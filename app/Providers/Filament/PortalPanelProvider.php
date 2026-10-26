<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Reports\VanTaxiReport;
use App\Filament\Theme;
use App\Http\Middleware\LinkPreview;
use App\Http\Middleware\SendToHomePanel;
use App\Http\Middleware\SyncTableLayouts;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The portal (/portal) for everyone outside the office, with the same logins as PHPRunner:
 * partners, suppliers, hotels and coach companies see only their own transfers
 * (App\Support\Portal), drivers their jobs. PHPRunner's group masks still decide what they may do.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Theme::apply($panel)
            ->id('portal')
            ->path('portal')
            ->login(Login::class)
            ->globalSearch(false)
            // Phone friendly: installable, and a tab bar at the bottom on small screens.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.portal.head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.portal.bottom-nav') : '')
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            // Hotels have PHPRunner's Van Taxi Report too.
            ->pages([VanTaxiReport::class])
            ->databaseNotifications(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Link previews (WhatsApp, Viber, ...) show the page's name and logo, not "Login".
                LinkPreview::class,
                SyncTableLayouts::class,
            ])
            ->authMiddleware([
                SendToHomePanel::class,
            ]);
    }
}
