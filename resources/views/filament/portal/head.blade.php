{{-- Installable on the phone's home screen ("Add to Home Screen"), opening full screen at /portal. --}}
<link rel="manifest" href="{{ route('portal.manifest') }}">
<link rel="apple-touch-icon" href="{{ asset(config('business.logo.icon_180')) }}">
<meta name="theme-color" content="{{ config('business.colors.navy') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('business.short_name') }}">
