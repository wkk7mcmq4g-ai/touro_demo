<?php

namespace App\Support;

use App\Models\Transfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Quick edits of one transfer field without opening the form: double-click a cell on the
 * Transfers list (or the "Χωρίς όχημα" button on the Dashboard) and a small window opens in the
 * browser (view filament/transfers/cell-editor). Only Αποθήκευση reaches the server
 * (trait App\Filament\Concerns\EditsTransferCells → save() here). The value is saved through the
 * Transfer model, so the same stamps, triggers, price procedure and own-vehicle rule run as on the
 * edit page; the checks are the form's own (required fields, values from its lists, lengths).
 */
class TransferCellEdit
{
    /** Fields with a double-click window on the Transfers list. */
    public const FIELDS = ['CoachCompanyID', 'Status', 'TransferTime', 'Assistant', 'DriverNotes'];

    /** The form's fixed lists (TransferForm uses the same). */
    public const GUIDE_STATUSES = ['Request', 'Client Rejected', 'Payment Pending', 'Payment Received'];

    public const TICKETS = ['Contacted', 'Paid in Advance', 'Cash', "Purchased by\u{a0}client", 'No tickets needed'];

    public const INVOICE_TYPES = ['Όχι', 'Τιμολόγιο', 'Απόδειξη'];

    /** Money fields: only for users who see the money (as the totals above the list). */
    public const MONEY_FIELDS = ['TobeInvoiced', 'InvoicedIssued', 'PaymentOnHoldFlag', 'SalePrice', 'PurchasePrice'];

    /**
     * The fields «Αλλαγή» sets on many ticked transfers at once: label, group and the kind of input.
     * Kinds: list (options, `empty` = label of the blank choice, none = required), text, textarea, date, money.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function bulkFields(): array
    {
        $yes = ['Ναι' => 'Ναι'];
        $fields = [
            'CoachCompanyID' => ['label' => 'Coach', 'group' => 'Λειτουργικά', 'kind' => 'list', 'options' => Lookups::coaches(), 'empty' => '— Χωρίς όχημα —',
                'hint' => 'Τα δικά μας οχήματα μηδενίζουν την τιμή αγοράς.'],
            'Status' => ['label' => 'Status', 'group' => 'Λειτουργικά', 'kind' => 'list', 'options' => self::statuses()],
            'TransferDate' => ['label' => 'Transfer Date', 'group' => 'Λειτουργικά', 'kind' => 'date'],
            'TransferTime' => ['label' => 'Transfer Time', 'group' => 'Λειτουργικά', 'kind' => 'text', 'max' => 50, 'placeholder' => '14:30 ή TBD'],
            'CarTypeID' => ['label' => 'Car Type', 'group' => 'Λειτουργικά', 'kind' => 'list', 'options' => Lookups::optionValues('CarType')],
            'Assistant' => ['label' => 'Assistant (ξεναγός)', 'group' => 'Λειτουργικά', 'kind' => 'text', 'max' => 500, 'suggest' => array_keys(Lookups::assistantNames()),
                'hint' => 'Κενό = χωρίς ξεναγό.'],
            'AssistantStatus' => ['label' => 'Guide status', 'group' => 'Λειτουργικά', 'kind' => 'list', 'options' => array_combine(self::GUIDE_STATUSES, self::GUIDE_STATUSES), 'empty' => '— Κενό —'],
            'TicketsRequested' => ['label' => 'Tickets', 'group' => 'Λειτουργικά', 'kind' => 'list', 'options' => array_combine(self::TICKETS, self::TICKETS), 'empty' => '— Κενό —'],
            'DriverNotes' => ['label' => 'Driver Notes', 'group' => 'Λειτουργικά', 'kind' => 'textarea', 'max' => 2500, 'hint' => 'Αντικαθιστά τα Driver Notes όλων.'],
            'TobeInvoiced' => ['label' => 'Τύπος παραστ.', 'group' => 'Οικονομικά', 'kind' => 'list', 'options' => array_combine(self::INVOICE_TYPES, self::INVOICE_TYPES)],
            'InvoicedIssued' => ['label' => 'Εκδόθηκε παραστ.', 'group' => 'Οικονομικά', 'kind' => 'list', 'options' => $yes, 'empty' => '— Όχι (κενό) —'],
            'PaymentOnHoldFlag' => ['label' => 'Αναμ. Έκδ. (on hold)', 'group' => 'Οικονομικά', 'kind' => 'list', 'options' => $yes, 'empty' => '— Όχι (κενό) —'],
            'SalePrice' => ['label' => 'Τιμή Πώλ.', 'group' => 'Οικονομικά', 'kind' => 'money',
                'hint' => 'Όπου η τιμή βγαίνει από τα Products, την ξαναϋπολογίζει η βάση.'],
            'PurchasePrice' => ['label' => 'Τιμή Αγορ.', 'group' => 'Οικονομικά', 'kind' => 'money',
                'hint' => 'Στα δικά μας οχήματα η τιμή αγοράς μένει 0.'],
        ];

        return self::seesMoney() ? $fields : array_diff_key($fields, array_flip(self::MONEY_FIELDS));
    }

    public static function seesMoney(): bool
    {
        return (bool) auth()->user()?->may('Dashboard', 'view');
    }

    /**
     * What the window shows for each field (sent to the browser once per page).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function config(): array
    {
        return [
            'CoachCompanyID' => ['label' => 'Coach', 'kind' => 'list', 'options' => array_values(Lookups::coaches()),
                'empty' => '— Χωρίς όχημα —', 'hint' => 'Τα δικά μας οχήματα μηδενίζουν την τιμή αγοράς.', 'search' => 'Αναζήτηση οχήματος…'],
            'Status' => ['label' => 'Status', 'kind' => 'list', 'options' => array_values(self::statuses()), 'empty' => null, 'search' => 'Αναζήτηση…'],
            'Assistant' => ['label' => 'Assistant (ξεναγός)', 'kind' => 'list', 'free' => true, 'options' => array_values(Lookups::assistantNames()),
                'empty' => '— Χωρίς ξεναγό —', 'hint' => 'Διαλέξτε από τη λίστα ή γράψτε άλλο όνομα.', 'search' => 'Όνομα ξεναγού…'],
            'TransferTime' => ['label' => 'Transfer Time', 'kind' => 'text', 'placeholder' => '14:30 ή TBD', 'max' => 50],
            'DriverNotes' => ['label' => 'Driver Notes', 'kind' => 'textarea', 'max' => 2500, 'copy' => '>> Από τα Transfer Notes'],
        ];
    }

    /** The data a cell carries for the window: transfer, field, full current value (+ Transfer Notes for the copy button). */
    public static function cellData(Transfer $record, string $field): array
    {
        $data = ['id' => (int) $record->getKey(), 'field' => $field, 'value' => (string) ($record->getAttribute($field) ?? '')];
        if ($field === 'DriverNotes') {
            $data['notes'] = (string) ($record->Notes ?? '');
        }

        return $data;
    }

