@php
    $r = $this->result();
    $in = $this->insights($r);
    $chips = $this->activeFilters();
    $money = $r['money'];
    $fmt = fn (?float $v): string => $v === null ? '' : ($money
        ? \Illuminate\Support\Number::currency($v, 'EUR', 'el')
        : \Illuminate\Support\Number::format($v, maxPrecision: 2, locale: 'el'));
    // Short figures for the chart axis and cards: 12,4χιλ. / 1,2εκ.
    $short = fn (float $v): string => ($money ? '€' : '').\Illuminate\Support\Number::abbreviate($v, maxPrecision: 1);
    // Cell shading: azure for positive, rose for negative, by size against the biggest cell.
    $heat = function (?float $v) use ($r): string {
        if (! $this->heatmap || ! $v || ! $r['maxCell']) {
            return '';
        }
        $a = round(0.05 + 0.33 * min(1, abs($v) / $r['maxCell']), 3);

        return $v > 0 ? "background-color: rgb(0 150 255 / {$a})" : "background-color: rgb(225 29 72 / {$a})";
    };

    // Trend chart (rows are periods): columns of the row totals, zero line, every n-th label.
    $series = $in['series'];
    $chart = null;
    if ($r['time'] && count($series) >= 2) {
        $W = 720; $H = 200; $padL = 8; $padB = 26; $padT = 16;
        $vals = array_column($series, 'value');
        $max = max(0, ...$vals); $min = min(0, ...$vals);
        $span = ($max - $min) ?: 1;
        $n = count($series);
        $slot = ($W - $padL * 2) / $n;
        $bw = max(2, min(38, $slot * 0.68));
        $y = fn (float $v): float => $padT + ($max - $v) / $span * ($H - $padT - $padB);
        $every = max(1, (int) ceil($n / 12));
        $chart = compact('W', 'H', 'padL', 'padB', 'slot', 'bw', 'y', 'every', 'n');
    }
