@php
    $r = $this->result();
    $fmt = fn (?float $v): string => $v === null ? '' : ($r['money']
        ? \Illuminate\Support\Number::currency($v, 'EUR', 'el')
        : \Illuminate\Support\Number::format($v, maxPrecision: 2, locale: 'el'));
@endphp
<x-filament-panels::page>
    {{ $this->form }}

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
                                    <td>{{ $fmt($r['cells'][$rowKey][$colKey] ?? null) }}</td>
                                @endforeach
                                @if ($r['hasCols'])
                                    <td class="myat-pivot-total">{{ $fmt($r['rowTotals'][$rowKey]) }}</td>
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
</x-filament-panels::page>
