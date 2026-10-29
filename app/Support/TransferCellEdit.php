<?php

namespace App\Support;

use App\Models\Transfer;
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
    public const FIELDS = ['CoachCompanyID', 'Status', 'TransferTime', 'Assistant', 'DriverNotes'];

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
        abort_unless(in_array($field, self::FIELDS, true), 422);

        $transfer = Transfer::query()->findOrFail($id);
        $value = $field === 'DriverNotes' ? rtrim((string) $value) : trim((string) $value);
        $current = (string) ($transfer->getAttribute($field) ?? '');

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
        };
        if ($error !== null) {
            return $error;
        }

        $transfer->setAttribute($field, $value);
        $transfer->save();

        return null;
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
