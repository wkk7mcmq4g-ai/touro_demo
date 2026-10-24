<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMyFuel extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = MyFuelResource::class;

    /** Save stays at the bottom of the phone screen. */
    public static bool $formActionsAreSticky = true;

    protected static ?string $title = 'Απόδειξη καυσίμου';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Αποθήκευση')->icon(Heroicon::OutlinedCheck);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Άκυρο');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Αποθηκεύτηκε';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
