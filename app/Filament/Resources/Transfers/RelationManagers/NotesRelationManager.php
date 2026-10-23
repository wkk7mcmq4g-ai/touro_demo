<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Models\TransferNote;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * PHPRunner detail table `transfer_notes` (operational / financial issues).
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes & issues';

    private const TABLE = 'transfer_notes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Textarea::make('Note')
                    ->required()
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Select::make('Category')
                    ->options(TransferNote::CATEGORIES)
                    ->helperText('Admins are notified about every new note.'),
                DatePicker::make('NoteDate')
                    ->label('Date')
                    ->default(now()),
                Select::make('Status')
                    ->options(['Closed' => 'Closed'])
                    ->placeholder('Open'),
                Toggle::make('Alert')
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('Note')
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('NoteDate')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('Category')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'Financial' ? 'warning' : 'info'),
                TextColumn::make('Note')->wrap()->limit(200),
                TextColumn::make('Status')
                    ->badge()
                    ->getStateUsing(fn (TransferNote $record): string => $record->Status ?: 'Open')
                    ->color(fn (string $state): string => $state === 'Closed' ? 'success' : 'danger'),
                IconColumn::make('Alert')->boolean()->getStateUsing(fn (TransferNote $record): bool => (bool) $record->Alert),
                TextColumn::make('CreatedBy')->label('By')
                    ->description(fn (TransferNote $record): ?string => $record->CreatedTimestamp?->format('d/m/Y H:i')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New note')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'update'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
                DeleteAction::make()
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
            ]);
    }
}
