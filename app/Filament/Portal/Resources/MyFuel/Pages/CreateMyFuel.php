<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateMyFuel extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = MyFuelResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Νέα απόδειξη καυσίμου';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The driver is the one linked to this login (drivers.UserID).
        $data['DriverID'] = DB::table('drivers')->where('UserID', auth()->id())->value('DriverID');

        return $this->mergeLegacyFiles($data, null);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
