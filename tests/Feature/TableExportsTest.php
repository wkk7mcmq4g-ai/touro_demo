<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Support\ExcelFilters;
use App\Support\SavedFilters;
use App\Support\TableExports;
use App\Support\TablePresets;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** Excel export with chosen columns, saved exports and saved filters, on any table. */
class TableExportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['expenses', 'optionvalues', 'vehicles', 'drivers']);
        DB::table('MyAthensTransfersAPP_settings')->whereIn('TYPE', [TablePresets::EXPORT, TablePresets::FILTERS])->delete();
        DB::table('expenses')->insert([
            ['ExpenseDate' => '2026-09-30', 'ExpenseReason' => 'Ενοίκιο', 'Company' => 'ΙΚΕ', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 500],
            ['ExpenseDate' => '2026-10-01', 'ExpenseReason' => 'Καύσιμα', 'Company' => 'ΑΤΟΜΙΚΗ', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 60.5],
        ]);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    protected function tearDown(): void
    {
        DB::table('MyAthensTransfersAPP_settings')->whereIn('TYPE', [TablePresets::EXPORT, TablePresets::FILTERS])->delete();
        parent::tearDown();
    }

    private function rows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        return $rows;
    }

    public function test_exports_the_chosen_columns_of_the_filtered_rows(): void
    {
        $page = Livewire::test(ListExpenses::class);
        $names = array_column(TableExports::columns($page->instance()), 'name');
        $this->assertContains('ExpenseReason', $names);

        ExcelFilters::set($page->instance(), 'Company', ['ΑΤΟΜΙΚΗ']);
        $url = TableExports::export($page->instance(), ['ExpenseDate', 'ExpenseReason', 'ExpenseAmountPaid'], 'Συναλλαγές');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $path = session("myat.exports.{$token}")['path'];

        $rows = $this->rows($path);
        $this->assertSame(['Ημερομηνία', 'Αιτιολογία', 'Πληρωμή'], $rows[0]);
        $this->assertCount(2, $rows); // header + the one ΑΤΟΜΙΚΗ row
        $this->assertSame('Καύσιμα', $rows[1][1]);
        $this->assertEquals(60.5, $rows[1][2]);
        $this->assertSame('2026-10-01', $rows[1][0]->format('Y-m-d'));

        // The link works once, for the user who made it.
        $this->get($url)->assertOk()->assertDownload();
        $this->get($url)->assertNotFound();
    }

    public function test_exports_and_filters_are_saved_per_user_and_put_back(): void
    {
        $page = Livewire::test(ListExpenses::class);
        $key = SavedFilters::key($page->instance());

        $id = TablePresets::save(TablePresets::EXPORT, $key, 'Λογιστήριο', ['columns' => ['ExpenseDate', 'ExpenseAmountPaid']]);
        $this->assertSame($id, TablePresets::save(TablePresets::EXPORT, $key, 'Λογιστήριο', ['columns' => ['ExpenseDate']]));
        $this->assertSame(['ExpenseDate'], TablePresets::list(TablePresets::EXPORT, $key)[0]['data']['columns']);

        ExcelFilters::set($page->instance(), 'Company', ['ΙΚΕ']);
        $filters = TablePresets::save(TablePresets::FILTERS, $key, 'Μόνο ΙΚΕ', SavedFilters::current($page->instance()));
        ExcelFilters::clear($page->instance());

        $page->call('applyFilterPreset', $filters)
            ->assertCanSeeTableRecords(Expense::query()->where('Company', 'ΙΚΕ')->get())
            ->assertCanNotSeeTableRecords(Expense::query()->where('Company', 'ΑΤΟΜΙΚΗ')->get());

        // Someone else sees none of them.
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->assertSame([], TablePresets::list(TablePresets::FILTERS, $key));
    }
}
