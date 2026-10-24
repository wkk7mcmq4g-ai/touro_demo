<?php

namespace App\Filament\Resources\DailyTransfers;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\DailyTransfers\Pages\ListDailyTransfers;
use App\Mail\DailyTransfers;
use App\Models\DailyRecipient;
use App\Services\Dispatch\TelegramClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * PHPRunner "Αποστολή Transfers" (v_daily_transfers_emails, opened from the dispatch list):
 * every driver and tour guide with jobs on a day, to e-mail or Telegram them the day's
 * transfers and to mark who has read them.
 */
class DailyTransfersResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'v_daily_transfers_emails';

    protected static ?string $model = DailyRecipient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Αποστολή Transfers';

    protected static ?string $modelLabel = 'recipient';

    protected static ?string $pluralModelLabel = 'Αποστολή Transfers';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'send-transfers';

    public static function getEloquentQuery(): Builder
    {
        return DailyRecipient::source();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function sendEmails(iterable $recipients, bool $withPassengers): void
    {
        $sent = [];
        $jobs = 0;
        foreach ($recipients as $recipient) {
            if (blank($recipient->Email)) {
                continue;
            }
            $list = $recipient->jobs();
            Mail::to($recipient->Email)->send(new DailyTransfers($recipient, $list, $withPassengers));
            $sent[] = $recipient->Email;
            $jobs += $list->count();
        }

        $sent === []
            ? Notification::make()->warning()->title('Nobody to e-mail: no e-mail address')->send()
            : Notification::make()->success()->title(count($sent).' emails were sent')->body('To: '.implode('; ', $sent).". Total transfers: {$jobs}")->send();
    }

    public static function table(Table $table): Table
    {
        $passengers = Toggle::make('withPassengers')->label('Attach passenger list');

        return $table
            ->defaultSort('ID') // PHPRunner: ORDER BY ID
            ->paginated(false)
            ->emptyStateHeading('No jobs on this day')
            ->columns([
                TextColumn::make('Fullname')->label('Driver / guide')->weight('bold')->searchable(),
                TextColumn::make('Type')->badge()->color(fn (string $state): string => $state === 'Driver' ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'Driver' ? 'Driver' : 'Guide'),
                TextColumn::make('VehicleName')->label('Vehicle')->placeholder('—'),
                TextColumn::make('Transfers')->alignEnd(),
                TextColumn::make('Email')->placeholder('no e-mail')->copyable(),
                IconColumn::make('TelegramID')->label('Telegram')->boolean()->getStateUsing(fn (DailyRecipient $record): bool => (int) $record->TelegramID !== 0),
                TextColumn::make('SendStatus')->label('Status')->badge()->color(fn (string $state): string => $state === 'Read' ? 'success' : 'gray'),
                TextColumn::make('TransferDate')->label('Date')->date('D d/m')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('Type')->options(['Driver' => 'Drivers', 'Assistant' => 'Guides']),
            ])
            ->recordActions([
                Action::make('email')->label('Email')->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (DailyRecipient $record): bool => filled($record->Email))
                    ->modalHeading(fn (DailyRecipient $record): string => 'E-mail '.$record->Fullname)
                    ->schema([$passengers])
                    ->modalSubmitActionLabel('Send')
                    ->action(fn (DailyRecipient $record, array $data) => self::sendEmails([$record], (bool) ($data['withPassengers'] ?? false))),
                Action::make('telegram')->label('Telegram')->icon(Heroicon::OutlinedChatBubbleOvalLeft)->color('info')
                    ->visible(fn (DailyRecipient $record): bool => (int) $record->TelegramID !== 0)
                    ->requiresConfirmation()
                    ->modalHeading(fn (DailyRecipient $record): string => 'Telegram to '.$record->Fullname)
                    ->modalDescription(fn (DailyRecipient $record) => str(e($record->telegramText()))->replace("\n", '<br>')->toHtmlString())
                    ->modalSubmitActionLabel('Send')
                    ->action(function (DailyRecipient $record): void {
                        try {
                            app(TelegramClient::class)->send((int) $record->TelegramID, $record->telegramText(), (int) $record->ID, $record->TransferDate->toDateString());
                            Notification::make()->success()->title('Message sent')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Not sent')->body($e->getMessage())->persistent()->send();
                        }
                    }),
                Action::make('read')->label('Read')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->tooltip('The driver confirmed reading the transfers')
                    ->visible(fn (DailyRecipient $record): bool => $record->SendStatus !== 'Read')
                    ->action(function (DailyRecipient $record): void {
                        $record->markRead();
                        Notification::make()->success()->title('ok')->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('emailSelected')->label('Αποστολή Email')->icon(Heroicon::OutlinedEnvelope)
                    ->schema([$passengers])
                    ->modalSubmitActionLabel('Send')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records, array $data) => self::sendEmails($records, (bool) ($data['withPassengers'] ?? false))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDailyTransfers::route('/')];
    }
}