    /**
     * Cell attributes that open the window on a double-click (only for users who may edit transfers).
     * The data goes HTML-escaped into an attribute: Laravel's attribute bag doesn't escape it.
     *
     * @return array<string, string>
     */
    public static function cellAttributes(Transfer $record, string $field): array
    {
        if (! self::allowed()) {
            return [];
        }

        return [
            'class' => 'myat-dblclick-edit',
            'title' => 'Διπλό κλικ για αλλαγή',
            'data-myat-cell' => e(json_encode(self::cellData($record, $field), JSON_UNESCAPED_UNICODE)),
            'x-on:dblclick.prevent' => '$dispatch(\'myat-cell\', JSON.parse($el.dataset.myatCell))',
        ];
    }

    public static function allowed(): bool
    {
        return (bool) auth()->user()?->may('transfers', 'edit');
    }

    /**
     * Save one field. Returns null when saved, or the message to show in the window.
     */
    public static function save(int $id, string $field, ?string $value): ?string
    {
        abort_unless(self::allowed(), 403);
        abort_unless(in_array($field, self::FIELDS, true) || array_key_exists($field, self::BULK_KINDS), 422);
        abort_if(in_array($field, self::MONEY_FIELDS, true) && ! self::seesMoney(), 403);

        $transfer = Transfer::query()->findOrFail($id);
        $value = $field === 'DriverNotes' ? rtrim((string) $value) : trim((string) $value);
        $current = match (true) {
            $field === 'TransferDate' => $transfer->TransferDate?->toDateString() ?? '',
            in_array($field, ['SalePrice', 'PurchasePrice'], true) => $transfer->getAttribute($field) === null ? '' : (string) (float) $transfer->getAttribute($field),
            default => (string) ($transfer->getAttribute($field) ?? ''),
        };
        if (in_array($field, ['SalePrice', 'PurchasePrice'], true) && is_numeric(str_replace(',', '.', $value))) {
            $value = (string) (float) str_replace(',', '.', $value);
        }

        if ($value === $current) {
            return null;
        }

        $error = match ($field) {
            'CoachCompanyID' => $value !== '' && ! array_key_exists($value, Lookups::coaches()) ? 'Το «'.$value.'» δεν είναι στη λίστα οχημάτων.' : null,
            'Status' => match (true) {
                $value === '' => 'Το Status είναι υποχρεωτικό.',
                ! array_key_exists($value, self::statuses()) => 'Το «'.$value.'» δεν είναι στη λίστα.',
                default => null,
            },
            'TransferTime' => match (true) {
                $value === '' => 'Η ώρα είναι υποχρεωτική.',
                mb_strlen($value) > 50 => 'Έως 50 χαρακτήρες.',
                default => null,
            },
            'Assistant' => mb_strlen($value) > 500 ? 'Έως 500 χαρακτήρες.' : null,
            'DriverNotes' => mb_strlen($value) > 2500 ? 'Έως 2.500 χαρακτήρες.' : null,
            'TransferDate' => self::dateError($value, $transfer),
            'CarTypeID' => self::listError($value, Lookups::optionValues('CarType'), required: true),
            'AssistantStatus' => self::listError($value, array_flip(self::GUIDE_STATUSES)),
            'TicketsRequested' => self::listError($value, array_flip(self::TICKETS)),
            'TobeInvoiced' => self::listError($value, array_flip(self::INVOICE_TYPES), required: true),
            'InvoicedIssued', 'PaymentOnHoldFlag' => self::listError($value, ['Ναι' => true]),
            'SalePrice', 'PurchasePrice' => match (true) {
                $value === '' => 'Η τιμή είναι υποχρεωτική.',
                ! is_numeric($value) => 'Γράψτε αριθμό.',
                (float) $value < 0 => 'Η τιμή δεν μπορεί να είναι αρνητική.',
                default => null,
            },
        };
        if ($error !== null) {
            return $error;
        }

        $transfer->setAttribute($field, in_array($field, ['SalePrice', 'PurchasePrice'], true) ? (float) $value : $value);
        $transfer->save();

        return null;
    }

