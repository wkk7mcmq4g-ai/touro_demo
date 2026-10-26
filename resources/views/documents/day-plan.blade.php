{{--
    Ημερήσιο πλάνο as PDF (App\Filament\Pages\DayTimeline::pdf): A4 landscape, Dompdf, DejaVu Sans.
    One section per vehicle with jobs (unassigned first), a table of its jobs with a bar showing where
    each falls in the day, warnings written out; vehicles without jobs listed at the end.
--}}
@php
    $span = max(60, $to - $from);
    $hhmm = fn (?int $m): string => $m === null ? '' : sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60);
    $colours = ['Arrival' => '#0284c7', 'Departure' => '#059669', 'Tour' => '#7c3aed'];
@endphp
<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<title>Ημερήσιο πλάνο {{ $day->format('d/m/Y') }}</title>
<style>
    @page { margin: 12mm 10mm 12mm 10mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 7.6pt; color: #111827; }
    .top { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
    .top td { vertical-align: bottom; }
    h1 { font-size: 14pt; margin: 0; }
    .muted { color: #6b7280; }
    .pill { display: inline-block; padding: 1px 6px; border-radius: 8px; background: #f3f4f6; margin-left: 4px; }
    .pill.warn { background: #fef3c7; color: #92400e; }
    .pill.bad { background: #fee2e2; color: #b91c1c; }
    .veh { margin-top: 3mm; page-break-inside: avoid; }
    .veh-h { padding: 3px 6px; background: #f3f4f6; border: 1px solid #e5e7eb; border-bottom: 0; font-weight: bold; font-size: 8.4pt; }
    .veh-h.none { background: #fef3c7; }
    .veh-h span { font-weight: normal; color: #6b7280; font-size: 7.4pt; }
    .veh-h .warn { color: #b45309; font-weight: bold; }
    table.jobs { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.jobs th { text-align: left; font-size: 6.6pt; text-transform: uppercase; color: #6b7280; padding: 2px 4px; border: 1px solid #e5e7eb; background: #fafafa; }
    table.jobs td { padding: 3px 4px; border: 1px solid #e5e7eb; vertical-align: top; word-wrap: break-word; }
    tr.clash td { background: #fef2f2; }
    .t { font-weight: bold; font-size: 8.4pt; }
    .type { display: inline-block; width: 6px; height: 6px; border-radius: 3px; margin-right: 3px; }
    .warnline { color: #b91c1c; font-weight: bold; }
    .bar-cell { padding: 4px 4px !important; }
    .scale { position: relative; height: 8px; background: #f3f4f6; }
    .bar { position: absolute; top: 0; height: 8px; border-radius: 2px; }
    .bar.est { opacity: .65; }
    table.ticks { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.ticks td { border: 0; border-left: 1px solid #d1d5db; padding: 0 0 0 2px; font-size: 5.8pt; color: #6b7280; font-weight: normal; text-transform: none; white-space: nowrap; overflow: hidden; }
    .idle { margin-top: 4mm; color: #6b7280; }
    .foot { margin-top: 3mm; font-size: 6.4pt; color: #9ca3af; }
</style>
</head>
<body>
<table class="top">
    <tr>
        <td>
            <h1>Ημερήσιο πλάνο · {{ $day->locale('el')->translatedFormat('l j F Y') }}</h1>
            <div class="muted">{{ config('business.name') }}</div>
        </td>
        <td style="text-align:right">
            <span class="pill"><b>{{ $jobs }}</b> δρομολόγια</span>
            <span class="pill {{ $unassigned ? 'warn' : '' }}"><b>{{ $unassigned }}</b> χωρίς όχημα</span>
            <span class="pill {{ $clashes ? 'bad' : '' }}"><b>{{ $clashes }}</b> επικαλύψεις</span>
            <div class="muted" style="margin-top:2px">Εκτύπωση {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

@foreach ($rows as $row)
    @continue($row['jobs'] === [])
    <div class="veh">
        <div class="veh-h {{ $row['kind'] === 'none' ? 'none' : '' }}">
            {{ $row['name'] }}
            <span class="{{ ($row['noDriver'] ?? false) ? 'warn' : '' }}">· {{ $row['sub'] }} · {{ count($row['jobs']) }} δρομ.</span>
        </div>
        <table class="jobs">
            <colgroup>
                <col style="width:7%"><col style="width:9%"><col style="width:27%"><col style="width:5%"><col style="width:13%"><col style="width:10%"><col style="width:8%"><col style="width:21%">
            </colgroup>
            <tr>
                <th>Ώρα</th><th>Τύπος</th><th>Από → Προς</th><th>Άτ.</th><th>Επιβάτης</th><th>Πτήση / Πλοίο</th><th>Πελάτης</th>
                <th class="bar-cell">
                    {{-- hour scale for the bars: one cell per 3 hours, widths in proportion --}}
                    <table class="ticks"><tr>
                        @for ($m = $from; $m < $to; $m += 180)
                            <td style="width:{{ min(180, $to - $m) / $span * 100 }}%">{{ sprintf('%02d', intdiv($m, 60) % 24) }}</td>
                        @endfor
                    </tr></table>
                </th>
            </tr>
            @foreach ($row['jobs'] as $j)
                <tr class="{{ $j['clash'] ? 'clash' : '' }}">
                    <td>
                        <span class="t">{{ $j['start'] !== null ? $hhmm($j['start']) : ($j['time'] ?: 'TBD') }}</span>
                        @if ($j['start'] !== null)<br><span class="muted">έως {{ $hhmm($j['end']) }}{{ $j['estimated'] ? '*' : '' }}</span>@endif
                    </td>
                    <td><span class="type" style="background:{{ $colours[$j['type']] ?? '#475569' }}"></span>{{ $j['type'] }}</td>
                    <td>
                        {{ $j['type'] === 'Tour' ? $j['to'] : $j['from'].' → '.$j['to'] }}
                        @if ($j['clash'])<br><span class="warnline">⚠ Επικαλύπτεται με άλλο δρομολόγιο</span>@endif
                        @if ($j['over'])<br><span class="warnline">⚠ Περισσότεροι επιβάτες από θέσεις</span>@endif
                    </td>
                    <td>{{ $j['pax'] ?: '' }}</td>
                    <td>{{ $j['lead'] }}</td>
                    <td>{{ $j['flight'] }}</td>
                    <td>{{ $j['customer'] }}</td>
                    <td class="bar-cell">
                        <div class="scale">
                            @if ($j['start'] !== null)
                                <div class="bar {{ $j['estimated'] ? 'est' : '' }}" style="left:{{ max(0, ($j['start'] - $from) / $span * 100) }}%; width:{{ max(1.2, ($j['end'] - $j['start']) / $span * 100) }}%; background:{{ $colours[$j['type']] ?? '#475569' }}"></div>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endforeach

@php($idle = collect($rows)->where('kind', 'own')->where('jobs', [])->pluck('name'))
@if ($idle->isNotEmpty())
    <div class="idle"><b>Χωρίς δρομολόγια:</b> {{ $idle->implode(' · ') }}</div>
@endif
@if ($jobs === 0)
    <p class="idle">Κανένα δρομολόγιο αυτή την ημέρα.</p>
@endif
<div class="foot">* εκτιμώμενη ώρα λήξης (Arrival 1:30, Departure 1:15, Transfer 1:00, εκδρομή από τη διάρκεια της εκδρομής). Η μπάρα δείχνει τη θέση του δρομολογίου στην ημέρα {{ $hhmm($from) }}–{{ $hhmm($to) }}.</div>
</body>
</html>
