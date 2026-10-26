<?php

namespace App\Navigation;

use App\Support\PersonalMenu;
use Filament\Navigation\NavigationManager;

/** The office panel's side menu, arranged the way each user set it up (App\Support\PersonalMenu). */
class PersonalNavigationManager extends NavigationManager
{
    private bool $plain = false;

    public function get(): array
    {
        $groups = parent::get();

        if ($this->plain || $this->panel->getId() !== 'admin' || ! auth()->check()) {
            return $groups;
        }

        try {
            return PersonalMenu::apply($groups, PersonalMenu::config());
        } catch (\Throwable $e) {
            report($e); // a broken arrangement must never take the menu away

            return $groups;
        }
    }

    /** The menu as Filament builds it, before the user's arrangement (for the editor). */
    public function defaults(): array
    {
        // Through the panel, as the sidebar does: while it asks, pages register their items here.
        $this->plain = true;
        try {
            return $this->panel->getNavigation();
        } finally {
            $this->plain = false;
        }
    }
}
