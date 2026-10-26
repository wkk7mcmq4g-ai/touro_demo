<?php

/*
|--------------------------------------------------------------------------
| The business this copy of the app belongs to
|--------------------------------------------------------------------------
|
| Everything that identifies Demo Transfers (names, registration number, contact
| details, colours, logo, document images) lives here and in .env, so a copy of the app
| for another business changes this file, .env and the image files it points to, and
| nothing else. Defaults are the Demo showcase's (made up: a copy of MyAT shown to
| prospects, on an anonymised copy of MyAT's database); .env overrides the values that have an env() key.
|
| Not here on purpose: the PHPRunner table names (MyAthensTransfersAPP_*, myat_*) and the
| internal `myat-` CSS/JS prefixes. They are code, not branding, and stay the same.
*/
return [

    // Shown in the panel, browser tab and PWA ("Add to Home Screen").
    'name' => env('BUSINESS_NAME', 'Demo Transfers'),
    'short_name' => env('BUSINESS_SHORT_NAME', 'Demo'),

    // As printed on vouchers and the OLP request ("Επωνυμία επιχείρησης").
    'trade_name' => env('BUSINESS_TRADE_NAME', 'Demo Transfers & Tours'),
    // Company with legal form: e-mail footer, "Εντολέας" line of the daily dispatch.
    'legal_name' => env('BUSINESS_LEGAL_NAME', 'Demo Transfers IKE'),
    // ΜΗ.Τ.Ε. (GNTO licence number) on vouchers, the OLP request and the dispatch messages.
    // The app prints no VAT number (ΑΦΜ / ΔΟΥ); invoices come from Elorus.
    'mhte' => env('BUSINESS_MHTE', '0000E00000000000'),

    'website' => env('BUSINESS_WEBSITE', 'www.example.com'),
    // General mailbox: blind copy of the OLP requests.
    'email' => env('BUSINESS_EMAIL', 'info@example.com'),
    // Reservations: given to partners in the portal when a booking is refused.
    'reservations_email' => env('BUSINESS_RESERVATIONS_EMAIL', 'rsv@example.com'),
    // Operations desk: reply-to of guide assignments, phone in e-mails and the signature.
    'operations' => [
        'name' => env('BUSINESS_OPS_NAME', ''),
        'title' => env('BUSINESS_OPS_TITLE', 'Operations Desk'),
        'email' => env('BUSINESS_OPS_EMAIL', 'ops@example.com'),
        'phone' => env('BUSINESS_OPS_PHONE', '+30 210 000 0000'),
    ],
    // Line under the business name in the e-mail signature.
    'tagline' => env('BUSINESS_TAGLINE', 'Transfers & Tours'),

    /*
    | The name as drawn next to the logo mark (panel) and in the e-mail header:
    | prefix (small) + main + suffix (light). A plain name: set only 'main'.
    */
    'wordmark' => [
        'prefix' => env('BUSINESS_WORDMARK_PREFIX', ''),
        'main' => env('BUSINESS_WORDMARK_MAIN', 'Demo'),
        'suffix' => env('BUSINESS_WORDMARK_SUFFIX', ' Transfers'),   // leading space: "Demo Transfers", not "DemoTransfers"
    ],

    /*
    | Logo files. `mark` is an SVG under public/ (panel logo, favicon); it is inlined in the
    | panel so it can follow dark mode. Icons are the PWA / home-screen icons.
    */
    'logo' => [
        'mark' => 'myat/mark.svg',
        'icon_180' => 'myat/icon-180.png',
        'icon_192' => 'myat/icon-192.png',
        'icon_512' => 'myat/icon-512.png',
        // Small logo at the foot of every e-mail (absolute URL: mail clients fetch it).
        'mail' => env('BUSINESS_MAIL_LOGO_URL', 'https://touro.gr/images/Touro_email.jpg'),
        'mail_alt' => 'Touro',
    ],

    /*
    | Colours. The panel theme (public/myat/theme.css) reads them as CSS variables;
    | Filament's primary colour, the dashboard chart and the e-mails use them directly.
    */
    'colors' => [
        'primary' => env('BUSINESS_COLOR_PRIMARY', '#0d9488'),       // teal
        'primary_light' => '#14b8a6',
        'primary_dark' => '#0f766e',
        'primary_soft' => '#e6f6f4',
        'navy' => env('BUSINESS_COLOR_NAVY', '#0f3d3e'),              // sidebar, PWA bar
        'navy_light' => '#14504f',
        'navy_deep' => '#092829',
        'accent' => env('BUSINESS_COLOR_ACCENT', '#f59e0b'),          // amber
        'page' => '#f4f7f7',
        // E-mail header (a deeper navy gradient, light text on it).
        'mail_header' => '#0c3536',
        'mail_header_from' => '#14504f',
        'mail_header_to' => '#082223',
        'mail_header_text' => '#a7d8d2',
        'mail_header_chip' => '#1f5f5d',
        'mail_wordmark' => '#5eead4',
    ],

    /*
    | Images used by the PHPRunner voucher pages (resources/legacy/pages/*.json refer to
    | them as "business:<key>"), relative to resources/.
    */
    'documents' => [
        'logo' => 'legacy/images/demo-logo.png',
        'voucher_header' => 'legacy/images/demo-voucher-header.png',
        'stamp' => 'legacy/images/demo-stamp.png',
    ],

];
