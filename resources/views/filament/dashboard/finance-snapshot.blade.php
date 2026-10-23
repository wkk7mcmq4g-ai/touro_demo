@php
    $money = fn (float $v): string => \Illuminate\Support\Number::currency($v, 'EUR', 'el');
@endphp
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Οικονομικά</x-slot>
        <x-slot name="description">{{ ucfirst($month) }}</x-slot>

        <div class="myat-fin-head">
            <span class="myat-kpi-label">Πωλήσεις μήνα</span>
            <div class="myat-fin-value">{{ $money($sales) }}</div>
            @if ($change !== null)
                <div @class(['myat-fin-trend', 'is-up' => $change >= 0, 'is-down' => $change < 0])>
                    <x-filament::icon :icon="$change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'" />
                    {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 0, ',', '.') }}% έως σήμερα, σε σχέση με τον προηγούμενο μήνα
                </div>
            @endif
        </div>

        <dl class="myat-fin-pair">
            <div><dt>Απαιτήσεις</dt><dd>{{ $money($receivable) }}</dd></div>
            <div><dt>Υποχρεώσεις</dt><dd>{{ $money($payable) }}</dd></div>
        </dl>

        @if ($top->isNotEmpty())
            <div class="myat-fin-top">
                <span class="myat-kpi-label">Κορυφαίοι συνεργάτες</span>
                <ul>
                    @foreach ($top as $row)
                        <li>
                            <span class="myat-fin-name">{{ $row->name }}</span>
                            <span class="myat-fin-amount">{{ $money((float) $row->total) }}</span>
                            <span class="myat-fin-bar"><span style="width: {{ max(3, round((float) $row->total / $max * 100)) }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
