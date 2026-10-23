<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * PHPRunner detail `transfers_comms_view`: the communication log of a transfer
 * (who said what to the customer, driver or partner, and when).
 */
class CommsRelationManager extends RelationManager
{
    protected static string $relationship = 'comms';

    protected static ?string $title = 'Communications';

    private const TABLE = 'transfers_comms_view';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('Notes')->label('Message')->rows(4)->required()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Notes')
            ->defaultSort('CreatedDate', 'desc')
            ->columns([
                TextColumn::make('CreatedDate')->label('When')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('CreatedBy')->label('By'),
                TextColumn::make('Notes')->label('Message')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create')),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'update')),
                DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete')),
            ]);
    }
}
