<?php

namespace App\Support;

use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;

/**
 * Full screen + zoom for wide tables (views filament/tables/immersive*.blade.php). A page opts in by
 * being listed here: its table toolbar gets the Full screen and −/%/+ buttons.
 */
class ImmersiveTables
{
    /** @param  list<class-string>  $pages  Livewire page classes with a wide table */
    public static function register(array $pages): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn () => view('filament.tables.immersive-controls'),
            scopes: $pages,
        );
    }
}
