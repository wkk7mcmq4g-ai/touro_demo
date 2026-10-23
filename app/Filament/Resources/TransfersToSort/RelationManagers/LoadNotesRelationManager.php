<?php

namespace App\Filament\Resources\TransfersToSort\RelationManagers;

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

/** PHPRunner detail `transfer_notes_load`: notes on a booking to sort. */
class LoadNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Σημειώσεις';

    private const TABLE = 'transfer_notes_load';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([Textarea::make('Note')->rows(4)->required()->maxLength(5000)]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('CreatedTimestamp')->label('When')->dateTime('d/m/Y H:i'),
                TextColumn::make('CreatedBy')->label('By'),
                TextColumn::make('Note')->wrap(),
            ])
            ->headerActions([CreateAction::make()->label('Add note')->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create'))])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'update')),
                DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete')),
            ]);
    }
}
