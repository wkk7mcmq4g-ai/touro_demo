<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Support\ExcelFilters;
use App\Support\TableLayouts;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Excel-style header filters on every table, and column order/widths kept per user. */
class TableLayoutsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['expenses', 'optionvalues', 'vehicles', 'drivers']);
        DB::table('MyAthensTransfersAPP_settings')->where('TYPE', TableLayouts::TYPE)->delete();
        DB::table('expenses')->insert([
            ['ExpenseDate' => '2026-09-30', 'ExpenseReason' => 'Α', 'Company' => 'ΙΚΕ', 'ExpenseAmountRcvd' => 100, 'ExpenseAmountPaid' => 0],
            ['ExpenseDate' => '2026-10-01', 'ExpenseReason' => 'Β', 'Company' => 'ΑΤΟΜΙΚΗ', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 30],
            ['ExpenseDate' => '2026-10-02', 'ExpenseReason' => 'Γ', 'Company' => '', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 5],
        ]);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    protected function tearDown(): void
    {
        DB::table('MyAthensTransfersAPP_settings')->where('TYPE', TableLayouts::TYPE)->delete();
        parent::tearDown();
    }

    public function test_values_list_the_column_and_dates_are_recognised(): void
    {
        $page = Livewire::test(ListExpenses::class);

        $company = ExcelFilters::values($page->instance(), 'Company');
        $this->assertSame('list', $company['type']);
        $this->assertSame(['ΑΤΟΜΙΚΗ', 'ΙΚΕ', ExcelFilters::EMPTY], array_column($company['values'], 'v'));

        $dates = ExcelFilters::values($page->instance(), 'ExpenseDate');
        $this->assertSame('date', $dates['type']);
        $this->assertSame(['2026-09-30', '2026-10-01', '2026-10-02'], array_column($dates['values'], 'v'));
    }

    public function test_ticked_values_filter_the_table_from_the_header(): void
    {
        $rows = DB::table('expenses')->orderBy('ExpenseID')->get();

        Livewire::test(ListExpenses::class)
            ->call('applyExcelFilter', 'ExpenseDate', ['2026-10-01', '2026-10-02'])
            ->assertCanSeeTableRecords([$rows[1]->ExpenseID, $rows[2]->ExpenseID])
            ->assertCanNotSeeTableRecords([$rows[0]->ExpenseID])
            ->call('applyExcelFilter', 'Company', [ExcelFilters::EMPTY])
            ->assertCanSeeTableRecords([$rows[2]->ExpenseID])
            ->assertCanNotSeeTableRecords([$rows[1]->ExpenseID]);

        // Kept for the session; another column's list is worked out with the other filters on.
        $page = Livewire::test(ListExpenses::class)->assertCanNotSeeTableRecords([$rows[1]->ExpenseID]);
        $this->assertSame(['ΑΤΟΜΙΚΗ', ExcelFilters::EMPTY], array_column(ExcelFilters::values($page->instance(), 'Company')['values'], 'v'));
        $this->assertSame(['2026-10-02'], array_column(ExcelFilters::values($page->instance(), 'ExpenseDate')['values'], 'v'));

        $page->call('clearExcelFilters')->assertCanSeeTableRecords($rows->pluck('ExpenseID')->all());
    }

    public function test_unknown_or_computed_columns_are_refused(): void
    {
        Livewire::test(ListExpenses::class)->call('applyExcelFilter', 'nope; drop', ['x'])->assertStatus(422);
        $this->assertSame([], session('myat_excel', []) ?: []);
    }

    public function test_layouts_and_widths_are_saved_to_the_users_settings_and_loaded_back(): void
    {
        $user = auth()->user();
        Livewire::test(ListExpenses::class)->call('saveColumnWidths', ['ExpenseReason' => 320, 'bad key' => 50, 'Company' => 'x']);
        $name = array_key_first(session('myat.widths'));
        $this->assertSame(['ExpenseReason' => 320], session('myat.widths')[$name]);

        session()->put('filament.tables.abc123.columns', [['name' => 'Company', 'isToggled' => false]]);
        TableLayouts::save($user);
        $rows = DB::table('MyAthensTransfersAPP_settings')->where('TYPE', TableLayouts::TYPE)->where('USERNAME', $user->username)->pluck('SEARCH', 'TABLENAME');
        $this->assertContains("myat.widths:{$name}", $rows->keys()->all());
        $this->assertContains('filament.tables.abc123.columns', $rows->keys()->all());

        // Another login (another browser) gets them back.
        session()->flush();
        DB::table('MyAthensTransfersAPP_settings')->insert(['TYPE' => TableLayouts::TYPE, 'USERNAME' => $user->username, 'TABLENAME' => 'evil.key', 'SEARCH' => '1']);
        TableLayouts::load($user);
        $this->assertSame(['ExpenseReason' => 320], session('myat.widths')[$name]);
        $this->assertSame('Company', session('filament.tables.abc123.columns')[0]['name']);
        $this->assertNull(session('evil'));

        // The middleware loads them on the first page after login.
        session()->flush();
        $this->get('/admin/expenses')->assertOk()->assertSee('myatTables', false);
        $this->assertSame(['ExpenseReason' => 320], session('myat.widths')[$name]);
    }
}
