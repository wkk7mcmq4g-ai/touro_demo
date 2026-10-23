<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('username')
            ->columns([
                TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('fullname')
                    ->label('Full name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('groupid')
                    ->label('Group')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => UserGroup::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->color(fn (?string $state): string => UserGroup::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => (int) $record->active === 1),
                TextColumn::make('LastLoginDateTime')
                    ->label('Last login')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('groupid')
                    ->label('Group')
                    ->options(UserGroup::class),
                TernaryFilter::make('active')
                    ->queries(
                        true: fn (Builder $query) => $query->where('active', 1),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->where('active', '<>', 1)->orWhereNull('active')),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
