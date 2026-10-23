<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Models\Driver;
use App\Models\User;
use App\Support\LegacyFiles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** PHPRunner "Οδηγοί" (drivers). */
class DriverResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'drivers';

    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Οδηγοί';

    protected static ?string $modelLabel = 'driver';

    protected static ?string $recordTitleAttribute = 'Fullname';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία Οδηγού')->columns(3)->columnSpanFull()->schema([
                TextInput::make('Fullname')->label('Ονοματεπώνυμο')->required()->maxLength(150),
                TextInput::make('Shortname')->maxLength(50),
                TextInput::make('IDNumber')->label('Αριθμός Ταυτότητας')->maxLength(150),
                TextInput::make('LicenceNumber')->label('Δίπλωμα Οδήγησης')->maxLength(150),
                TextInput::make('LicenceType')->label('Τύπος Διπλώματος')->maxLength(150),
                TextInput::make('MobilePhone')->label('Τηλέφωνο')->tel()->maxLength(50),
                TextInput::make('Email')->email()->maxLength(150),
                Select::make('UserID')->label('Όνομα χρήστη (Touro)')
                    ->options(fn (): array => User::query()->orderBy('username')->pluck('username', 'ID')->all())->searchable(),
                Select::make('TelegramID')->label('Telegram')
                    ->options(fn (): array => DB::table('telegram_users')->orderBy('first_name')
                        ->get()->mapWithKeys(fn ($u) => [$u->telegram_id => trim($u->first_name.' '.$u->last_name)])->all())
                    ->searchable(),
                DatePicker::make('StartDate')->label('Ημ/νία Έναρξης'),
                DatePicker::make('EndDate')->label('Ημ/νία Αποχώρησης'),
                TextInput::make('ContractType')->label('Τύπος Σύμβασης')->maxLength(150),
                TextInput::make('Salary')->label('Μισθός')->numeric()->prefix('€'),
                TextInput::make('Benefits')->numeric()->prefix('€'),
                Toggle::make('Active')->default(true),
            ]),
            Section::make('Αρχεία')->columnSpanFull()->schema(self::legacyFilesComponents()),
        ]);
    }

    public static function table(Table $table): Table
    {
        $text = fn (string $name, string $label, bool $hidden = false): TextColumn => TextColumn::make($name)->label($label)
            ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: $hidden)->placeholder('—');

        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('Fullname')
            ->columns([
                $text('Fullname', 'Ονοματεπώνυμο')->weight('bold'),
                $text('MobilePhone', 'Τηλέφωνο'),
                $text('Email', 'Email'),
                $text('LicenceNumber', 'Δίπλωμα Οδήγησης'),
                $text('IDNumber', 'Αριθμός Ταυτότητας'),
                TextColumn::make('UserID')->label('Χρήστης (Touro)')->toggleable()
                    ->formatStateUsing(fn ($state): ?string => User::query()->whereKey($state)->value('username')),
                TextColumn::make('StartDate')->label('Έναρξη')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('EndDate')->label('Αποχώρηση')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                $text('LicenceType', 'Τύπος Διπλώματος', true),
                $text('ContractType', 'Τύπος Σύμβασης', true),
                TextColumn::make('Salary')->label('Μισθός')->money('EUR', locale: 'el')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Benefits')->money('EUR', locale: 'el')->toggleable(isToggledHiddenByDefault: true),
                $text('Shortname', 'Shortname', true),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html()->toggleable(),
                IconColumn::make('Active')->boolean()->toggleable(),
            ])
            ->filters([TernaryFilter::make('Active')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
