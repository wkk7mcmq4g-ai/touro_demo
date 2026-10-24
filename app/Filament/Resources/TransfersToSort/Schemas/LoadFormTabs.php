<?php

namespace App\Filament\Resources\TransfersToSort\Schemas;

use App\Filament\Resources\TransfersToSort\RelationManagers\LoadFilesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadNotesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadPriceLinesRelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

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
    public static function make(array $left, array $right, string $pageClass, array $top = []): Tabs
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
                        Group::make($left)->columns(2),
                        Group::make($right)->columns(2),
                    ]),
                ]),
                $list(LoadPriceLinesRelationManager::class, 'Products', Heroicon::OutlinedListBullet),
                $list(LoadNotesRelationManager::class, 'Σημειώσεις', Heroicon::OutlinedChatBubbleLeftRight),
                $list(LoadFilesRelationManager::class, 'Αρχεία', Heroicon::OutlinedPaperClip),
            ]);
    }
}
