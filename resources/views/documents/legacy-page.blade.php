<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 10mm 12mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
    td { padding: 1.5pt 5pt; vertical-align: middle; }
    .lp-grid td { border-bottom: 0.4pt solid #e3e3e3; }
    .lp-grid tr.lp-spacer td { border: 0; padding: 0; height: 4pt; }
    .lp-grid.lp-cols-3 td:first-child { width: 42%; }
    img { max-width: 100%; max-height: 22mm; }
    p, h1, h2, h3, h4 { margin: 0; }
    h3 { font-size: 13pt; }
    .ql-align-right { text-align: right; }
    .ql-align-center { text-align: center; }
    .ql-align-justify { text-align: justify; }
    .ql-size-huge { font-size: 16pt; }
    .ql-size-large { font-size: 13pt; }
    .ql-size-small { font-size: 8pt; }
</style>
</head>
<body>
{!! $body !!}
</body>
</html>
