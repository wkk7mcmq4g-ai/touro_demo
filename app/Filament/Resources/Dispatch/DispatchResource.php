<?php

namespace App\Filament\Resources\Dispatch;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Dispatch\Pages\ListDispatch;
use App\Mail\GuideAssignment;
use App\Mail\OlpEntryRequest;
use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * PHPRunner's v_transfers list, admin side: the next five days' assigned jobs per driver,
 * with the vouchers, the OLP port request, the tour guide assignment and the driver text.
 */
class DispatchResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'v_transfers';

    protected static ?string $model = VTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Dispatch';

    protected static ?string $modelLabel = 'job';

    protected static ?string $pluralModelLabel = 'Dispatch';

    protected static ?string $slug = 'dispatch';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderBy('Fullname')->orderBy('TransferDate')->orderBy('TransferTime_sort'))
            ->groups([
                Group::make('Fullname')->label('Driver')
                    ->getDescriptionFromRecordUsing(fn (VTransfer $record): string => (string) $record->VehicleName),
                Group::make('TransferDate')->label('Date')->date(),
            ])
            ->defaultGroup('Fullname')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('row_num')->label('#'),
                TextColumn::make('TransferDate')->label('Date')->date('D d/m'),
                TextColumn::make('TransferTime')->label('Ώρα παραλαβής')->weight('bold'),
                TextColumn::make('Pax')->label('Άτομα'),
                TextColumn::make('TransferType')->label('Κατηγορία'),
                TextColumn::make('From')->label('Σημείο Παραλαβής')->wrap(),
                TextColumn::make('Flight_Ship_Num_From')->label('Πληροφορίες')->wrap(),
                TextColumn::make('To')->label('Σημείο Αποβίβασης')->wrap(),
                TextColumn::make('LeadName')->label('Όνομα επιβάτη')->wrap(),
                TextColumn::make('LeadPhone')->label('Τηλέφωνο επιβάτη'),
                TextColumn::make('Notes')->label('Παρατηρήσεις')->wrap()->limit(120),
                TextColumn::make('Assistant')->label('Συνοδός / Ξεναγός')->wrap(),
                TextColumn::make('Tickets_Flag')->label('Tickets')->badge()->color('warning')
                    ->getStateUsing(fn (VTransfer $record): ?string => $record->Tickets_Flag === 'No' ? null : $record->Tickets_Flag),
                TextColumn::make('VehicleName')->label('Vehicle')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('TransferID')->label('ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('voucher')
                    ->label('Voucher')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->visible(fn (VTransfer $record): bool => Vouchers::forLicence($record->VehicleLicence) !== null)
                    ->url(fn (VTransfer $record): string => route('vouchers.show', [Vouchers::forLicence($record->VehicleLicence), $record->TransferID]))
                    ->openUrlInNewTab(),
                ActionGroup::make([
                    self::olpAction(),
                    self::guideAction(),
                    self::smsAction(),
                    Action::make('tour')
                        ->label('Tour description')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->visible(fn (VTransfer $record): bool => $record->TransferType === 'Tour')
                        ->modalHeading(fn (VTransfer $record): string => (string) $record->To)
                        ->modalContent(fn (VTransfer $record) => str(e((string) $record->TourDescription))->replace("\n", '<br>')->toHtmlString())
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),
                ]),
            ]);
    }

    /** "ΟΛΠ": the port entry request, as PDF or by email (New_Button66). */
    private static function olpAction(): Action
    {
        return Action::make('olp')
            ->label('ΟΛΠ')
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn (VTransfer $record): bool => $record->needsOlpRequest())
            ->modalHeading('Email the OLP entry request')
            ->modalDescription(fn (VTransfer $record) => str('<a style="text-decoration:underline" target="_blank" href="'.e(route('vouchers.show', ['VoucherOLP', $record->TransferID])).'">Open the request (PDF)</a>')->toHtmlString())
            ->schema([
                TextInput::make('to')->label('ΟΛΠ 1')->email()->required()->default(config('dispatch.olp.to')),
                TextInput::make('cc')->label('ΟΛΠ 2')->email()->default(config('dispatch.olp.cc')),
                TextInput::make('reply_to')->label('Απάντηση σε')->email()->default(config('dispatch.reply_to')),
                TextInput::make('bcc')->label('Κοινοποίηση σε')->email()->default(config('dispatch.olp.bcc'))
                    ->helperText(fn (VTransfer $record): ?string => filled($record->Email) ? "The driver ({$record->Email}) also gets a copy." : null),
            ])
            ->modalSubmitActionLabel('Send')
            ->action(function (VTransfer $record, array $data): void {
                $bcc = array_values(array_filter([$data['bcc'] ?? null, $record->Email]));
                self::send(fn () => Mail::to($data['to'])
                    ->cc(array_filter([$data['cc'] ?? null]))
                    ->bcc($bcc)
                    ->send(new OlpEntryRequest($record, $data['reply_to'] ?? null)));
            });
    }

    /** "Ξεναγός": the tour guide assignment by email (Send_Assistant_Email). */
    private static function guideAction(): Action
    {
        return Action::make('guide')
            ->label('Ξεναγός')
            ->icon(Heroicon::OutlinedUserCircle)
            ->visible(fn (VTransfer $record): bool => $record->hasAssistant())
            ->modalHeading('Send the tour guide assignment')
            ->modalDescription(fn (VTransfer $record) => str(
                'To: '.e($record->AssistantEmail ?: '(no email for this guide)').' · <a style="text-decoration:underline" target="_blank" href="'
                .e(route('vouchers.show', ['Voucher_assistant', $record->TransferID])).'">Open the assignment (PDF)</a>'
            )->toHtmlString())
            ->modalSubmitActionLabel('Send')
            ->action(function (VTransfer $record): void {
                if (blank($record->AssistantEmail)) {
                    Notification::make()->danger()->title('This guide has no email address.')->send();

                    return;
                }
                self::send(fn () => Mail::to($record->AssistantEmail)->send(new GuideAssignment($record)));
            });
    }

    /** "SMS": the job as text for the driver (view_SMS page). */
    private static function smsAction(): Action
    {
        return Action::make('sms')
            ->label('SMS / WhatsApp text')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalHeading('Text for the driver')
            ->schema(fn (VTransfer $record): array => [
                Textarea::make('text')->hiddenLabel()->rows(14)->default($record->smsText())->readOnly()->extraInputAttributes(['onclick' => 'this.select()']),
                Text::make(fn () => filled($record->DriverPhone)
                    ? str('<a style="text-decoration:underline" target="_blank" rel="noopener" href="https://wa.me/'.preg_replace('/\D/', '', (string) $record->DriverPhone).'?text='.rawurlencode($record->smsText()).'">Open in WhatsApp</a>')->toHtmlString()
                    : 'No phone for this driver.'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private static function send(callable $send): void
    {
        try {
            $send();
            Notification::make()->success()->title('Επιτυχής αποστολή')->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('The email could not be sent')->body($e->getMessage())->persistent()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispatch::route('/'),
        ];
    }
}
