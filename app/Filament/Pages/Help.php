<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ShowsHelp;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Βοήθεια: the office manual (resources/help/admin). */
class Help extends Page
{
    use ShowsHelp;

    protected string $view = 'filament.help.manual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Βοήθεια';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Εγχειρίδιο';

    protected static ?string $title = 'Βοήθεια';

    protected static ?string $slug = 'help';

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function helpPanel(): string
    {
        return 'admin';
    }
}
