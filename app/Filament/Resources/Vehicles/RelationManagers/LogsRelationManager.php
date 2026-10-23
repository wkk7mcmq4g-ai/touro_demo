<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Filament\Resources\VehicleLogs\VehicleLogResource;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** PHPRunner detail `vehicles_log` ("Log book" tab). */
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Log book';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), 'vehicles_log', 'view');
    }

    public function form(Schema $schema): Schema
    {
        return VehicleLogResource::form($schema, withVehicle: false);
    }

    public function table(Table $table): Table
    {
        return VehicleLogResource::table($table, withVehicle: false)
            ->headerActions([CreateAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_log', 'create'))])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_log', 'update')),
                DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_log', 'delete')),
            ]);
    }
}
