<?php

namespace App\Filament\Resources\Transfers;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\CommsRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\TaxiFilesRelationManager;
use App\Filament\Resources\Transfers\Schemas\TransferForm;
use App\Filament\Resources\Transfers\Tables\TransfersTable;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransferResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers';

    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'transfer';

    protected static ?string $recordTitleAttribute = 'TransferID';

    public static function getEloquentQuery(): Builder
    {
        // PHPRunner's transfers datasource: WHERE TransferDate >= '2025-01-01'; older ones are in Archive.
        return parent::getEloquentQuery()
            ->where('TransferDate', '>=', Transfer::ARCHIVE_BEFORE)
            ->withoutBlobs()
            ->with('customer:CID,SupplierName');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? "Transfer #{$record->TransferID}" : 'Transfer';
    }

    public static function form(Schema $schema): Schema
    {
        return TransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransfersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            PriceLinesRelationManager::class,
            ChargesRelationManager::class,
            ExpensesRelationManager::class,
            FilesRelationManager::class,
            TaxiFilesRelationManager::class,
            CommsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransfers::route('/'),
            'create' => CreateTransfer::route('/create'),
            'edit' => EditTransfer::route('/{record}/edit'),
        ];
    }
}
