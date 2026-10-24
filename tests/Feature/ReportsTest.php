<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\AccountantExport;
use App\Filament\Pages\Reports\DueReport;
use App\Filament\Pages\Reports\ExpensesReport;
use App\Filament\Pages\Reports\StatsReport;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Αναφορές: the PHPRunner crosstab reports and report lists. */
class ReportsTest extends TestCase
{
    private const SLUGS = ['stats', 'capacity', 'accountant', 'receive', 'due', 'issue', 'require', 'expenses', 'pl', 'fuel', 'taxis', 'duplicates', 'drivers', 'van-taxi'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['expenses', 'optionvalues', 'vehicles', 'drivers', 'transfers', 'transfer_charges', 'counterparties']);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_every_report_opens(): void
    {
        foreach (self::SLUGS as $slug) {
            $this->get("/admin/reports/{$slug}")->assertOk();
        }
    }

    public function test_entry_sees_only_the_reports_phprunner_gives_it(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        foreach (self::SLUGS as $slug) {
            $this->get("/admin/reports/{$slug}")->assertStatus($slug === 'van-taxi' ? 200 : 403);
        }
    }

    public function test_crosstab_adds_up_by_row_and_column_with_filters(): void
    {
        $rent = DB::table('optionvalues')->insertGetId(['Option' => 'ExpenseCategory', 'Value' => 'Ενοίκιο'], 'OptionID');
        $fuel = DB::table('optionvalues')->insertGetId(['Option' => 'ExpenseCategory', 'Value' => 'Καύσιμα'], 'OptionID');
        DB::table('expenses')->insert([
            ['ExpenseDate' => '2026-09-05', 'ExpenseReason' => 'a', 'ExpenseAmountPaid' => 600, 'ExpenseAmountRcvd' => 0, 'ExpenseCategoryID' => $rent, 'Company' => 'ΙΚΕ'],
            ['ExpenseDate' => '2026-10-05', 'ExpenseReason' => 'b', 'ExpenseAmountPaid' => 650, 'ExpenseAmountRcvd' => 0, 'ExpenseCategoryID' => $rent, 'Company' => 'ΙΚΕ'],
            ['ExpenseDate' => '2026-10-10', 'ExpenseReason' => 'c', 'ExpenseAmountPaid' => 80, 'ExpenseAmountRcvd' => 0, 'ExpenseCategoryID' => $fuel, 'Company' => 'ΑΤΟΜΙΚΗ'],
        ]);

        $page = Livewire::test(ExpensesReport::class)->assertOk()->assertSee('Ενοίκιο')->assertSee('Καύσιμα');
        $r = $page->instance()->result();
        // Expense period = month end, as PHPRunner (Last_day).
        $this->assertSame(['2026-09-30' => 600.0, '2026-10-31' => 730.0], $r['rowTotals']);
        $this->assertSame('30/09/2026', $r['rows']['2026-09-30']);
        $this->assertEquals(1330, $r['total']);
        $this->assertEquals(650, $r['cells']['2026-10-31'][(string) $rent]);

        // No vehicle on the expense: "No Vehicle", as PHPRunner, and it can be filtered.
        $page->set('data.cols', 'VehicleID');
        $this->assertSame(['No Vehicle' => 'No Vehicle'], $page->instance()->result()['cols']);
        $page->set('data.filter.VehicleID', ['No Vehicle']);
        $this->assertEquals(1330, $page->instance()->result()['total']);

        $page->set('data.filter.ExpenseCategoryID', [(string) $rent]);
        $this->assertEquals(1250, $page->instance()->result()['total']);

        $page->set('data.cols', null)->set('data.rows', 'year');
        $this->assertSame(['2026' => 1250.0], $page->instance()->result()['rowTotals']);
    }

    public function test_crosstab_and_list_export_to_excel(): void
    {
        $response = Livewire::test(StatsReport::class)->callAction('export');
        $response->assertFileDownloaded();

        DB::table('transfers')->insert(['TransferDate' => '2026-10-01', 'SalesInvoiceNum' => 'ΤΠΥ-12 ', 'PaymentType' => 'Cash', 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b']);
        Livewire::test(AccountantExport::class)->assertSee('ΤΠΥ-12')
            ->callAction(TestAction::make('export')->table())
            ->assertFileDownloaded();
    }

    public function test_due_report_always_has_the_months_across(): void
    {
        DB::table('transfers')->insert([
            ['TransferDate' => '2025-12-03', 'CoachCompanyID' => 'VITO 2022', 'PurchasePrice' => 50, 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b', 'Status' => 'Completed'],
            ['TransferDate' => '2026-01-05', 'CoachCompanyID' => 'VITO 2022', 'PurchasePrice' => 70, 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b', 'Status' => 'Completed'],
        ]);

        // Asking for something else across is ignored: the months stay the columns, grouped by year.
        $page = Livewire::test(DueReport::class)->assertOk()->set('data.cols', 'Supplier')->set('data.rows', 'month');
        $r = $page->instance()->result();
        $this->assertTrue($r['colTime']);
        $this->assertSame([2025, 2026], array_keys(array_filter($r['colGroups'], fn ($k) => $k !== 'Παλαιότερα', ARRAY_FILTER_USE_KEY)));
        $this->assertArrayHasKey('2025-12-31', $r['cols']);
        $this->assertSame('Coach', $r['rowLabel']);
    }

    public function test_quick_filter_leaves_out_zero_amounts(): void
    {
        DB::table('transfers')->insert([
            ['TransferDate' => '2026-01-05', 'CoachCompanyID' => 'VITO 2022', 'PurchasePrice' => 70, 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b', 'Status' => 'Completed'],
            ['TransferDate' => '2026-01-06', 'CoachCompanyID' => 'E CLASS 2015', 'PurchasePrice' => 0, 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b', 'Status' => 'Completed'],
        ]);

        $page = Livewire::test(DueReport::class)->assertSee('≠ 0', false);
        $this->assertArrayHasKey('E CLASS 2015', $page->instance()->result()['rows']);

        $page->set('nonZero', true);
        $rows = $page->instance()->result()['rows'];
        $this->assertArrayHasKey('VITO 2022', $rows);
        $this->assertArrayNotHasKey('E CLASS 2015', $rows);
    }
}