    /** The fields only «Αλλαγή» sets (the double-click ones are in FIELDS). */
    private const BULK_KINDS = ['TransferDate' => 'date', 'CarTypeID' => 'list', 'AssistantStatus' => 'list', 'TicketsRequested' => 'list',
        'TobeInvoiced' => 'list', 'InvoicedIssued' => 'list', 'PaymentOnHoldFlag' => 'list', 'SalePrice' => 'money', 'PurchasePrice' => 'money'];

    private static function listError(string $value, array $options, bool $required = false): ?string
    {
        return match (true) {
            $value === '' => $required ? 'Η τιμή είναι υποχρεωτική.' : null,
            ! array_key_exists($value, $options) => 'Το «'.$value.'» δεν είναι στη λίστα.',
            default => null,
        };
    }

    /** The form's date checks (TransferRules): required, and a changed date not in the past. */
    private static function dateError(string $value, Transfer $transfer): ?string
    {
        if ($value === '') {
            return 'Η ημερομηνία είναι υποχρεωτική.';
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            $date = false;
        }
        if (! $date || $date->format('Y-m-d') !== $value) {
            return 'Μη έγκυρη ημερομηνία.';
        }

        return TransferRules::transferDateError($value, $transfer, $transfer->ImportDate, false);
    }

    /** "#10482 · Coach: VITO 2022" for the confirmation. */
    public static function savedMessage(int $id, string $field, ?string $value): string
    {
        $label = self::config()[$field]['label'] ?? $field;
        $value = trim((string) $value);

        return '#'.$id.' · '.Str::before($label, ' (').': '.($value === '' ? '—' : Str::limit(str_replace("\n", ' ', $value), 60));
    }

    /** @return array<string, string> the statuses this user's group may set (the form's list) */
    private static function statuses(): array
    {
        return Lookups::statusesFor(auth()->user()?->groupid);
    }
}
