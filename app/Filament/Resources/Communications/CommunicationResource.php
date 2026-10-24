<?php

namespace App\Filament\Resources\Communications;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Communications\Pages\CreateCommunication;
use App\Filament\Resources\Communications\Pages\EditCommunication;
use App\Filament\Resources\Communications\Pages\ListCommunications;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\TransfersComm;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** PHPRunner transfers_comms_view: the communication log of all transfers. */
class CommunicationResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers_comms_view';

    protected static ?string $model = TransfersComm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Communications';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Communications';

    protected static ?string $modelLabel = 'message';

    protected static ?string $slug = 'communications';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // PHPRunner's lookup: "ID  date  time  From --- To".
            Select::make('TransferID')->label('Transfer')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search): array => self::transferOptions(DB::table('transfers')
                    ->where(fn ($q) => $q->where('TransferID', 'like', "{$search}%")->orWhere('LeadName', 'like', "%{$search}%")->orWhere('SupplierCode', 'like', "%{$search}%"))
                    ->orderByDesc('TransferDate')->limit(50)))
                ->getOptionLabelUsing(fn ($value): ?string => self::transferOptions(DB::table('transfers')->where('TransferID', $value))[$value] ?? null),
            Textarea::make('Notes')->label('Message')->rows(4)->required(),
        ]);
    }

    private static function transferOptions($query): array
    {
        return $query->get(['TransferID', 'TransferDate', 'TransferTime', 'From', 'To'])
            ->mapWithKeys(fn ($t) => [$t->TransferID => "{$t->TransferID}  ".date('d/m/Y', strtotime((string) $t->TransferDate))."  {$t->TransferTime}  {$t->From}  ---  {$t->To}"])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('CreatedDate', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('transfer:TransferID,TransferDate,TransferTime,From,To'))
            ->columns([
                TextColumn::make('CreatedDate')->label('When')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('CreatedBy')->label('By')->searchable(),
                TextColumn::make('TransferID')->label('Transfer')
                    ->formatStateUsing(fn (TransfersComm $record): string => trim($record->TransferID.'  '.$record->transfer?->TransferDate?->format('d/m').'  '.$record->transfer?->From.' → '.$record->transfer?->To))
                    ->url(fn (TransfersComm $record): string => TransferResource::getUrl('edit', ['record' => $record->TransferID]))
                    ->searchable(),
                TextColumn::make('Notes')->label('Message')->wrap()->searchable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunications::route('/'),
            'create' => CreateCommunication::route('/create'),
            'edit' => EditCommunication::route('/{record}/edit'),
        ];
    }
}
