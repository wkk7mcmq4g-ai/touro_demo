<?php

namespace App\Filament\Resources\Tours;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\Tour;
use App\Support\LegacyFiles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Προορισμοί" (tours): pick-up/drop-off places and tours, with the tour
 * description shown on the dispatch screen and the tour guide voucher.
 */
class TourResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'tours';

    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Προορισμοί';

    protected static ?string $modelLabel = 'destination';

    protected static ?string $recordTitleAttribute = 'TourName';

    private static function types(): array
    {
        $types = DB::table('tours')->whereNotNull('DestinationType')->where('DestinationType', '<>', '')->distinct()->orderBy('DestinationType')->pluck('DestinationType')->all();

        return array_combine($types, $types) + ['Tour' => 'Tour'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Προορισμοί')->columns(2)->columnSpanFull()->schema([
                TextInput::make('TourName')->label('Προορισμός')->required()->maxLength(500)
                    ->helperText('Names ending in "- MyAT" pass their Tickets setting to matching transfers.'),
                Select::make('DestinationType')->label('Destination type')->options(fn (): array => self::types())->live(),
                Textarea::make('TourDescription')->label('Περιγραφή')->rows(5)->maxLength(5000)->columnSpanFull(),
                // PHPRunner shows the duration only for tours.
                TextInput::make('TourDurationHours')->label('Διάρκεια (ώρες)')->numeric()->visible(fn (Get $get): bool => $get('DestinationType') === 'Tour'),
                TextInput::make('TourDurationDays')->label('Διάρκεια (μέρες)')->numeric()->visible(fn (Get $get): bool => $get('DestinationType') === 'Tour'),
                Toggle::make('Tickets')->label('Tickets needed'),
            ]),
            Section::make('Αρχεία')->columnSpanFull()->schema(self::legacyFilesComponents()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('TourName')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('TourName')->label('Προορισμός')->searchable()->sortable()->weight('bold')->wrap(),
                TextColumn::make('DestinationType')->label('Destination type')->badge()->sortable(),
                TextColumn::make('TourDescription')->label('Περιγραφή')->limit(120)->wrap()->searchable()->toggleable(),
                TextColumn::make('TourDurationHours')->label('Ώρες')->toggleable(),
                TextColumn::make('TourDurationDays')->label('Μέρες')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html()->toggleable(),
                IconColumn::make('Tickets')->boolean()->getStateUsing(fn (Tour $record): bool => (bool) $record->Tickets),
            ])
            ->filters([SelectFilter::make('DestinationType')->label('Destination type')->options(fn (): array => self::types())])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'edit' => EditTour::route('/{record}/edit'),
        ];
    }
}
