<?php

namespace App\Filament\Resources\Counterparties;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Counterparties\Pages\CreateCounterparty;
use App\Filament\Resources\Counterparties\Pages\EditCounterparty;
use App\Filament\Resources\Counterparties\Pages\ListCounterparties;
use App\Filament\Resources\Counterparties\RelationManagers\PartnerFilesRelationManager;
use App\Filament\Resources\Counterparties\Schemas\CounterpartyForm;
use App\Filament\Resources\Counterparties\Tables\CounterpartiesTable;
use App\Models\Counterparty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * PHPRunner "Συνεργάτες" (counterparties): customers/suppliers, coach partners and
 * tour guides in one table, told apart by the IsSupplier / IsCoach / IsAssistant flags.
 */
class CounterpartyResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'counterparties';

    protected static ?string $model = Counterparty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Συνεργάτες';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Συνεργάτες';

    protected static ?string $modelLabel = 'partner';

    protected static ?string $recordTitleAttribute = 'CounterShortName';

    public static function form(Schema $schema): Schema
    {
        return CounterpartyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CounterpartiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PartnerFilesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCounterparties::route('/'),
            'create' => CreateCounterparty::route('/create'),
            'edit' => EditCounterparty::route('/{record}/edit'),
        ];
    }
}
