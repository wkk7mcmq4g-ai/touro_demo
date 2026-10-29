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
 * The business's look, shared by the back office and the portal: colours from
 * config/business.php (navy, azure, mint for MyAT), Manrope served from public/myat (no font
 * CDN), a full-height navy sidebar holding search and the user menu. Styles:
 * public/myat/theme.css, whose brand colours are CSS variables set from the config here.
 */
class Theme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->brandName(config('business.name'))
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset(config('business.logo.mark')))
            ->colors([
                'primary' => Color::hex(config('business.colors.primary')),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Manrope', url: asset('myat/fonts.css'), provider: LocalFontProvider::class)
            ->topbar(false)
            ->globalSearch(position: GlobalSearchPosition::Sidebar)
            // Quick find from any screen (TransferResource::getGlobalSearchResults).
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn (): string => str_contains((string) request()->userAgent(), 'Mac') ? '⌘K' : 'Ctrl K')
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => '<link rel="stylesheet" href="'.asset('myat/theme.css').'?v='.@filemtime(public_path('myat/theme.css')).'">'.self::colorVariables())
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop();
    }

    /** The brand colours of config/business.php as the CSS variables theme.css uses. */
    public static function colorVariables(): string
    {
        $c = config('business.colors');
        $rgb = fn (string $hex): string => implode(' ', array_map('hexdec', str_split(ltrim($hex, '#'), 2)));
        $vars = [
            '--myat-navy' => $c['navy'],
            '--myat-navy-light' => $c['navy_light'],
            '--myat-navy-deep' => $c['navy_deep'],
            '--myat-azure' => $c['primary'],
            '--myat-azure-light' => $c['primary_light'],
            '--myat-azure-dark' => $c['primary_dark'],
            '--myat-azure-soft' => $c['primary_soft'],
            '--myat-azure-rgb' => $rgb($c['primary']),
            '--myat-mint' => $c['accent'],
            '--myat-mint-rgb' => $rgb($c['accent']),
            '--myat-navy-rgb' => $rgb($c['navy']),
            '--myat-page' => $c['page'],
        ];

        return '<style>:root{'.collect($vars)->map(fn (string $v, string $k): string => $k.':'.e($v))->implode(';').'}</style>';
    }
}
