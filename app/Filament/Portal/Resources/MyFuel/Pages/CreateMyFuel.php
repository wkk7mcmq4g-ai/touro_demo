<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class CreateMyFuel extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = MyFuelResource::class;

    /** Save stays at the bottom of the phone screen. */
    public static bool $formActionsAreSticky = true;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Νέα απόδειξη καυσίμου';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The driver is the one linked to this login (drivers.UserID).
        $data['DriverID'] = DB::table('drivers')->where('UserID', auth()->id())->value('DriverID');

        return $this->mergeLegacyFiles($data, null);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Αποθήκευση')->icon(Heroicon::OutlinedCheck);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Άκυρο');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Η απόδειξη καταχωρήθηκε';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
