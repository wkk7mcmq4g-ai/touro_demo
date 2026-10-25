<?php

namespace App\Support;

use App\Filament\Resources\Transfers\Pages\ListTransfers;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;

/**
 * Each user's list layout from the old PHPRunner app (MyAthensTransfersAPP_settings, written by its
 * paramsLogger): TYPE 2 column widths {"gridWidth", "thWidths": {cellId: px}}, TYPE 3 hidden
 * columns per device (NAME 0 = all devices, 1 = desktop) as a list of field names, TYPE 4 column
 * order as a list of field names. Converted to the new app's column manager state and widths.
 */
class PhpRunnerLayouts
{
    /**
     * PHPRunner list pages per new page: its table name in the settings, the header cell of each
     * field on the list (transfers_list.php grid, for TYPE 2 widths) and the renamed columns.
     */
    public const PAGES = [
        ListTransfers::class => [
            'table' => 'transfers',
            'rename' => ['cID_Customer' => 'customer.SupplierName'],
            'cells' => [
                'headcell_field' => 'TransferID', 'headcell_field2' => 'cID_Customer', 'headcell_field3' => 'SupplierCode',
                'headcell_field4' => 'Pax', 'headcell_field5' => 'TransferType', 'headcell_field6' => 'TransferDate',
                'headcell_field7' => 'TransferTime', 'headcell_field9' => 'From', 'headcell_field10' => 'To',
                'headcell_field12' => 'Flight_Ship_Num_From', 'headcell_field55' => 'OLP', 'cell_field42' => 'TicketsRequested',
                'headcell_field15' => 'AssistantStatus', 'headcell_field17' => 'Assistant_Price', 'headcell_field18' => 'CarTypeID',
                'cell_field49' => 'CoachCompanyID', 'headcell_field20' => 'Notes', 'headcell_field21' => 'LesseeName',
                'headcell_field22' => 'LeadName', 'headcell_field23' => 'LeadPhone', 'headcell_field24' => 'PassengerName',
                'headcell_field25' => 'Status', 'headcell_field27' => 'PlatformPrice', 'headcell_field28' => 'Assets',
                'headcell_field29' => 'Liabilities', 'headcell_field30' => 'CashRegister', 'headcell_field31' => 'Income',
                'headcell_field32' => 'PurchasePrice', 'headcell_field33' => 'SalePrice', 'headcell_field34' => 'PricePaid',
                'headcell_field35' => 'PriceRcvd', 'headcell_field36' => 'Expense', 'headcell_field39' => 'TobeInvoiced',
                'headcell_field40' => 'InvoicedIssued', 'headcell_field41' => 'InvoicedRcvd', 'headcell_field44' => 'VPOS',
                'headcell_field26' => 'SalesInvoiceNum', 'headcell_field47' => 'PurchaseInvoiceNum', 'headcell_field42' => 'DriverNotes',
                'headcell_field11' => 'PaymentOnHoldFlag', 'headcell_field8' => 'Assistant', 'headcell_field13' => 'Platform',
                'headcell_field14' => 'PaymentType', 'headcell_field19' => 'Voucher_StartDt', 'headcell_field43' => 'Voucher_StartTime',
                'headcell_field45' => 'Voucher_EndDt', 'headcell_field46' => 'Voucher_EndTime', 'headcell_field1' => 'ImportDate',
                'headcell_field48' => 'ExtraIncome', 'headcell_field49' => 'DriverReceiptType', 'headcell_field51' => 'TicketsValue',
                'headcell_field50' => 'FLNM', 'headcell_field53' => 'TaxiReceipt_Issued',
            ],
        ],
    ];

