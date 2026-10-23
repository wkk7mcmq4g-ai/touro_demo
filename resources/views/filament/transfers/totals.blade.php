{{-- Totals of what the transfers list shows now (TransferTotals widget). --}}
<x-filament-widgets::widget>
    <div class="myat-totals" role="group" aria-label="Σύνολα της λίστας">
        <div class="myat-total">
            <span class="myat-total-label">Transfers</span>
            <span class="myat-total-value">{{ number_format($jobs, 0, ',', '.') }}</span>
        </div>
        @foreach ($money as $label => $value)
            <div class="myat-total">
                <span class="myat-total-label">{{ $label }}</span>
                <span class="myat-total-value">{{ \Illuminate\Support\Number::currency($value, 'EUR', 'el') }}</span>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
