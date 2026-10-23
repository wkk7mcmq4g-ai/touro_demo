@php
    $money = fn (float $v): string => \Illuminate\Support\Number::currency($v, 'EUR', 'el');
    $net = $this->netPosition();
@endphp
<x-filament-panels::page>
    <div class="myat-netpos-hero">
        <div class="myat-netpos-hero-label">ΣΥΝΟΛΙΚΗ ΘΕΣΗ</div>
        <div class="myat-netpos-hero-value">{{ $money($net) }}</div>
        <div class="myat-netpos-hero-note">Συνολικά έσοδα + συναλλαγές + (απαιτήσεις − υποχρεώσεις)</div>
    </div>

    <div class="myat-netpos-grid">
        @foreach ($this->groups() as $group)
            <x-filament::section :heading="$group['title']">
                <dl class="myat-netpos-list">
                    @foreach ($group['rows'] as $row)
                        <div @class(['myat-netpos-row', 'myat-netpos-total' => $row['total'] ?? false])>
                            <dt>{{ $row['label'] }}</dt>
                            {{-- PHPRunner shows negative ΙΚΕ, εταιρία and cash difference in red. --}}
                            <dd @class(['myat-neg' => ($row['signed'] ?? false) && $row['value'] < 0])>{{ $money($row['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
