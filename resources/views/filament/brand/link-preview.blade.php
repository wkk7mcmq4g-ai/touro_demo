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
<meta property="og:image" content="{{ asset(config('business.logo.icon_512')) }}">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta name="twitter:card" content="summary">
<meta name="robots" content="noindex, nofollow">
</head>
<body><h1>{{ $title }}</h1><p>{{ config('business.name') }}</p></body>
</html>
