<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Help;
use App\Filament\Pages\MyMenu;
use App\Filament\Theme;
use App\Http\Middleware\LinkPreview;
use App\Http\Middleware\SendToHomePanel;
use App\Http\Middleware\SyncTableLayouts;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Support\AppVersion;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Theme::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // PHPRunner asked before leaving the add page; warn before losing unsaved changes.
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Εργασίες'),
                NavigationGroup::make('Partner jobs'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Fleet & drivers'),
                NavigationGroup::make('Settings'),
                NavigationGroup::make('Βοήθεια'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->databaseNotifications(false)
            // Each user's own side menu (App\Filament\Pages\MyMenu): from the user menu and under the side menu.
            ->userMenuItems([
                Action::make('myMenu')->label('Το μενού μου')->icon(Heroicon::OutlinedSquares2x2)->url(fn (): string => MyMenu::getUrl()),
                Action::make('help')->label('Βοήθεια')->icon(Heroicon::OutlinedQuestionMarkCircle)->url(fn (): string => Help::getUrl()),
                // Which version is running (new number on every push).
                Action::make('version')->label(fn (): string => AppVersion::label())->tooltip(fn (): ?string => AppVersion::details())->icon(Heroicon::OutlinedTag)->color('gray')->disabled()->sort(100),
            ])
            // The login page's own link preview: business name and logo.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => auth()->check() ? '' : '<meta property="og:site_name" content="'.e(config('business.name')).'"><meta property="og:image" content="'.e(asset(config('business.logo.icon_180'))).'">')
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_END, fn (): string => view('filament.brand.edit-menu-link')->render())
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