@endphp
<x-filament-panels::page>
    <div class="myat-rep">
        {{ $this->form }}

        @if ($chips !== [])
            <div class="myat-rep-chips" role="list" aria-label="Ενεργά φίλτρα">
                @foreach ($chips as $chip)
                    <span class="myat-rep-chip" role="listitem">
                        <span class="myat-rep-chip-k">{{ $chip['label'] }}</span>
                        <span class="myat-rep-chip-v">{{ $chip['value'] }}</span>
                        <button type="button" wire:click="removeFilter('{{ $chip['key'] }}')" aria-label="Αφαίρεση φίλτρου {{ $chip['label'] }}">&times;</button>
                    </span>
                @endforeach
            </div>
        @endif

        @if ($r['rows'] !== [])
            <section class="myat-rep-kpis" aria-label="Σύνοψη">
                <div class="myat-rep-kpi myat-rep-kpi-main">
                    <span class="myat-rep-kpi-label">Σύνολο · {{ $r['measure'] }}</span>
                    <span class="myat-rep-kpi-value">{{ $fmt($in['total']) }}</span>
                    <span class="myat-rep-kpi-sub">{{ $in['rows'] }} {{ $r['time'] ? ($in['rows'] === 1 ? 'περίοδος' : 'περίοδοι') : ($in['rows'] === 1 ? 'γραμμή' : 'γραμμές') }}@if ($in['cols']) · {{ $in['cols'] }} {{ $in['cols'] === 1 ? 'στήλη' : 'στήλες' }}@endif</span>
                </div>
                @if ($in['last'])
                    <div class="myat-rep-kpi">
                        <span class="myat-rep-kpi-label">Τελευταία περίοδος · {{ $in['last']['label'] }}</span>
                        <span class="myat-rep-kpi-value">{{ $fmt($in['last']['value']) }}</span>
                        @if ($in['last']['change'] !== null)
                            @php $up = $in['last']['change'] >= 0; @endphp
                            <span @class(['myat-rep-delta', 'is-up' => $up, 'is-down' => ! $up])>
                                <x-filament::icon :icon="$up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" class="myat-rep-delta-icon" />
                                {{ ($up ? '+' : '').\Illuminate\Support\Number::format($in['last']['change'], maxPrecision: 1, locale: 'el') }}% από {{ $fmt($in['last']['prev']) }}
                            </span>
                        @endif
                    </div>
                @endif
                @if ($in['top'] !== [])
                    <div class="myat-rep-kpi">
                        <span class="myat-rep-kpi-label">Μεγαλύτερο · {{ $r['colLabel'] }}</span>
                        <span class="myat-rep-kpi-value myat-rep-kpi-name">{{ $in['top'][0]['label'] }}</span>
                        <span class="myat-rep-kpi-sub">{{ $fmt($in['top'][0]['value']) }} · {{ \Illuminate\Support\Number::format($in['top'][0]['share'], maxPrecision: 1, locale: 'el') }}% του συνόλου</span>
                    </div>
                @endif
            </section>

            @if ($chart || count($in['top']) > 1)
                <section @class(['myat-rep-charts', 'is-single' => ! $chart || count($in['top']) <= 1])>
                    @if ($chart)
                        <figure class="myat-rep-card">
                            <figcaption class="myat-rep-card-title">{{ $r['measure'] }} ανά {{ mb_strtolower($r['rowLabel']) }}</figcaption>
                            <svg class="myat-rep-trend" viewBox="0 0 {{ $chart['W'] }} {{ $chart['H'] }}" role="img"
                                aria-label="{{ $r['measure'] }} ανά περίοδο: {{ collect($series)->map(fn ($p) => $p['label'].' '.$short($p['value']))->implode(', ') }}">
                                <line x1="0" x2="{{ $chart['W'] }}" y1="{{ $chart['y'](0) }}" y2="{{ $chart['y'](0) }}" class="myat-rep-zero" />
                                @foreach ($series as $i => $p)
                                    @php
                                        $x = $chart['padL'] + $i * $chart['slot'] + ($chart['slot'] - $chart['bw']) / 2;
                                        $top = min($chart['y']($p['value']), $chart['y'](0));
                                        $h = max(1, abs($chart['y']($p['value']) - $chart['y'](0)));
                                        $isLast = $i === $chart['n'] - 1;
                                    @endphp
                                    <rect x="{{ round($x, 1) }}" y="{{ round($top, 1) }}" width="{{ round($chart['bw'], 1) }}" height="{{ round($h, 1) }}" rx="3"
                                        @class(['myat-rep-bar', 'is-neg' => $p['value'] < 0, 'is-last' => $isLast])>
                                        <title>{{ $p['label'] }}: {{ $fmt($p['value']) }}</title>
                                    </rect>
                                    @if ($i % $chart['every'] === 0 || $isLast)
                                        <text x="{{ round($x + $chart['bw'] / 2, 1) }}" y="{{ $chart['H'] - 8 }}" text-anchor="middle" class="myat-rep-axis">{{ preg_replace('/^\d{2}\//', '', $p['label']) }}</text>
                                    @endif
                                    @if ($isLast)
                                        <text x="{{ round($x + $chart['bw'] / 2, 1) }}" y="{{ round($top - 5, 1) }}" text-anchor="end" class="myat-rep-axis myat-rep-axis-strong">{{ $short($p['value']) }}</text>
                                    @endif
                                @endforeach
                            </svg>
                        </figure>
                    @endif
                    @if (count($in['top']) > 1)
                        <div class="myat-rep-card">
                            <div class="myat-rep-card-title">Κατανομή ανά {{ mb_strtolower($r['colLabel']) }}</div>
                            <ol class="myat-rep-rank">
                                @foreach ($in['top'] as $t)
                                    <li>
                                        <span class="myat-rep-rank-name" title="{{ $t['label'] }}">{{ $t['label'] }}</span>
                                        <span class="myat-rep-rank-val">{{ $short($t['value']) }} <small>{{ \Illuminate\Support\Number::format($t['share'], maxPrecision: 0, locale: 'el') }}%</small></span>
                                        <span class="myat-rep-rank-track" aria-hidden="true"><span @class(['myat-rep-rank-fill', 'is-neg' => $t['value'] < 0]) style="width: {{ max(2, min(100, abs($t['share']))) }}%"></span></span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                </section>
            @endif

            <div class="myat-rep-tablebar">
                <span class="myat-rep-card-title">Αναλυτικά</span>
                <label class="myat-rep-switch">
                    <input type="checkbox" wire:model.live="heatmap">
                    <span>Χρωματισμός τιμών</span>
                </label>
            </div>
        @endif

    <div class="myat-pivot-ctn" wire:loading.class="opacity-60">
        @if ($r['rows'] === [])
            <div class="myat-pivot-empty">Δεν βρέθηκαν εγγραφές.</div>
        @else
            <table class="myat-pivot">
                <thead>
                    <tr>
                        <th class="myat-pivot-rowhead">{{ $r['rowLabel'] }}</th>
                        @foreach ($r['cols'] as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        @if ($r['hasCols'])
                            <th class="myat-pivot-total">Σύνολο</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Months under their year with the year's subtotal first (PHPRunner "stepped"), or plain rows.
                        $sections = $r['groups'] !== [] ? $r['groups'] : [['label' => null, 'rows' => array_keys($r['rows'])]];
                    @endphp
                    @foreach ($sections as $section)
                        @if ($section['label'] !== null)
                            <tr class="myat-pivot-group">
                                <th class="myat-pivot-rowhead">{{ $section['label'] }}</th>
                                @foreach ($r['cols'] as $colKey => $label)
                                    <td>{{ $fmt($section['cells'][$colKey] ?? null) }}</td>
                                @endforeach
                                @if ($r['hasCols'])
                                    <td class="myat-pivot-total">{{ $fmt($section['total']) }}</td>
                                @endif
                            </tr>
                        @endif
                        @foreach ($section['rows'] as $rowKey)
                            <tr @class(['myat-pivot-child' => $section['label'] !== null])>
                                <th class="myat-pivot-rowhead">{{ $r['rows'][$rowKey] }}</th>
                                @foreach ($r['cols'] as $colKey => $label)
                                    @php $v = $r['cells'][$rowKey][$colKey] ?? null; @endphp
                                    <td style="{{ $heat($v) }}" @class(['is-neg' => $v < 0, 'is-empty' => ! $v])>{{ $v ? $fmt($v) : '—' }}</td>
                                @endforeach
                                @if ($r['hasCols'])
                                    <td @class(['myat-pivot-total', 'is-neg' => $r['rowTotals'][$rowKey] < 0])>{{ $fmt($r['rowTotals'][$rowKey]) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="myat-pivot-rowhead">Σύνολο</th>
                        @foreach ($r['cols'] as $colKey => $label)
                            <td>{{ $fmt($r['colTotals'][$colKey] ?? 0) }}</td>
                        @endforeach
                        @if ($r['hasCols'])
                            <td class="myat-pivot-total">{{ $fmt($r['total']) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>
    </div>
</x-filament-panels::page>
