<?php

namespace Tests\Feature;

use App\Filament\Pages\NetPosition;
use App\Filament\Resources\Cash\Pages\CreateCash;
use App\Filament\Resources\Cash\Pages\ListCash;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Cash;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Οικονομικά: Συναλλαγές (expenses), Θέση (v_netposition), Ταμείο (cash). */
class FinanceTest extends TestCase
{
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['expenses', 'cash', 'CashRegister', 'transfers', 'optionvalues', 'vehicles', 'drivers']);
        $this->categoryId = DB::table('optionvalues')->insertGetId(['Option' => 'ExpenseCategory', 'Value' => 'Ενοίκιο', 'Color' => '#ffcc00'], 'OptionID');
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_every_screen_opens(): void
    {
        foreach (['expenses', 'expenses/create', 'cash', 'cash/create', 'net-position'] as $slug) {
            $this->get("/admin/{$slug}")->assertOk();
        }
    }

    public function test_transactions_are_stamped_like_phprunner(): void
    {
        Livewire::test(CreateExpense::class)
            ->fillForm(['Company' => 'ΙΚΕ', 'ExpenseDate' => '2026-10-20', 'ExpenseReason' => 'Ενοίκιο γραφείου', 'ExpenseCategoryID' => $this->categoryId, 'ExpenseAmountPaid' => 500])
            ->call('create')->assertHasNoFormErrors();
        $expense = Expense::query()->firstOrFail();
        $this->assertSame([auth()->user()->username, 500.0, 0.0], [$expense->CreatedBy, $expense->ExpenseAmountPaid, $expense->ExpenseAmountRcvd]);

        Livewire::test(EditExpense::class, ['record' => $expense->ExpenseID])
            ->fillForm(['ExpenseAmountPaid' => 450])->call('save')->assertHasNoFormErrors();
        $this->assertSame([auth()->user()->username, 450.0], [$expense->fresh()->MofifiedBy, $expense->fresh()->ExpenseAmountPaid]);

        Livewire::test(CreateExpense::class)->fillForm(['ExpenseReason' => 'x'])->call('create')
            ->assertHasFormErrors(['Company' => 'required']);
    }

    public function test_list_tabs_totals_and_update_selected(): void
    {
        DB::table('expenses')->insert([
            ['ExpenseDate' => '2026-10-01', 'ExpenseReason' => 'Α', 'Company' => 'ΙΚΕ', 'ExpenseAmountRcvd' => 100, 'ExpenseAmountPaid' => 0],
            ['ExpenseDate' => '2026-10-02', 'ExpenseReason' => 'Β', 'Company' => 'ΑΤΟΜΙΚΗ', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 30],
        ]);
        [$ike, $atomiki] = Expense::query()->orderBy('ExpenseID')->get()->all();

        Livewire::test(ListExpenses::class)->assertCanSeeTableRecords([$ike, $atomiki])
            ->set('activeTab', 'ΙΚΕ')->assertCanSeeTableRecords([$ike])->assertCanNotSeeTableRecords([$atomiki]);

        Livewire::test(ListExpenses::class)
            ->callTableBulkAction('updateSelected', [$ike, $atomiki], data: ['Flag' => 'Greg', 'ExpenseCategoryID' => $this->categoryId])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame(['Greg', 'Greg'], Expense::query()->orderBy('ExpenseID')->pluck('Flag')->all());
        $this->assertSame(['ΙΚΕ', 'ΑΤΟΜΙΚΗ'], Expense::query()->orderBy('ExpenseID')->pluck('Company')->all(), 'blank fields are left alone');
    }

    public function test_cash_counts_never_write_the_total_and_start_from_the_last_count(): void
    {
        DB::table('cash')->insert(['DT' => '2026-10-01', 'ERB' => 1000, 'Cash' => 200]);

        Livewire::test(CreateCash::class)
            ->assertSchemaStateSet(['ERB' => 1000.0, 'Cash' => 200.0])
            ->fillForm(['DT' => '2026-10-23', 'Viva' => 50])
            ->call('create')->assertHasNoFormErrors();

        $latest = Cash::query()->latest('ID')->firstOrFail();
        $this->assertEquals(1250, $latest->Total, 'MariaDB adds the boxes up');
        Livewire::test(ListCash::class)->assertCanSeeTableRecords([$latest]);
    }

    public function test_net_position_reads_the_view(): void
    {
        DB::table('CashRegister')->insert(['ID' => 1, 'Before2023' => 10000, 'Others' => 0]);
        DB::table('expenses')->insert(['ExpenseDate' => '2026-10-01', 'ExpenseReason' => 'Α', 'Company' => 'ΙΚΕ', 'ExpenseAmountRcvd' => 0, 'ExpenseAmountPaid' => 300]);
        DB::table('cash')->insert(['DT' => '2026-10-01', 'Cash' => 9000]);

        Livewire::test(NetPosition::class)->assertOk()
            ->assertSee('ΣΥΝΟΛΙΚΗ ΘΕΣΗ')->assertSee('Συναλλαγές ΙΚΕ')
            ->assertSeeHtml('myat-neg');
    }

    public function test_entry_users_follow_phprunner_permissions(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        foreach (['expenses' => 'expenses', 'cash' => 'cash', 'net-position' => 'v_netposition'] as $slug => $table) {
            $this->get("/admin/{$slug}")->assertStatus(auth()->user()->may($table, 'view') ? 200 : 403);
        }
    }
}
