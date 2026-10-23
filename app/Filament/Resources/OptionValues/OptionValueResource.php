<?php

namespace App\Filament\Resources\OptionValues;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\OptionValues\Pages\ManageOptionValues;
use App\Models\OptionValue;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Field Settings" (optionvalues): the values of the drop-downs across the app
 * (transfer types, car types, statuses, file types, expense categories, ...) and their colours.
 */
class OptionValueResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'optionvalues';

    protected static ?string $model = OptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 94;

    protected static ?string $navigationLabel = 'Field Settings';

    protected static ?string $modelLabel = 'value';

    protected static ?string $pluralModelLabel = 'Field Settings';

    protected static ?string $slug = 'field-settings';

    /** @return array<string, string> the drop-downs that exist */
    public static function options(): array
    {
        return DB::table('optionvalues')->whereNotNull('Option')->distinct()->orderBy('Option')->pluck('Option', 'Option')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('Option')->label('Drop-down')->required()->maxLength(500)
                ->datalist(fn (): array => array_values(self::options()))
                ->helperText('Pick an existing one (e.g. TransferType, CarType, ExpenseCategory) or type a new name.'),
            TextInput::make('Value')->required()->maxLength(500),
            TextInput::make('Color')->maxLength(150)
                ->helperText('A CSS colour, e.g. #ffcc00 or Orange.')
                ->rule('regex:/^[#a-zA-Z0-9(),.% ]*$/'),
            TextInput::make('Group')->maxLength(150),
            Toggle::make('DriverView')->label('Drivers see it'),
            Toggle::make('PartnerView')->label('Partners see it'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('Option')->label('Drop-down')->collapsible())
            ->defaultSort('Value')
            ->paginated([50, 100, 'all'])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('Value')->searchable()->sortable(),
                ColorColumn::make('Color')->label('Colour')->placeholder('—'),
                TextColumn::make('colour_value')->label('Colour value')->state(fn (OptionValue $record): ?string => $record->Color)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Group')->placeholder('—')->toggleable(),
                IconColumn::make('DriverView')->label('Drivers')->boolean()->toggleable(),
                IconColumn::make('PartnerView')->label('Partners')->boolean()->toggleable(),
                TextColumn::make('OptionID')->label('ID')->sortable()
                    ->tooltip('Some lists store this number (e.g. file types, expense categories).'),
            ])
            ->filters([
                SelectFilter::make('Option')->label('Drop-down')->options(fn (): array => self::options())->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Records that already use this value keep it; it just stops being offered.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOptionValues::route('/')];
    }
}
