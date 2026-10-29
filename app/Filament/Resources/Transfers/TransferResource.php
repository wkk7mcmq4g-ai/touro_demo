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
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /**
     * Quick find (search box at the top of the side menu, Ctrl/⌘+K from any screen): a transfer by
     * its number, the passenger's name (lead or list), the phone (digits only, as PHPRunner's
     * PhoneClean) or the supplier's booking code. The exact number first, then the coming ones by
     * date, then past ones (latest first). Opens the transfer.
     */
    public static function getGlobalSearchResults(string $search): Collection
    {
        $search = trim($search);
        if (mb_strlen($search) < 2 && ! ctype_digit($search)) {
            return collect();
        }

        $digits = preg_replace('/\D/', '', $search);
        $like = '%'.$search.'%';

        return static::getEloquentQuery()
            ->where(function (Builder $q) use ($search, $digits, $like): void {
                if (ctype_digit($search)) {
                    $q->orWhere('TransferID', (int) $search);
                }
                $q->orWhere('LeadName', 'like', $like)
                    ->orWhere('PassengerName', 'like', $like)
                    ->orWhere('SupplierCode', 'like', $like);
                if (strlen($digits) >= 6) {
                    $q->orWhereRaw("REGEXP_REPLACE(`LeadPhone`, '[^0-9]', '') LIKE ?", ['%'.$digits.'%']);
                }
            })
            ->orderByRaw('`TransferID` = ? DESC', [ctype_digit($search) ? (int) $search : 0])
            ->orderByRaw('`TransferDate` >= CURRENT_DATE DESC')
            ->orderByRaw('IF(`TransferDate` >= CURRENT_DATE, `TransferDate`, NULL) ASC')
            ->orderByDesc('TransferDate')
            ->limit(8)
            ->get(['TransferID', 'TransferDate', 'TransferTime', 'TransferType', 'From', 'To', 'LeadName', 'Status', 'cID_Customer'])
            ->map(fn (Transfer $t): GlobalSearchResult => new GlobalSearchResult(
                title: '#'.$t->TransferID.($t->LeadName ? ' · '.$t->LeadName : ''),
                url: static::getUrl('edit', ['record' => $t]),
                details: array_filter([
                    'Πότε' => trim(($t->TransferDate?->format('d/m/Y') ?? '').' '.$t->TransferTime),
                    'Διαδρομή' => $t->TransferType === 'Tour' ? (string) $t->To : trim($t->From.' → '.$t->To, ' →'),
                    'Πελάτης' => (string) ($t->customer?->SupplierName ?? ''),
                    'Status' => (string) $t->Status,
                ]),
            ));
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
