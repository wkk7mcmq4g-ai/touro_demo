<?php

namespace App\Filament\Resources\TransfersToSort\Schemas;

use App\Filament\Resources\TransfersToSort\RelationManagers\LoadFilesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadNotesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadPriceLinesRelationManager;
use App\Support\Permissions;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * PHPRunner's transfers_load page (Προς Διαλογή, and the partners' booking requests): tabs
 * Πληροφορίες (two columns of fields), Products, Σημειώσεις, Αρχεία; the lists inside their tab,
 * loaded the first time it is opened. The lists exist once the booking is saved.
 */
class LoadFormTabs
{
    /**
     * @param  array<mixed>  $left  fields of the left column (PHPRunner: Sup Code ... Notes)
     * @param  array<mixed>  $right  fields of the right column (From ... Lead Phone)
     * @param  class-string  $pageClass  the edit page the lists belong to
     */
    public static function make(array $left, array $right, string $pageClass, array $top = [], ?Closure $customerId = null): Tabs
    {
        $list = fn (string $manager, string $label, Heroicon $icon): Tab => Tab::make($label)
            ->icon($icon)
            ->visible(fn (string $operation, ?Model $record): bool => $operation === 'edit' && $record && $manager::canViewForRecord($record, $pageClass))
            ->schema([
                Livewire::make($manager, fn (?Model $record): array => ['ownerRecord' => $record, 'pageClass' => $pageClass, ...$manager::getDefaultProperties()])
                    ->key($manager)
                    ->lazy(),
            ]);

        return Tabs::make('load')
            ->contained()
            ->persistTabInQueryString('tab')
            ->columnSpanFull()
            ->tabs([
                Tab::make('Πληροφορίες')->icon(Heroicon::OutlinedInformationCircle)->schema([
                    ...$top,
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        // Two columns also on phones: short fields in pairs, long ones full width.
                        Group::make($left)->columns(['default' => 2]),
                        Group::make($right)->columns(['default' => 2]),
                    ]),
                ]),
                $list(LoadPriceLinesRelationManager::class, 'Products', Heroicon::OutlinedListBullet),
                $list(LoadNotesRelationManager::class, 'Σημειώσεις', Heroicon::OutlinedChatBubbleLeftRight),
                $list(LoadFilesRelationManager::class, 'Αρχεία', Heroicon::OutlinedPaperClip),
                // The add page, as PHPRunner's: products and files are entered with the booking and
                // saved with it.
                Tab::make('Products')->key('new-products')->icon(Heroicon::OutlinedListBullet)
                    ->visible(fn (string $operation): bool => $operation === 'create' && $customerId !== null && Permissions::allows(auth()->user(), 'price_list_load', 'create'))
                    ->schema([self::newProducts($customerId)]),
                Tab::make('Αρχεία')->key('new-files')->icon(Heroicon::OutlinedPaperClip)
                    ->visible(fn (string $operation): bool => $operation === 'create' && Permissions::allows(auth()->user(), 'myat_transfers_load_files', 'create'))
                    ->schema([self::newFiles()]),
            ]);
    }

    /** Products from the customer's price list, as on the products list (PriceLinesRelationManager). */
    private static function newProducts(?Closure $customerId): Repeater
    {
        $prices = fn (): Builder => DB::table('price_list')->where('cid_Customer', $customerId ? $customerId() : 0);

        return Repeater::make('priceLines')
            ->relationship()
            ->hiddenLabel()
            ->addActionLabel('Add product')
            ->defaultItems(0)
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                Select::make('Category')
                    ->options(fn (): array => $prices()->distinct()->orderBy('Category')->pluck('Category', 'Category')->all())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ProductID', null)),
                Select::make('ProductCarType')
                    ->label('Car type')
                    ->options(fn (Get $get): array => $prices()->when($get('Category'), fn ($q, $c) => $q->where('Category', $c))
                        ->distinct()->orderBy('CarType')->pluck('CarType', 'CarType')->all())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ProductID', null)),
                Select::make('ProductID')
                    ->label('Product')
                    ->options(fn (Get $get): array => $prices()
                        ->when($get('Category'), fn ($q, $c) => $q->where('Category', $c))
                        ->when($get('ProductCarType'), fn ($q, $t) => $q->where('CarType', $t))
                        ->orderBy('Product2')->pluck('Product2', 'ID')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $state ? $set('PriceValue', DB::table('price_list')->where('ID', $state)->value('PriceValue')) : null),
                TextInput::make('PriceValue')->label('Price')->numeric()->prefix('€')->required(),
            ]);
    }

    /** Files kept in the database (blobs), as the files list does. */
    private static function newFiles(): Repeater
    {
        return Repeater::make('files')
            ->relationship()
            ->hiddenLabel()
            ->addActionLabel('Add file')
            ->defaultItems(0)
            ->simple(FileUpload::make('upload')->hiddenLabel()->storeFiles(false)->maxSize(10 * 1024)->required())
            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                /** @var TemporaryUploadedFile $upload */
                $upload = is_array($data['upload']) ? reset($data['upload']) : $data['upload'];

                return ['Flnm' => $upload->getClientOriginalName(), 'Attachment' => $upload->get()];
            });
    }
}
