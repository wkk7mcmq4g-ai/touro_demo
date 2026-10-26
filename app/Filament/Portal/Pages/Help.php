<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\ShowsHelp;
use App\Support\Help as Manual;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Βοήθεια in the portal: the articles for this user (resources/help/portal; drivers get theirs). */
class Help extends Page
{
    use ShowsHelp;

    protected string $view = 'filament.help.manual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Βοήθεια';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Βοήθεια';

    protected static ?string $title = 'Βοήθεια';

    protected static ?string $slug = 'help';

    public static function canAccess(): bool
    {
        return auth()->check() && Manual::articles('portal')->isNotEmpty();
    }

    protected function helpPanel(): string
    {
        return 'portal';
    }
}
