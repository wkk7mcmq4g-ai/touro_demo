<?php

namespace App\Filament\Concerns;

use App\Support\PhpRunnerLayouts;

/**
 * On a table page's first load, a user without a layout of their own in the new app gets their
 * columns (order, shown, widths) from the old PHPRunner list (App\Support\PhpRunnerLayouts::PAGES).
 * Runs after Filament has built the table: Livewire calls this trait's booted hook after
 * InteractsWithTable's, which the page's own booted() would not.
 */
trait CopiesPhpRunnerLayout
{
    protected bool $copyPhpRunnerLayout = false;

    public function mountCopiesPhpRunnerLayout(): void
    {
        $this->copyPhpRunnerLayout = true;
    }

    public function bootedCopiesPhpRunnerLayout(): void
    {
        if (! $this->copyPhpRunnerLayout) {
            return;
        }
        $this->copyPhpRunnerLayout = false;

        $username = (string) auth()->user()?->username;
        // Filament has already put its default columns in the session; what counts is a saved layout.
        if (! PhpRunnerLayouts::hasNewLayout($this, $username)) {
            PhpRunnerLayouts::apply($this, $username);
        }
    }
}
