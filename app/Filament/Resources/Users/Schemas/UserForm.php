<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserGroup;
use App\Models\Counterparty;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Login')
                    ->columns(2)
                    ->components([
                        TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                        Toggle::make('active')
                            ->label('Active (can log in)')
                            ->formatStateUsing(fn ($state): bool => (int) $state === 1)
                            ->dehydrateStateUsing(fn ($state): int => $state ? 1 : 0)
                            ->default(true),
                    ]),
                Section::make('Profile & role')
                    ->columns(2)
                    ->components([
                        TextInput::make('fullname')
                            ->label('Full name')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Select::make('groupid')
                            ->label('Group')
                            ->options(UserGroup::class)
                            ->required()
                            ->live(),
                        Select::make('SupplierID')
                            ->label('Supplier')
                            ->helperText('Supplier logins only see this supplier’s transfers.')
                            ->options(fn () => Counterparty::query()
                                ->where('IsSupplier', 1)
                                ->orderBy('SupplierName')
                                ->pluck('SupplierName', 'CID'))
                            ->searchable()
                            ->visible(fn (Get $get): bool => in_array($get('groupid'), [UserGroup::Supplier, UserGroup::SupplierHotel, UserGroup::Supplier->value, UserGroup::SupplierHotel->value], true)),
                    ]),
            ]);
    }
}
