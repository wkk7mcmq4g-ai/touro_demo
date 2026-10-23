<?php

namespace App\Filament\Resources\Transfers\Actions;

use App\Services\Elorus\InvoiceRun;
use App\Services\Elorus\TransferInvoicing;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Elorus buttons of PHPRunner's "Τιμολόγηση" menu, on the ticked transfers / charges.
 * The work itself is in App\Services\Elorus\TransferInvoicing.
 */
class InvoicingActions
{
    /** @return list<BulkAction> */
    public static function forTransfers(): array
    {
        return [
            self::make('invoiceTotal', 'One invoice with 1 product total amount', [
                self::date(),
                Textarea::make('description')->label('Item description')->rows(3),
                self::draft(),
            ], fn (TransferInvoicing $s, Collection $r, array $d) => $s->oneInvoiceTotal($r, $d['date'], (string) ($d['description'] ?? ''), $d['draft'])),

            self::make('invoiceLines', 'One invoice with many products', [self::date(), self::draft()],
                fn (TransferInvoicing $s, Collection $r, array $d) => $s->oneInvoicePerLines($r, $d['date'], $d['draft'])),

            self::make('invoicesPerLead', 'Many invoices per lead name', [self::date(), self::draft()],
                fn (TransferInvoicing $s, Collection $r, array $d) => $s->invoicesPerLeadName($r, $d['date'], $d['draft'])),

            self::make('receipts', 'Many receipts with 1 product per line', [self::draft(), self::priceType(), self::getPdf(TransferInvoicing::PDF_AUTO)],
                fn (TransferInvoicing $s, Collection $r, array $d) => $s->receipts($r, $d['draft'], $d['price'] === 'platform', $d['pdf'])),

            self::make('taxiReceipts', 'Taxi many receipts with 1 product per line', [
                self::draft(), self::priceType(), self::getPdf(TransferInvoicing::PDF_AUTO),
                Toggle::make('credit')->label('Issue credit note for the taxi receipt')->default(true),
            ], fn (TransferInvoicing $s, Collection $r, array $d) => $s->receipts($r, $d['draft'], $d['price'] === 'platform', $d['pdf'], taxi: true, creditNote: (bool) $d['credit'])),

            self::make('getInvoicePdf', 'Get Invoice PDF', [self::getPdf(TransferInvoicing::PDF_ALL)],
                fn (TransferInvoicing $s, Collection $r, array $d) => $s->downloadPdfs($r, $d['pdf']), guard: null)
                ->icon(Heroicon::OutlinedDocumentArrowDown),
        ];
    }

    /** "Issue Receipt" on the charges of a transfer (New_Button29). */
    public static function chargeReceipts(): BulkAction
    {
        return self::make('issueReceipt', 'Issue Receipt', [self::draft()],
            fn (TransferInvoicing $s, Collection $r, array $d) => $s->chargeReceipts($r, $d['draft']), table: 'transfer_charges', guard: 'Invoiced');
    }

    /** @param  Closure(TransferInvoicing, Collection, array): InvoiceRun  $work */
    private static function make(string $name, string $label, array $fields, Closure $work, string $table = 'transfers', ?string $guard = 'InvoicedIssued'): BulkAction
    {
        if ($guard) {
            // PHPRunner would invoice the same transfer twice; here that needs a deliberate tick.
            $fields[] = Toggle::make('again')->label('Also the ones already invoiced')->default(false);
        }

        return BulkAction::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedDocumentText)
            ->modalHeading($label)
            ->modalSubmitActionLabel('Send to Elorus')
            ->schema($fields)
            ->visible(fn (): bool => (bool) auth()->user()?->may($table, 'edit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) use ($work, $guard): void {
                $skipped = 0;
                if ($guard && ! ($data['again'] ?? false)) {
                    $done = $records->filter(fn ($record): bool => in_array((string) $record->{$guard}, ['Ναι', '1'], true));
                    $skipped = $done->count();
                    $records = $records->diff($done);
                }

                $run = $records->isEmpty() ? new InvoiceRun : $work(app(TransferInvoicing::class), $records, $data);
                if ($skipped > 0) {
                    $run->failed[] = "{$skipped} already invoiced, skipped";
                }

                Notification::make()
                    ->title(match (true) {
                        $run->failed === [] => 'Sent to Elorus',
                        $run->created === [] && $run->pdfs === 0 => 'Nothing was created in Elorus',
                        default => 'Partly done',
                    })
                    ->body($run->summary() ?: 'Nothing to do.')
                    ->status($run->failed === [] ? 'success' : ($run->created === [] && $run->pdfs === 0 ? 'danger' : 'warning'))
                    ->persistent($run->failed !== [])
                    ->send();
            });
    }

    private static function date(): DatePicker
    {
        return DatePicker::make('date')->label('Invoice date')->default(now())->required();
    }

    private static function draft(): Toggle
    {
        return Toggle::make('draft')
            ->label('Draft (check and issue it in Elorus)')
            ->helperText('Off: the document is issued straight away and sent to myDATA.')
            ->default(true);
    }

    private static function priceType(): Select
    {
        return Select::make('price')->label('Price type')
            ->options(['sale' => 'Sale price', 'platform' => 'Platform price'])
            ->helperText('Platform price applies to platform bookings only.')
            ->default('sale')->required()->selectablePlaceholder(false);
    }

    private static function getPdf(string $default): Select
    {
        return Select::make('pdf')->label('Get invoice?')
            ->options([
                TransferInvoicing::PDF_ALL => 'All',
                TransferInvoicing::PDF_AUTO => 'Auto (platform tours / long transfers)',
                TransferInvoicing::PDF_NO => 'No',
            ])
            ->default($default)->required()->selectablePlaceholder(false);
    }
}
