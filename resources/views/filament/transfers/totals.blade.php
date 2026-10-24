{{-- Totals of what the transfers list shows now (TransferTotals widget). Phones: one slim strip that opens the rest. --}}
@php
    $eur = fn (float $v): string => \Illuminate\Support\Number::currency($v, 'EUR', 'el');
    $sales = $money['Πωλήσεις'] ?? null;
    $rest = array_diff_key($money, ['Πωλήσεις' => true]);
@endphp
<x-filament-widgets::widget>
    <div
        x-data="{
            open: (() => { try { return localStorage.getItem('myat-totals-open') === '1' } catch (e) { return false } })(),
            toggle() { this.open = ! this.open; try { localStorage.setItem('myat-totals-open', this.open ? '1' : '0') } catch (e) {} },
        }"
        :class="{ 'is-open': open }"
        class="myat-totals-wrap"
    >
        @if ($money !== [])
            <button type="button" class="myat-totals-strip" x-on:click="toggle()" :aria-expanded="open.toString()" aria-controls="myat-totals-grid" title="Περισσότερα σύνολα">
                <span><strong>{{ number_format($jobs, 0, ',', '.') }}</strong> transfers</span>
                @if ($sales !== null)
                    <span class="myat-totals-strip-sep" aria-hidden="true">·</span>
                    <span><strong>{{ $eur($sales) }}</strong> πωλήσεις</span>
                @endif
                <x-filament::icon icon="heroicon-m-chevron-down" class="myat-totals-strip-icon" />
            </button>
        @else
            <div class="myat-totals-strip is-static"><span><strong>{{ number_format($jobs, 0, ',', '.') }}</strong> transfers</span></div>
        @endif

        <div class="myat-totals" id="myat-totals-grid" role="group" aria-label="Σύνολα της λίστας">
            <div class="myat-total">
                <span class="myat-total-label">Transfers</span>
                <span class="myat-total-value">{{ number_format($jobs, 0, ',', '.') }}</span>
            </div>
            @foreach ($money as $label => $value)
                <div @class(['myat-total', 'myat-total-sales' => $label === 'Πωλήσεις'])>
                    <span class="myat-total-label">{{ $label }}</span>
                    <span class="myat-total-value">{{ $eur($value) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
