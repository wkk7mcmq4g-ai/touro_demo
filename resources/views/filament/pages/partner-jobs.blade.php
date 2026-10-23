<x-filament-panels::page>
    <div class="flex flex-col gap-2">
        @foreach (['for-us' => 'Αναθέσεις προς MyAT', 'by-us' => 'Αναθέσεις από MyAT'] as $direction => $heading)
            @if (\App\Filament\Pages\PartnerJobs::partners($direction) !== [])
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-44 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $heading }}</span>
                    @foreach (\App\Filament\Pages\PartnerJobs::partners($direction) as $key => $label)
                        <x-filament::button size="sm"
                            :color="$partner === $key && $this->direction === $direction ? 'primary' : 'gray'"
                            wire:click="show('{{ $key }}', '{{ $direction }}')">{{ $label }}</x-filament::button>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
