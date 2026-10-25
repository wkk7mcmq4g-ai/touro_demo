{{--
    Partner jobs: a direction switch (jobs partners gave us / jobs we gave them) and the partners
    as tabs. Partners with jobs not yet copied into our transfers carry a badge with how many
    (PartnerJobs::newCounts, upcoming and not cancelled); a partner whose database can't be
    read from here is shown faded.
--}}
@php
    $short = config('business.short_name');
    $directions = collect([
        'for-us' => ['label' => 'Προς '.$short, 'title' => 'Αναθέσεις προς '.$short, 'icon' => 'heroicon-m-arrow-down-tray'],
        'by-us' => ['label' => 'Από '.$short, 'title' => 'Αναθέσεις από '.$short, 'icon' => 'heroicon-m-arrow-up-tray'],
    ])->filter(fn ($d, $key) => \App\Filament\Pages\PartnerJobs::partners($key) !== []);
    $newCounts = \App\Filament\Pages\PartnerJobs::newCounts();
    $totalNew = array_sum($newCounts);
    $partners = \App\Filament\Pages\PartnerJobs::partners($this->direction);
@endphp
<x-filament-panels::page>
    <div class="myat-pj-nav">
        @if ($directions->count() > 1)
            <div class="myat-pj-dir" role="tablist" aria-label="Κατεύθυνση">
                @foreach ($directions as $key => $d)
                    <button type="button" role="tab" aria-selected="{{ $this->direction === $key ? 'true' : 'false' }}"
                            @class(['is-active' => $this->direction === $key]) title="{{ $d['title'] }}"
                            wire:click="switchDirection('{{ $key }}')">
                        <x-filament::icon :icon="$d['icon']" class="h-4 w-4" />
                        {{ $d['label'] }}
                        @if ($key === 'for-us' && $totalNew > 0)
                            <span class="myat-pj-count">{{ $totalNew }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif

        <div class="myat-pj-partners" role="tablist" aria-label="{{ $directions[$this->direction]['title'] ?? 'Συνεργάτες' }}">
            @foreach ($partners as $key => $label)
                @php
                    $new = $this->direction === 'for-us' ? ($newCounts[$key] ?? 0) : 0;
                    $offline = $this->direction === 'for-us' && ! array_key_exists($key, $newCounts);
                @endphp
                <button type="button" role="tab" aria-selected="{{ $partner === $key ? 'true' : 'false' }}"
                        @class(['myat-pj-partner', 'is-active' => $partner === $key, 'has-new' => $new > 0, 'is-offline' => $offline])
                        title="{{ $offline ? 'Η βάση του συνεργάτη δεν διαβάζεται από εδώ' : ($new > 0 ? $new.' νέες αναθέσεις για μεταφορά' : $label) }}"
                        wire:click="show('{{ $key }}', '{{ $this->direction }}')">
                    <span class="myat-pj-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($label, 0, 1)) }}</span>
                    <span>{{ $label }}</span>
                    @if ($new > 0)
                        <span class="myat-pj-count"><span class="myat-pj-dot" aria-hidden="true"></span>{{ $new }} νέ{{ $new === 1 ? 'α' : 'ες' }}</span>
                    @endif
                </button>
            @endforeach
            <x-filament::loading-indicator class="h-5 w-5 text-gray-400" wire:loading wire:target="show,switchDirection" />
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