    /**
     * The user's PHPRunner layout for a page, in new column names, or null without one.
     *
     * @return array{order: list<string>|null, hidden: list<string>|null, widths: array<string, int>}|null
     */
    public static function read(string $page, string $username): ?array
    {
        $map = self::PAGES[$page] ?? null;
        if ($map === null || $username === '') {
            return null;
        }

        $rows = DB::table('MyAthensTransfersAPP_settings')
            ->where('USERNAME', $username)->where('TABLENAME', $map['table'])->whereIn('TYPE', [2, 3, 4])
            ->orderBy('ID')->get(['TYPE', 'NAME', 'SEARCH']);
        if ($rows->isEmpty()) {
            return null;
        }

        $name = fn (string $field): string => $map['rename'][$field] ?? $field;
        $decode = fn ($row): mixed => self::decode((string) $row->SEARCH);

        $orderRow = $rows->firstWhere('TYPE', 4);
        $order = $orderRow ? array_values(array_map($name, array_filter((array) $decode($orderRow), 'is_string'))) : null;

        // Hidden columns: the desktop entry, else the one for all devices.
        $hiddenRow = $rows->where('TYPE', 3)->firstWhere('NAME', '1') ?? $rows->where('TYPE', 3)->firstWhere('NAME', '0');
        $hidden = $hiddenRow ? array_values(array_map($name, array_filter((array) $decode($hiddenRow), 'is_string'))) : null;

        $widths = [];
        $widthRow = $rows->firstWhere('TYPE', 2);
        foreach ((array) (($widthRow ? $decode($widthRow) : null)['thWidths'] ?? []) as $cell => $px) {
            $field = $map['cells'][$cell] ?? $map['cells'][preg_replace('/^.*?((?:head)?cell_field\d*)$/', '$1', (string) $cell)] ?? null;
            if ($field !== null && is_numeric($px) && $px > 0) {
                $widths[$name($field)] = max(40, min(1200, (int) round($px)));
            }
        }

        return $order === null && $hidden === null && $widths === [] ? null : compact('order', 'hidden', 'widths');
    }

    /** PHPRunner writes JSON; older rows may be PHP-serialized. */
    private static function decode(string $value): mixed
    {
        $json = json_decode($value, true);
        if (is_array($json)) {
            return $json;
        }
        $php = @unserialize($value, ['allowed_classes' => false]);

        return is_array($php) ? $php : null;
    }

    /**
     * Puts the PHPRunner layout on a table page: column order and shown/hidden in Filament's column
     * manager, widths in the drag-to-resize widths. Returns false when the user has none.
     */
    public static function apply(HasTable $page, string $username): bool
    {
        $layout = self::read($page::class, $username);
        if ($layout === null) {
            return false;
        }

        $state = collect($page->getDefaultTableColumnState())->keyBy('name');

        if ($layout['order'] !== null) {
            $position = array_flip($layout['order']);
            $state = $state->sortBy(fn (array $item, string $name): int => $position[$name] ?? 1000 + array_search($name, $state->keys()->all(), true));
        }

        if ($layout['hidden'] !== null) {
            $state = $state->map(fn (array $item, string $name): array => ($item['isToggleable'] ?? false)
                ? ['isToggled' => ! in_array($name, $layout['hidden'], true)] + $item
                : $item);
        }

        $page->applyTableColumnManager($state->values()->all(), wasReordered: $layout['order'] !== null);

        $widths = (array) session('myat.widths', []);
        $keyed = collect($layout['widths'])->mapWithKeys(fn (int $px, string $name): array => [(string) str($name)->camel()->kebab() => $px])->all();
        if ($keyed === []) {
            unset($widths[$page->getName()]);
        } else {
            $widths[$page->getName()] = $keyed;
        }
        session()->put('myat.widths', $widths);

        return true;
    }

    /** True once the user has a layout of their own for this page in the new app. */
    public static function hasNewLayout(HasTable $page, string $username): bool
    {
        return DB::table('MyAthensTransfersAPP_settings')
            ->where('TYPE', TableLayouts::TYPE)->where('USERNAME', $username)
            ->whereIn('TABLENAME', [$page->getTableColumnsSessionKey(), 'myat.widths:'.$page->getName()])
            ->exists();
    }
}
