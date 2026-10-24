<?php

namespace App\Filament\Resources\FuelRegisters;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\FuelRegisters\Pages\CreateFuelRegister;
use App\Filament\Resources\FuelRegisters\Pages\EditFuelRegister;
use App\Filament\Resources\FuelRegisters\Pages\ListFuelRegisters;
use App\Models\FuelRegister;
use App\Support\FilterValues;
use App\Support\LegacyFiles;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** PHPRunner "Καύσιμα" (fuel_register): fuel receipts, mostly entered by the drivers, checked here. */
class FuelRegisterResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'fuel_register';

    protected static ?string $model = FuelRegister::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Καύσιμα';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Καύσιμα';

    protected static ?string $modelLabel = 'fuel receipt';

    protected static ?string $slug = 'fuel';

    private const STATUS = ['OK' => 'OK', 'Approved' => 'Approved', 'Rejected' => 'Rejected'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία Χρέωσης')->columns(3)->columnSpanFull()->schema([
                Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(['IKE', 'Taxi', 'DXF']))->searchable()->required(),
                DatePicker::make('FuelDate')->label('Ημερομηνία')->default(now())->required(),
                TextInput::make('FuelAmount')->label('Αξία')->numeric()->prefix('€')->required(),
                Select::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers())->searchable(),
                Select::make('ApprovedStatus')->label('Έλεγχος')->options(self::STATUS),
                Textarea::make('ApprovedReason')->label('Σχόλιο ελέγχου')->rows(1)->maxLength(500),
            ]),
            Section::make('Αρχεία')->columnSpanFull()->schema(self::legacyFilesComponents()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('FuelDate', 'desc')
            ->columns([
                TextColumn::make('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('CreatedBy')->label('Καταχώρηση')->sortable()->searchable(),
                TextColumn::make('FuelDate')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
                TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null)->sortable(),
                TextColumn::make('FuelAmount')->label('Αξία')->money('EUR', locale: 'el')->alignEnd()->sortable()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html(),
                TextColumn::make('ApprovedBy')->label('Έλεγχος από')->placeholder('—'),
                TextColumn::make('ApprovedDate')->label('Ημ/νία ελέγχου')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('ApprovedStatus')->label('Έλεγχος')->badge()->placeholder('Pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'Rejected' => 'danger', null, '' => 'warning', default => 'success'
                    }),
            ])
            ->filters([
                SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->multiple(),
                SelectFilter::make('checked')->label('Έλεγχος')->options(['pending' => 'Not checked', 'checked' => 'Checked'])
                    ->query(fn (Builder $q, array $data) => FilterValues::any($q, $data, [
                        'pending' => fn (Builder $q) => $q->whereNull('ApprovedStatus')->orWhere('ApprovedStatus', ''),
                        'checked' => fn (Builder $q) => $q->whereNotNull('ApprovedStatus')->where('ApprovedStatus', '<>', ''),
                    ])),
                Filter::make('month')->schema([DatePicker::make('month')->label('Μήνας')->displayFormat('m/Y')])
                    ->query(fn (Builder $q, array $data): Builder => $q->when($data['month'] ?? null, fn ($q, $m) => $q->whereYear('FuelDate', substr($m, 0, 4))->whereMonth('FuelDate', substr($m, 5, 2)))),
            ])
            ->recordActions([
                // PHPRunner's check button (New_Button75).
                Action::make('approve')->label('OK')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (FuelRegister $record): bool => blank($record->ApprovedStatus) && (bool) auth()->user()?->may('fuel_register', 'edit'))
                    ->action(fn (FuelRegister $record) => DB::table('fuel_register')->where('ID', $record->ID)->update([
                        'ApprovedDate' => now()->toDateString(), 'ApprovedBy' => auth()->user()->username, 'ApprovedStatus' => 'OK',
                    ])),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuelRegisters::route('/'),
            'create' => CreateFuelRegister::route('/create'),
            'edit' => EditFuelRegister::route('/{record}/edit'),
        ];
    }
}
