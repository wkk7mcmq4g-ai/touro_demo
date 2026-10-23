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
                    @foreach ($r['rows'] as $rowKey => $rowLabel)
                        <tr>
                            <th class="myat-pivot-rowhead">{{ $rowLabel }}</th>
                            @foreach ($r['cols'] as $colKey => $label)
                                <td>{{ $fmt($r['cells'][$rowKey][$colKey] ?? null) }}</td>
                            @endforeach
                            @if ($r['hasCols'])
                                <td class="myat-pivot-total">{{ $fmt($r['rowTotals'][$rowKey]) }}</td>
                            @endif
                        </tr>
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
