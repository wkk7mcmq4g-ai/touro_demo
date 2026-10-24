<?php

namespace App\Support;

use Filament\Pages\Page;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\View\TablesRenderHook;
use Livewire\Livewire;

/**
 * Full screen + zoom (views filament/tables/immersive*.blade.php) on every table page of the back
 * office: the table toolbar gets the Full screen and −/%/+ buttons. Not on tables inside another
 * page (relation managers on the edit form, dashboard widgets) nor in the drivers' and partners' portal.
 */
class ImmersiveTables
{
    public static function register(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn () => self::applies() ? view('filament.tables.immersive-controls') : '',
        );
    }

    private static function applies(): bool
    {
        $component = Livewire::current();

        return $component instanceof HasTable
            && ! str_starts_with($component::class, 'App\\Filament\\Portal\\')
            && $component instanceof Page; // not table widgets nor relation managers
    }
}
