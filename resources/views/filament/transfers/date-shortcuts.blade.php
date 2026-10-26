{{-- Transfers toolbar: PHPRunner's "Φίλτρα" date shortcuts (ListTransfers::applyPeriod). --}}
@php($active = $this->activePeriod())
<x-filament::dropdown placement="bottom-end" width="xs">
    <x-slot name="trigger">
        <button type="button" @class(['myat-quick-toggle', 'myat-date-menu', 'is-on' => $active !== null]) title="Γρήγορες ημερομηνίες">
            <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />
            <span>{{ $active !== null ? \App\Filament\Resources\Transfers\Tables\TransfersTable::PERIODS[$active] : 'Ημερομηνίες' }}</span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 opacity-60" />
        </button>
    </x-slot>
    <x-filament::dropdown.list>
        @foreach (\App\Filament\Resources\Transfers\Tables\TransfersTable::PERIODS as $key => $label)
            <x-filament::dropdown.list.item
                :icon="$key === 'issues7' ? 'heroicon-m-exclamation-triangle' : ($active === $key ? 'heroicon-m-check' : null)"
                :color="$active === $key ? 'primary' : 'gray'"
                wire:click="applyPeriod('{{ $key }}')" x-on:click="close()">
                {{ $label }}
            </x-filament::dropdown.list.item>
        @endforeach
        @if ($active !== null)
            <x-filament::dropdown.list.item icon="heroicon-m-x-mark" color="danger" wire:click="applyPeriod('')" x-on:click="close()">
                Καθαρισμός
            </x-filament::dropdown.list.item>
        @endif
    </x-filament::dropdown.list>
</x-filament::dropdown>
