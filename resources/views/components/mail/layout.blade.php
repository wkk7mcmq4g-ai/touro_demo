@props(['preheader' => '', 'eyebrow' => null, 'title', 'kicker' => null, 'pills' => [], 'footer' => null])
{{--
    Shared e-mail layout: tables and inline styles only (Gmail, Outlook, Apple Mail), 600px wide,
    stacks on phones, light and dark mode. The header uses a text wordmark so it reads even when
    images are blocked.
--}}
<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $title }}</title>
<style>
    @media (max-width: 620px) {
        .mx-w { width: 100% !important; }
        .mx-p { padding-left: 16px !important; padding-right: 16px !important; }
        .mx-time { width: 64px !important; font-size: 20px !important; }
        .mx-title { font-size: 24px !important; }
        .mx-lbl { width: 96px !important; }
    }
    @media (prefers-color-scheme: dark) {
        .mx-bg { background: #0b1220 !important; }
        .mx-card { background: #111c2d !important; border-color: #1e2a3d !important; }
        .mx-t { color: #e2e8f0 !important; }
        .mx-m { color: #94a3b8 !important; }
        .mx-soft { background: #17253a !important; }
        .mx-link { color: #7dd3fc !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background:#eef2f7;-webkit-text-size-adjust:100%" class="mx-bg">
@if ($preheader)
    <div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all">{{ $preheader }}&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f7" class="mx-bg">
<tr><td align="center" style="padding:24px 8px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="mx-w" style="width:600px;max-width:600px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif">
    <tr><td class="mx-p" style="background:{{ config('business.colors.mail_header') }};background-image:linear-gradient(135deg,{{ config('business.colors.mail_header_from') }},{{ config('business.colors.mail_header_to') }});border-radius:16px 16px 0 0;padding:22px 28px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
            <td style="font-size:18px;font-weight:800;color:#ffffff">{{ config('business.wordmark.prefix') }}<span style="color:{{ config('business.colors.mail_wordmark') }}">{{ config('business.wordmark.main') }}</span>{{ config('business.wordmark.suffix') }}</td>
            @if ($eyebrow)
                <td align="right" style="font-size:12px;color:{{ config('business.colors.mail_header_text') }}">{{ $eyebrow }}</td>
            @endif
        </tr></table>
        @if ($kicker)
            <div style="margin-top:18px;font-size:13px;color:{{ config('business.colors.mail_header_text') }};letter-spacing:.04em;text-transform:uppercase">{{ $kicker }}</div>
        @endif
        <div class="mx-title" style="{{ $kicker ? '' : 'margin-top:18px;' }}font-size:28px;font-weight:800;color:#ffffff;line-height:1.2">{{ $title }}</div>
        @if ($pills !== [])
            <div style="margin-top:14px;font-size:0;line-height:0">
                @foreach ($pills as $i => $pill)
                    <span style="display:inline-block;margin:0 6px 6px 0;background:{{ config('business.colors.mail_header_chip') }};border-radius:999px;padding:6px 12px;font-size:13px;line-height:18px;color:#ffffff;{{ $i === 0 ? 'font-weight:700;' : '' }}white-space:nowrap">{{ $pill }}</span>
                @endforeach
            </div>
        @endif
    </td></tr>
    <tr><td class="mx-p mx-card" style="background:#ffffff;padding:22px 28px 8px">
        {{ $slot }}
    </td></tr>
    <tr><td class="mx-p mx-card" style="background:#ffffff;padding:6px 28px 22px;border-radius:0 0 16px 16px">
        {{ $footer }}
        <p class="mx-m" style="font-size:12px;line-height:1.6;color:#64748b;margin:14px 0 0">
            Παρακαλούμε μην απαντήσετε σε αυτό το μήνυμα. Για απορίες και διευκρινίσεις:
            <a href="tel:{{ \App\Support\Business::tel(config('business.operations.phone')) }}" class="mx-link" style="color:#0369a1;font-weight:700;text-decoration:none;white-space:nowrap">{{ config('business.operations.phone') }}</a>
        </p>
    </td></tr>
    <tr><td align="center" style="padding:16px 8px 4px">
        <img src="{{ config('business.logo.mail') }}" width="96" height="38" alt="{{ config('business.logo.mail_alt') }}" style="display:block;border:0;width:96px;height:auto">
    </td></tr>
    <tr><td align="center" class="mx-m" style="padding:4px 8px 8px;font-size:11px;color:#94a3b8">{{ config('business.legal_name') }}</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
