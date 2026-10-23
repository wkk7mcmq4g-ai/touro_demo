<?php

namespace App\Filament\Resources\ReviewLinks;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\ReviewLinks\Pages\CreateReviewLink;
use App\Filament\Resources\ReviewLinks\Pages\EditReviewLink;
use App\Filament\Resources\ReviewLinks\Pages\ListReviewLinks;
use App\Models\ReviewLink;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * PHPRunner review_links: the review link sent with a job (driver text, vouchers), by
 * transfer type and From/To (an empty From or To matches any).
 */
class ReviewLinkResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'review_links';

    protected static ?string $model = ReviewLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Review links';

    protected static ?string $modelLabel = 'review link';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('TransferType')->label('Transfer type')->options(fn (): array => Lookups::optionValues('TransferType'))->required(),
            TextInput::make('From')->datalist(fn (): array => array_keys(Lookups::pickupPlaces()))->helperText('Empty: any pick-up.'),
            TextInput::make('To')->datalist(fn (): array => array_keys(Lookups::destinations()))->helperText('Empty: any destination.'),
            TextInput::make('ReviewLink')->label('Review link')->url()->required()->maxLength(1500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('TransferType')
            ->columns([
                TextColumn::make('TransferType')->label('Transfer type')->badge()->sortable(),
                TextColumn::make('From')->searchable()->placeholder('(any)'),
                TextColumn::make('To')->searchable()->placeholder('(any)'),
                TextColumn::make('ReviewLink')->label('Review link')->url(fn (ReviewLink $record): string => $record->ReviewLink)->openUrlInNewTab()->limit(60),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewLinks::route('/'),
            'create' => CreateReviewLink::route('/create'),
            'edit' => EditReviewLink::route('/{record}/edit'),
        ];
    }
}
