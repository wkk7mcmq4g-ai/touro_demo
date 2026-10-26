<?php

namespace App\Filament\Resources\Counterparties\Actions;

use App\Services\Elorus\ElorusClient;
use App\Services\Elorus\ElorusException;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Προσθήκη στο Elorus" (New_Button14): create Elorus contacts for the ticked partners. */
class AddToElorus
{
    public static function make(): BulkAction
    {
        return BulkAction::make('addToElorus')
            ->label('Προσθήκη στο Elorus')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalDescription('Creates an Elorus contact for each ticked partner and stores its Elorus ID.')
            ->schema([Toggle::make('again')->label('Also the ones already in Elorus')->default(false)])
            ->modalSubmitActionLabel('Send to Elorus')
            ->visible(fn (): bool => config('elorus.buttons') && (bool) auth()->user()?->may('counterparties', 'edit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                if (! config('elorus.enabled')) {
                    Notification::make()->danger()->title('Elorus is switched off on this copy of the app (ELORUS_ENABLED=false in .env).')->send();

                    return;
                }

                $done = [];
                $failed = [];
                $client = app(ElorusClient::class);

                foreach ($records as $partner) {
                    if (! ($data['again'] ?? false) && ! in_array((string) $partner->ElorusID, ['', 'NotRegistered'], true)) {
                        $failed[] = "{$partner->CounterShortName}: already in Elorus";

                        continue;
                    }
                    try {
                        $contact = $client->createContact(['first_name' => $partner->CounterShortName, 'last_name' => $partner->CounterShortName]);
                        DB::table('counterparties')->where('CID', $partner->CID)->update(['ElorusID' => $contact['id']]);
                        $done[] = $partner->CounterShortName;
                    } catch (ElorusException $e) {
                        $failed[] = "{$partner->CounterShortName}: {$e->getMessage()}";
                    }
                }

                Notification::make()
                    ->title($failed === [] ? 'Added to Elorus' : ($done === [] ? 'Nothing was added' : 'Partly done'))
                    ->body(implode('. ', array_filter([$done ? count($done).' added: '.implode(', ', $done) : null, ...$failed])))
                    ->status($failed === [] ? 'success' : ($done === [] ? 'danger' : 'warning'))
                    ->persistent($failed !== [])
                    ->send();
            });
    }
}
