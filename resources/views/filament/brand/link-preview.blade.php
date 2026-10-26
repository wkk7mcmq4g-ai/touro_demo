{{-- What link-preview robots get (App\Http\Middleware\LinkPreview): the screen's name and the logo, no data. --}}
<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<title>{{ $title }} · {{ config('business.name') }}</title>
<meta name="description" content="{{ config('business.name') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('business.name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ config('business.name') }}">
<meta property="og:url" content="{{ $url }}">
{{-- A small square logo (under 300 px): WhatsApp then shows a small thumbnail beside the title,
     not a big picture above it. --}}
<meta property="og:image" content="{{ asset(config('business.logo.icon_180')) }}">
<meta property="og:image:width" content="180">
<meta property="og:image:height" content="180">
<meta name="twitter:card" content="summary">
<meta name="robots" content="noindex, nofollow">
</head>
<body><h1>{{ $title }}</h1><p>{{ config('business.name') }}</p></body>
</html>
