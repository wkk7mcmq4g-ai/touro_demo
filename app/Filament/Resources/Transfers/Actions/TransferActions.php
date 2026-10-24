<?php

namespace App\Filament\Resources\Transfers\Actions;

use App\Enums\UserGroup;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use App\Support\Lookups;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The PHPRunner custom buttons of the transfers list (spec/buttons/New_Button*.txt).
 * They write with plain UPDATE/INSERT statements, as the PHPRunner buttons did, so the
 * database triggers behave exactly as before.
 */
class TransferActions
{
    /** Row button "copy" (New_Button65): the Copy_Transfer function, then open the copy. */
    public static function copy(): Action
    {
        return Action::make('copy')
            ->label('Copy')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Copy this transfer?')
            ->modalDescription('A new transfer is created with the same trip, passenger and notes, and opened for editing.')
            ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'add'))
            ->action(function (Transfer $record, Action $action): void {
                $newId = DB::selectOne('SELECT `Copy_Transfer`(?) AS newid', [$record->getKey()])->newid;

                $action->redirect(TransferResource::getUrl('edit', ['record' => $newId]));
            });
    }

    /**
     * Row button "SMS" (PHPRunner's sms field, Admin only): the job as a text for the driver, to copy
     * or open in WhatsApp with the driver's number.
     */
    public static function sms(): Action
    {
        return Action::make('sms')
            ->label('SMS')
            ->tooltip('SMS / WhatsApp για τον οδηγό')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->group() === UserGroup::Admin)
            ->modalHeading('Κείμενο για τον οδηγό')
            ->modalWidth(Width::Large)
            ->fillForm(fn (Transfer $record): array => ['text' => $record->smsForDriver()['text']])
            ->schema(fn (Transfer $record): array => [
                Textarea::make('text')->hiddenLabel()->rows(13)->readOnly()
                    ->extraInputAttributes(['onclick' => 'this.select()', 'class' => 'myat-sms-text']),
                Text::make(function () use ($record) {
                    $sms = $record->smsForDriver();
                    $digits = preg_replace('/\D/', '', $sms['phone']);

                    return filled($digits)
                        ? str('<a class="myat-sms-wa" target="_blank" rel="noopener" href="https://wa.me/'.(strlen($digits) === 10 ? '30'.$digits : $digits).'?text='.rawurlencode($sms['text']).'">Άνοιγμα στο WhatsApp</a>')->toHtmlString()
                        : 'Δεν υπάρχει τηλέφωνο οδηγού για αυτή την ημέρα.';
                }),
            ])
            ->modalSubmitAction(false)
            ->extraModalFooterActions([
                Action::make('copySms')->label('Αντιγραφή')->icon(Heroicon::OutlinedClipboardDocument)
                    ->alpineClickHandler("navigator.clipboard.writeText(document.querySelector('.myat-sms-text')?.value ?? ''); \$tooltip('Αντιγράφηκε', { timeout: 1500 })"),
            ])
            ->modalCancelActionLabel('Κλείσιμο');
    }

    /** "Κλείσιμο Πωλήσεων" (New_Button32): received = sale price on the selected transfers. */
    public static function closeSales(): BulkAction
    {
        return BulkAction::make('closeSales')
            ->label('Κλείσιμο Πωλήσεων')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading('Κλείσιμο Πωλήσεων')
            ->modalDescription('Received is set to the sale price on the selected transfers.')
            ->schema([self::usernameField()])
            ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'edit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                DB::table('transfers')
                    ->whereIn('TransferID', $records->modelKeys())
                    ->update(['PriceRcvd' => DB::raw('SalePrice')]);

                self::updated($records->count());
            });
    }

    /** "Κλείσιμο Αγορών" (New_Button33): paid = purchase price, purchase invoice received. */
    public static function closePurchases(): BulkAction
    {
        return BulkAction::make('closePurchases')
            ->label('Κλείσιμο Αγορών')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Κλείσιμο Αγορών')
            ->modalDescription('Paid is set to the purchase price and the purchase invoice is marked as received.')
            ->schema([
                self::usernameField(),
                Textarea::make('invnum')->label('Αριθμός Παραστατικού Αγορών')->rows(2),
            ])
            ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'edit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                DB::table('transfers')
                    ->whereIn('TransferID', $records->modelKeys())
                    ->update([
                        'PricePaid' => DB::raw('PurchasePrice'),
                        'PurchaseInvoiceNum' => (string) ($data['invnum'] ?? ''),
                        'InvoicedRcvd' => 'Έχω λάβει',
                    ]);

                self::updated($records->count());
            });
    }

    /** "Εισερχόμενο έμβασμα" (New_Button35): records a payment received from a customer. */
    public static function incomingPayment(): Action
    {
        return Action::make('incomingPayment')
            ->label('Εισερχόμενο έμβασμα')
            ->icon(Heroicon::OutlinedBanknotes)
            ->modalHeading('New payment')
            ->schema([
                Select::make('customer')->label('Supplier')->options(fn (): array => Lookups::customers())->searchable()->required(),
                TextInput::make('amount')->label('Amount')->numeric()->prefix('€')->required(),
                DatePicker::make('date')->label('Payment date')->default(now())->required(),
            ])
            ->visible(fn (): bool => (bool) auth()->user()?->may('PaymentsReceived', 'add'))
            ->action(function (array $data): void {
                DB::table('PaymentsReceived')->insert([
                    'CID_Customer' => $data['customer'],
                    'PaymentDate' => $data['date'],
                    'PaymentAmount' => $data['amount'],
                    'CreatedBy' => auth()->user()->username,
                    'CreatedDate' => now(),
                ]);

                Notification::make()->success()->title('Payment recorded')->send();
            });
    }

    /** PHPRunner asked for the user's own username before these bulk updates. */
    private static function usernameField(): TextInput
    {
        return TextInput::make('usr')
            ->label('Εισάγετε το όνομα χρήστη σας για να προχωρήσετε')
            ->required()
            ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                if ($value !== auth()->user()?->username) {
                    $fail('Wrong Username');
                }
            }]);
    }

    private static function updated(int $count): void
    {
        Notification::make()->success()->title("{$count} records updated")->send();
    }
}
