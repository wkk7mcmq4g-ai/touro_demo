<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Models\Transfer;
use App\Support\Lookups;

/**
 * Cell colours of the transfers list, ported from PHPRunner's list-page events
 * "Before record processed" (BeforeProcessRowList) and "After record processed" (BeforeMoveNextList):
 *
 * - Status: its colour from optionvalues (always).
 * - Not cancelled:
 *   - TransferType, CarTypeID, TicketsRequested, AssistantStatus: their optionvalues colour;
 *   - TobeInvoiced empty: OrangeRed;
 *   - voucher start/end date and time: light green when set, OrangeRed when missing; a missing value is
 *     shown from its calculated column (Voucher_*_Calc) in brighter green, as PHPRunner filled it in;
 *   - Platform "Ναι" with PlatformPrice 0, From/To empty, and any text starting with "TBD": OrangeRed,
 *     and then the TransferID turns yellow;
 *   - Assistant starting with "TBA": OrangeRed.
 * - Customers 51/44 on VITO 2022 booked as VAN: Coach in OrangeRed.
 */
class TransferCellColors
{
    public const ALERT = '#FF4500'; // OrangeRed

    public const SET = '#E2EFDA';

    public const CALCULATED = '#90EE90';

    public const ISSUE_ID = '#FFFF00';

    /** Text that starts with TBD in these columns marks the row as having an issue. */
    private const TBD = ['Pax', 'Flight_Ship_Num_From', 'From', 'To', 'TransferTime', 'Notes', 'DriverNotes', 'LeadName', 'LeadPhone', 'LesseeName', 'PassengerName'];

    private const OPTION_COLOURS = ['TransferType' => 'TransferType', 'CarTypeID' => 'CarType', 'TicketsRequested' => 'TicketsRequested', 'AssistantStatus' => 'AssistantStatus'];

    /** Voucher column => its calculated column, and whether PHPRunner marked the calculated value green. */
    public const VOUCHER = [
        'Voucher_StartDt' => ['Voucher_Start_Date_Calc', true],
        'Voucher_StartTime' => ['Voucher_Start_StartTime_Calc', true],
        'Voucher_EndDt' => ['Voucher_End_Date_Calc', true],
        'Voucher_EndTime' => ['Voucher_End_Time_Calc', false],
    ];

    /** The value to show: a voucher field falls back to its calculated value, as PHPRunner did. */
    public static function voucherValue(Transfer $record, string $column): mixed
    {
        $value = $record->getAttribute($column);

        return filled($value) ? $value : $record->getAttribute(self::VOUCHER[$column][0]);
    }

    public static function background(Transfer $record, string $column): ?string
    {
        if ($column === 'Status') {
            return Lookups::color('Status', $record->Status);
        }

        if ($column === 'CoachCompanyID' && in_array((int) $record->cID_Customer, [51, 44], true)
            && $record->CoachCompanyID === 'VITO 2022' && $record->CarTypeID === 'VAN') {
            return self::ALERT;
        }

        if ($record->Status === 'Cancelled') {
            return null;
        }

        if (isset(self::OPTION_COLOURS[$column])) {
            return Lookups::color(self::OPTION_COLOURS[$column], $record->getAttribute($column));
        }

        if (isset(self::VOUCHER[$column])) {
            [$calculated, $markCalculated] = self::VOUCHER[$column];

            return match (true) {
                filled($record->getAttribute($column)) => self::SET,
                filled($record->getAttribute($calculated)) => $markCalculated ? self::CALCULATED : self::SET,
                default => self::ALERT,
            };
        }

        return match ($column) {
            'TransferID' => self::issues($record) > 0 ? self::ISSUE_ID : null,
            'TobeInvoiced' => blank($record->TobeInvoiced) ? self::ALERT : null,
            'PlatformPrice' => self::platformPriceMissing($record) ? self::ALERT : null,
            'Assistant' => str_starts_with((string) $record->Assistant, 'TBA') ? self::ALERT : null,
            'From', 'To' => blank($record->getAttribute($column)) || self::isTbd($record, $column) ? self::ALERT : null,
            default => in_array($column, self::TBD, true) && self::isTbd($record, $column) ? self::ALERT : null,
        };
    }

    /** PHPRunner's $cnt: the things still to be confirmed on the row. */
    public static function issues(Transfer $record): int
    {
        $count = self::platformPriceMissing($record) ? 1 : 0;
        foreach (self::TBD as $column) {
            $count += self::isTbd($record, $column) ? 1 : 0;
        }

        return $count + (blank($record->From) ? 1 : 0) + (blank($record->To) ? 1 : 0);
    }

    /** Cell attributes: the background and a readable text colour on it. */
    public static function attributes(Transfer $record, string $column): array
    {
        $colour = self::background($record, $column);
        if ($colour === null || ! preg_match('/^#?[0-9a-fA-F]{3,8}$|^[a-zA-Z]+$/', $colour)) {
            return [];
        }

        return ['style' => 'background-color:'.$colour.';color:'.self::textOn($colour), 'class' => 'myat-cell-painted'];
    }

    private static function isTbd(Transfer $record, string $column): bool
    {
        return str_starts_with((string) $record->getAttribute($column), 'TBD');
    }

    private static function platformPriceMissing(Transfer $record): bool
    {
        return $record->Platform === 'Ναι' && (float) $record->PlatformPrice == 0.0;
    }

    /** Black or white text, whichever reads better on the colour. */
    public static function textOn(string $colour): string
    {
        $hex = ltrim($colour, '#');
        if (! ctype_xdigit($hex)) {
            return '#0f172a';
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        [$r, $g, $b] = array_map(fn (string $c): float => hexdec($c) / 255, str_split(substr($hex, 0, 6), 2));
        $lin = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return $luminance > 0.179 ? '#0f172a' : '#ffffff';
    }
}
