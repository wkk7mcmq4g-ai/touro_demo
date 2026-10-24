<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament's panel authentication, except that signed-in users who open the other panel (a driver
 * typing /admin, an office user /portal) are taken to their own one instead of "403 Forbidden".
 */
class SendToHomePanel extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        if ($user instanceof User && $panel && ($home = $user->homePanelId()) && $home !== $panel->getId()) {
            return $request->headers->has('X-Livewire')
                ? abort(403)
                : redirect()->to(Filament::getPanel($home)->getUrl());
        }

        return parent::handle($request, $next, ...$guards);
    }
}
