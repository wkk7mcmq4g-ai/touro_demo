{{-- Transfers toolbar: ready-made views, the same for everyone (App\Support\TransferViews, ListTransfers::applyView). --}}
@php($active = $this->activeView())
<x-filament::dropdown placement="bottom-end" width="xs">
    <x-slot name="trigger">
        <button type="button" @class(['myat-quick-toggle', 'myat-date-menu', 'is-on' => $active !== null]) title="Έτοιμες προβολές">
            <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
            <span>{{ $active !== null ? \App\Support\TransferViews::VIEWS[$active]['label'] : 'Προβολές' }}</span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 opacity-60" />
        </button>
    </x-slot>
    <x-filament::dropdown.list>
        @foreach (\App\Support\TransferViews::VIEWS as $key => $view)
            <x-filament::dropdown.list.item
                :icon="$active === $key ? 'heroicon-m-check' : $view['icon']"
                :color="$active === $key ? 'primary' : 'gray'"
                wire:click="applyView('{{ $key }}')" x-on:click="close()">
                {{ $view['label'] }}
            </x-filament::dropdown.list.item>
        @endforeach
        @if ($active !== null)
            <x-filament::dropdown.list.item icon="heroicon-m-x-mark" color="danger" wire:click="applyView('')" x-on:click="close()">
                Καθαρισμός
            </x-filament::dropdown.list.item>
        @endif
    </x-filament::dropdown.list>
</x-filament::dropdown>
