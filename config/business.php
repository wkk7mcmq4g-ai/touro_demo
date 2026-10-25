<?php

/*
|--------------------------------------------------------------------------
| The business this copy of the app belongs to
|--------------------------------------------------------------------------
|
| Everything that identifies MyAthens Transfers (names, registration number, contact
| details, colours, logo, document images) lives here and in .env, so a copy of the app
| for another business changes this file, .env and the image files it points to, and
| nothing else. Defaults are MyAT's; .env overrides the values that have an env() key.
|
| Not here on purpose: the PHPRunner table names (MyAthensTransfersAPP_*, myat_*) and the
| internal `myat-` CSS/JS prefixes. They are code, not branding, and stay the same.
*/
return [

    // Shown in the panel, browser tab and PWA ("Add to Home Screen").
    'name' => env('BUSINESS_NAME', 'MyAthens Transfers'),
    'short_name' => env('BUSINESS_SHORT_NAME', 'MyAT'),

    // As printed on vouchers and the OLP request ("Επωνυμία επιχείρησης").
    'trade_name' => env('BUSINESS_TRADE_NAME', 'My Athens Transfers'),
    // Company with legal form: e-mail footer, "Εντολέας" line of the daily dispatch.
    'legal_name' => env('BUSINESS_LEGAL_NAME', 'My Athens Transfers IKE'),
    // ΜΗ.Τ.Ε. (GNTO licence number) on vouchers, the OLP request and the dispatch messages.
    // The app prints no VAT number (ΑΦΜ / ΔΟΥ); invoices come from Elorus.
    'mhte' => env('BUSINESS_MHTE', '0261E60000721601'),

    'website' => env('BUSINESS_WEBSITE', 'www.myathenstransfers.com'),
    // General mailbox: blind copy of the OLP requests.
    'email' => env('BUSINESS_EMAIL', 'info@myathenstransfers.com'),
    // Reservations: given to partners in the portal when a booking is refused.
    'reservations_email' => env('BUSINESS_RESERVATIONS_EMAIL', 'rsv@myathenstransfers.com'),
    // Operations desk: reply-to of guide assignments, phone in e-mails and the signature.
    'operations' => [
        'name' => env('BUSINESS_OPS_NAME', 'Aris Biliktsis'),
        'title' => env('BUSINESS_OPS_TITLE', 'Operations Manager'),
        'email' => env('BUSINESS_OPS_EMAIL', 'aris@myathenstransfers.com'),
        'phone' => env('BUSINESS_OPS_PHONE', '+30 6976 684 995'),
    ],
    // Line under the business name in the e-mail signature.
    'tagline' => env('BUSINESS_TAGLINE', 'Tour Operator'),

    /*
    | The name as drawn next to the logo mark (panel) and in the e-mail header:
    | prefix (small) + main + suffix (light). A plain name: set only 'main'.
    */
    'wordmark' => [
        'prefix' => env('BUSINESS_WORDMARK_PREFIX', 'my'),
        'main' => env('BUSINESS_WORDMARK_MAIN', 'Athens'),
        'suffix' => env('BUSINESS_WORDMARK_SUFFIX', 'Transfers'),
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
        'primary' => env('BUSINESS_COLOR_PRIMARY', '#0096ff'),       // azure
        'primary_light' => '#0aa2ff',
        'primary_dark' => '#0077d9',
        'primary_soft' => '#e6f2ff',
        'navy' => env('BUSINESS_COLOR_NAVY', '#08345a'),              // sidebar, PWA bar
        'navy_light' => '#0a3d68',
        'navy_deep' => '#051f38',
        'accent' => env('BUSINESS_COLOR_ACCENT', '#34eca6'),          // mint
        'page' => '#f3f6fa',
        // E-mail header (a deeper navy gradient, light text on it).
        'mail_header' => '#062a4a',
        'mail_header_from' => '#0a3d68',
        'mail_header_to' => '#041a30',
        'mail_header_text' => '#a5c8e4',
        'mail_header_chip' => '#1d4a73',
        'mail_wordmark' => '#38bdf8',
    ],

    /*
    | Images used by the PHPRunner voucher pages (resources/legacy/pages/*.json refer to
    | them as "business:<key>"), relative to resources/.
    */
    'documents' => [
        'logo' => 'legacy/images/Myatlogo.png',
        'voucher_header' => 'legacy/images/MyatHeader_Voucher.JPG',
        'stamp' => 'legacy/images/Sfragida .png',
    ],

];
