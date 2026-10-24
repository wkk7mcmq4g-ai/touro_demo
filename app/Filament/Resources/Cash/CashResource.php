<?php

namespace App\Filament\Resources\Cash;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Cash\Pages\CreateCash;
use App\Filament\Resources\Cash\Pages\EditCash;
use App\Filament\Resources\Cash\Pages\ListCash;
use App\Models\Cash;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use UnitEnum;

/**
 * PHPRunner "Ταμείο" (cash): a count of the money in every account and box on a date.
 * The latest count is the "Cash In Hand" of the Θέση page.
 */
class CashResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'cash';

    protected static ?string $model = Cash::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ταμείο';

    protected static ?string $modelLabel = 'cash count';

    protected static ?string $pluralModelLabel = 'Ταμείο';

    protected static ?string $slug = 'cash';

    public static function form(Schema $schema): Schema
    {
        $boxes = collect(Cash::BOXES)->map(fn (string $label, string $column) => TextInput::make($column)->label($label)
            ->numeric()->prefix('€')->default(0)->live(onBlur: true))->values()->all();

        return $schema->components([
            Section::make('Στοιχεία')->columns(['default' => 2, 'lg' => 4])->columnSpanFull()->schema([
                DatePicker::make('DT')->label('Ημερομηνία')->default(now())->required()->columnSpan(['default' => 2, 'lg' => 1]),
                ...$boxes,
                TextInput::make('total')->label('Total')->prefix('€')->disabled()->dehydrated(false)->columnSpan(['default' => 2, 'lg' => 1])
                    ->helperText('Worked out by the database when saved.')
                    ->formatStateUsing(fn (Get $get): string => Number::format(collect(array_keys(Cash::BOXES))->sum(fn (string $c): float => (float) $get($c)), 2, locale: 'el'))
                    ->live(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $boxes = collect(Cash::BOXES)->map(fn (string $label, string $column) => TextColumn::make($column)->label($label)
            ->money('EUR', locale: 'el')->alignEnd()->toggleable())->values()->all();

        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('DT')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
                ...$boxes,
                TextColumn::make('Total')->money('EUR', locale: 'el')->alignEnd()->weight('bold')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCash::route('/'),
            'create' => CreateCash::route('/create'),
            'edit' => EditCash::route('/{record}/edit'),
        ];
    }
}
