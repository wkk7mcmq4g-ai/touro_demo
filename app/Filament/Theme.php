<?php

namespace App\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use Filament\Enums\GlobalSearchPosition;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

/**
 * The MyAthens Transfers look, shared by the back office and the portal: logo colours (navy,
 * azure, mint), Manrope served from public/myat (no font CDN), a full-height navy sidebar
 * holding search and the user menu. Styles: public/myat/theme.css.
 */
class Theme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->brandName('MyAthens Transfers')
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('myat/mark.svg'))
            ->colors([
                'primary' => Color::hex('#0096ff'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Manrope', url: asset('myat/fonts.css'), provider: LocalFontProvider::class)
            ->topbar(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<link rel="stylesheet" href="'.asset('myat/theme.css').'?v='.@filemtime(public_path('myat/theme.css')).'">')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop();
    }
}
