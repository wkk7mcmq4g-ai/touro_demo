<?php

namespace App\Filament\Resources\Transfers\Actions;

use App\Enums\UserGroup;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\TransferCellEdit;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** Arrival ⇄ Departure for «Επιστροφή». */
    public const RETURN_TYPES = ['Arrival' => 'Departure', 'Departure' => 'Arrival'];

    /**
     * «Επιστροφή»: the way back of an Arrival (or Departure) in one step. Copy_Transfer makes the copy
     * (same customer, passenger, lead, pax, notes), then the copy gets the other type, From/To swapped,
     * the date/time/flight given here, Status Pending and no vehicle, guide or driver notes. Saved
     * through the model, so the price procedure and triggers run as for a new transfer.
     */
    public static function returnTrip(): Action
    {
        return Action::make('returnTrip')
            ->label('Επιστροφή')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (?Transfer $record): bool => $record !== null
                && array_key_exists((string) $record->TransferType, self::RETURN_TYPES)
                && (bool) auth()->user()?->may('transfers', 'add'))
            ->modalHeading(fn (Transfer $record): string => self::RETURN_TYPES[$record->TransferType].' για το #'.$record->getKey())
            ->modalDescription(fn (Transfer $record): string => trim((string) $record->To).' → '.trim((string) $record->From).' · '.($record->LeadName ?: $record->PassengerName))
            ->modalSubmitActionLabel('Δημιουργία')
            ->modalWidth(Width::Medium)
            ->schema([
                DatePicker::make('date')->label('Ημερομηνία')->required()
                    ->minDate(fn (Transfer $record) => $record->TransferType === 'Arrival' ? $record->TransferDate : null)
                    ->maxDate(fn (Transfer $record) => $record->TransferType === 'Departure' ? $record->TransferDate : null),
                TextInput::make('time')->label('Ώρα')->placeholder('14:30 ή TBD')->maxLength(50)->required(),
                TextInput::make('flight')->label('Πτήση / Πλοίο')->maxLength(150),
            ])
            ->action(function (Transfer $record, array $data, Action $action): void {
                $newId = (int) DB::selectOne('SELECT `Copy_Transfer`(?) AS newid', [$record->getKey()])->newid;

                $copy = Transfer::query()->findOrFail($newId);
                $copy->fill([
                    'TransferType' => self::RETURN_TYPES[$record->TransferType],
                    'From' => $record->To,
                    'To' => $record->From,
                    'TransferDate' => $data['date'],
                    'TransferTime' => trim((string) $data['time']),
                    'Flight_Ship_Num_From' => trim((string) ($data['flight'] ?? '')),
                    'Status' => 'Pending',
                    'CoachCompanyID' => '',
                    'DriverNotes' => '',
                    'Assistant' => '',
                    'Assistant_Price' => null,
                    'cID_Assistant' => null,
                    'AssistantStatus' => '',
                ])->save();

                Notification::make()->success()->title('Δημιουργήθηκε η επιστροφή #'.$newId)->send();
                $action->redirect(TransferResource::getUrl('edit', ['record' => $newId]));
            });
    }

    /**
     * Row button "SMS" (PHPRunner's sms field, Admin only): the job as a text for the driver, to copy
     * or open in WhatsApp with the driver's number.
     */
    public static function sms(): Action
    {
        return self::smsWindow(
            Action::make('sms')->tooltip('SMS / WhatsApp για τον οδηγό')
                ->visible(fn (): bool => auth()->user()?->group() === UserGroup::Admin),
            fn (Transfer $record): array => $record->smsForDriver(),
        );
    }

    /**
     * The SMS window shared by Transfers and Αποστολή Transfers: the text ready to copy, a copy
     * button and a WhatsApp link to the phone. $sms(record) returns ['text' => ..., 'phone' => ...].
     *
     * @param  Closure(Model): array{text: string, phone: string}  $sms
     * @param  list<Action>  $footer  more buttons next to Αντιγραφή
     */
    public static function smsWindow(Action $action, Closure $sms, string|Closure $heading = 'Κείμενο για τον οδηγό', array $footer = []): Action
    {
        return $action
            ->label('SMS')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->modalHeading($heading)
            ->modalWidth(Width::Large)
            ->fillForm(fn (Model $record): array => ['text' => $sms($record)['text']])
            ->schema(fn (Model $record): array => [
                Textarea::make('text')->hiddenLabel()->rows(13)->readOnly()
                    ->extraInputAttributes(['onclick' => 'this.select()', 'class' => 'myat-sms-text']),
                Text::make(function () use ($record, $sms) {
                    $message = $sms($record);
                    $link = self::whatsAppUrl($message['phone'], $message['text']);

                    return $link
                        ? str('<a class="myat-sms-wa" target="_blank" rel="noopener" href="'.e($link).'">Άνοιγμα στο WhatsApp</a>')->toHtmlString()
                        : 'Δεν υπάρχει τηλέφωνο οδηγού για αυτή την ημέρα.';
                }),
            ])
            ->modalSubmitAction(false)
            ->extraModalFooterActions([
                Action::make('copySms')->label('Αντιγραφή')->icon(Heroicon::OutlinedClipboardDocument)
                    ->alpineClickHandler("navigator.clipboard.writeText(document.querySelector('.myat-sms-text')?.value ?? ''); \$tooltip('Αντιγράφηκε', { timeout: 1500 })"),
                ...$footer,
            ])
            ->modalCancelActionLabel('Κλείσιμο');
    }

    /** wa.me link for a Greek or international number (10 digits = Greek, +30 added), or null without one. */
    public static function whatsAppUrl(?string $phone, string $text): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.(strlen($digits) === 10 ? '30'.$digits : $digits).'?text='.rawurlencode($text);
    }

    /** "Κλείσιμο Πωλήσεων" (New_Button32): received = sale price on the selected transfers. */
    /**
     * Change one field on all ticked transfers (e.g. a group of 6 jobs on the same coach). Each one is
     * saved through the model with the quick-edit checks (App\Support\TransferCellEdit), so the
     * triggers, price procedure and own-vehicle rule run per transfer as on the form.
     */
    /**
     * «Αλλαγή»: one field, one value, on all ticked transfers. Pick the field (TransferCellEdit::bulkFields:
     * operational ones for everyone who edits transfers, money ones only for those who see the money),
     * then the value. Each transfer is saved on its own with the form's checks; refusals are listed.
     */
    public static function bulkChange(): BulkAction
    {
        $fields = fn (): array => TransferCellEdit::bulkFields();
        $is = fn (string $field): Closure => fn (Get $get): bool => $get('field') === $field;

        return BulkAction::make('bulkChange')
            ->label('Αλλαγή')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->button()
            ->color('gray')
            ->modalHeading(fn (Collection $records): string => 'Αλλαγή σε '.$records->count().' transfers')
            ->modalDescription('Ένα πεδίο, ίδια τιμή σε όλα. Ισχύουν οι κανόνες της φόρμας.')
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Αλλαγή σε όλα')
            // Built when the window opens (the lists are read then, not on every page load).
            ->schema(function () use ($fields, $is): array {
                $inputs = [];
                foreach (TransferCellEdit::bulkFields() as $name => $f) {
                    $id = 'v_'.$name;
                    $input = match ($f['kind']) {
                        'list' => Select::make($id)->options($f['options'])->searchable(count($f['options']) > 8)
                            ->placeholder($f['empty'] ?? 'Διαλέξτε…')->required(! isset($f['empty'])),
                        'date' => DatePicker::make($id)->required(),
                        'money' => TextInput::make($id)->numeric()->minValue(0)->prefix('€')->required(),
                        'textarea' => Textarea::make($id)->rows(4)->maxLength($f['max']),
                        default => TextInput::make($id)->maxLength($f['max'])->placeholder($f['placeholder'] ?? null)
                            ->datalist($f['suggest'] ?? null)->autocomplete(false)->required($name === 'TransferTime'),
                    };
                    $inputs[] = $input->label($f['label'])->helperText($f['hint'] ?? null)->visible($is($name));
                }

                return [
                    Select::make('field')->label('Πεδίο')->required()->live()
                        ->options(function () use ($fields): array {
                            $grouped = [];
                            foreach ($fields() as $name => $f) {
                                $grouped[$f['group']][$name] = $f['label'];
                            }

                            return $grouped;
                        }),
                    ...$inputs,
                ];
            })
            ->visible(fn (): bool => TransferCellEdit::allowed())
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) use ($fields): void {
                $field = (string) $data['field'];
                $f = $fields()[$field] ?? abort(422);
                $value = (string) ($data['v_'.$field] ?? '');
                $refused = [];
                foreach ($records as $record) {
                    if (($error = TransferCellEdit::save((int) $record->getKey(), $field, $value)) !== null) {
                        $refused[] = '#'.$record->getKey().': '.$error;
                    }
                }
                $done = $records->count() - count($refused);
                $shown = match (true) {
                    $value === '' => '—',
                    $f['kind'] === 'date' => Carbon::parse($value)->format('d/m/Y'),
                    default => Str::limit(str_replace("\n", ' ', $value), 40),
                };

                Notification::make()
                    ->{$refused === [] ? 'success' : 'warning'}()
                    ->title(Str::before($f['label'], ' (').': '.$shown.' σε '.$done.' transfers')
                    ->body($refused === [] ? null : implode("\n", $refused))
                    ->send();
            });
    }

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
            ->visible(fn (): bool => config('transfers.incoming_payment') && (bool) auth()->user()?->may('PaymentsReceived', 'add'))
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
