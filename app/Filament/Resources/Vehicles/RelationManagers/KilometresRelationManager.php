<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** PHPRunner detail `vehicles_klm` (km readings). */
class KilometresRelationManager extends RelationManager
{
    protected static string $relationship = 'kilometres';

    protected static ?string $title = 'Χιλιόμετρα';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), 'vehicles_klm', 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('KLM')->label('Χιλιόμετρα')->numeric()->required(),
            DatePicker::make('RecordDate')->label('Ημ/νία καταγραφής')->default(now())->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('RecordDate', 'desc')
            ->columns([
                TextColumn::make('RecordDate')->label('Ημ/νία καταγραφής')->date('d/m/Y')->sortable(),
                TextColumn::make('KLM')->label('Χιλιόμετρα')->numeric(0, ',', '.')->sortable(),
            ])
            ->headerActions([CreateAction::make()->label('New reading')->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_klm', 'create'))])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_klm', 'update')),
                DeleteAction::make()->visible(fn (): bool => Permissions::allows(auth()->user(), 'vehicles_klm', 'delete')),
            ]);
    }
}
