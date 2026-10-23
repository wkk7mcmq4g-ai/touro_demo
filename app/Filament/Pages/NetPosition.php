<?php

namespace App\Filament\Pages;

use App\Support\Permissions;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Θέση" (v_netposition): income, company transactions, cash and receivables in one
 * view. All figures come from the database view; nothing is calculated here.
 */
class NetPosition extends Page
{
    protected string $view = 'filament.pages.net-position';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Θέση';

    protected static ?string $title = 'Θέση';

    protected static ?string $slug = 'net-position';

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), 'v_netposition', 'view');
    }

    /**
     * The view's single row, grouped like the PHPRunner page.
     *
     * @return list<array{title: string, rows: list<array{label: string, value: float, total?: bool, signed?: bool}>}>
     */
    public function groups(): array
    {
        $p = (array) (DB::table('v_netposition')->first() ?? []);
        $v = fn (string $column): float => (float) ($p[$column] ?? 0);

        return [
            ['title' => 'Έσοδα', 'rows' => [
                ['label' => 'Έσοδα προ 2023', 'value' => $v('Before2023')],
                ['label' => 'Έσοδα από 2023', 'value' => $v('Touro_Cash')],
                ['label' => 'Συνολικά έσοδα', 'value' => $v('TotalIncome'), 'total' => true],
            ]],
            ['title' => 'Συναλλαγές', 'rows' => [
                ['label' => 'Συναλλαγές ΙΚΕ', 'value' => $v('ike'), 'signed' => true],
                ['label' => 'Συναλλαγές εταιρίας', 'value' => $v('atomiki'), 'signed' => true],
            ]],
            ['title' => 'Ταμείο', 'rows' => [
                ['label' => 'Λογιστικό ταμείο', 'value' => $v('CashRegister')],
                ['label' => 'Cash In Hand', 'value' => $v('CashInHand')],
                ['label' => 'Διαφορά με πραγματικό ταμείο', 'value' => $v('DiffInCash'), 'total' => true, 'signed' => true],
            ]],
            ['title' => 'Απαιτήσεις & υποχρεώσεις', 'rows' => [
                ['label' => 'Σύνολο απαιτήσεων', 'value' => $v('Require')],
                ['label' => 'Σύνολο υποχρεώσεων', 'value' => $v('Due')],
                ['label' => 'Net', 'value' => $v('NetAssets'), 'total' => true, 'signed' => true],
            ]],
        ];
    }

    public function netPosition(): float
    {
        return (float) (DB::table('v_netposition')->value('NetPosition') ?? 0);
    }
}
