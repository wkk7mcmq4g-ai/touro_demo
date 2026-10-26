{{--
    Το μενού μου (App\Filament\Pages\MyMenu): favourites on top, then one card per group. Items and
    groups are dragged by their handle (SortableJS, which Filament already loads as window.Sortable);
    every drop is saved at once and the side menu redraws.
--}}
@php($menu = $this->editor())
<x-filament-panels::page>
    <div
        class="myat-menu"
        x-data="{
            init() {
                this.$nextTick(() => this.bind())
                // Lists drawn again after a save need their dragging set up again.
                Livewire.hook('morphed', ({ component }) => { if (component.id === $wire.$id) this.$nextTick(() => this.bind()) })
            },
            bind() {
                const lists = (sel) => this.$root.querySelectorAll(sel)
                const make = (el, opts) => { if (! el._myatSortable) el._myatSortable = window.Sortable.create(el, { animation: 160, handle: '[data-handle]', ghostClass: 'is-ghost', ...opts }) }
                make(this.$refs.groups, { draggable: '[data-group]:not([data-top])', handle: '[data-group-handle]', onEnd: () => this.saveOrder() })
                lists('[data-items]').forEach((el) => make(el, { group: 'myat-menu-items', draggable: '[data-item]', onEnd: () => this.saveOrder() }))
                make(this.$refs.favourites, { draggable: '[data-item]', onEnd: () => $wire.saveFavourites([...this.$refs.favourites.querySelectorAll('[data-item]')].map((i) => i.dataset.item)) })
            },
            saveOrder() {
                const order = [...this.$refs.groups.querySelectorAll('[data-group]')].map((g) => ({
                    id: g.dataset.group,
                    items: [...g.querySelectorAll('[data-item]')].map((i) => i.dataset.item),
                }))
                $wire.saveOrder(order)
            },
        }"
    >
        <p class="myat-menu-intro">
            Σύρετε από τη λαβή <x-filament::icon icon="heroicon-m-bars-2" class="myat-menu-inline-icon" /> για να αλλάξετε σειρά ή ομάδα,
            πατήστε το αστέρι για τα <b>Αγαπημένα</b> και το μάτι για να κρύψετε κάτι που δεν χρησιμοποιείτε.
            Οι αλλαγές αποθηκεύονται αμέσως στο προφίλ σας.
        </p>

        {{-- Favourites --}}
        <section class="myat-menu-card is-favourites">
            <header class="myat-menu-card-h">
                <x-filament::icon icon="heroicon-s-star" class="myat-menu-star-h" />
                <span class="myat-menu-card-title">{{ \App\Support\PersonalMenu::FAVOURITES }}</span>
                <span class="myat-menu-count">{{ count($menu['favourites']) }}</span>
            </header>
            <ul class="myat-menu-items" x-ref="favourites" wire:key="favs-{{ md5(json_encode(array_column($menu['favourites'], 'key'))) }}">
                @forelse ($menu['favourites'] as $item)
                    <li class="myat-menu-item" data-item="{{ $item['key'] }}" wire:key="fav-{{ $item['key'] }}">
                        <span class="myat-menu-handle" data-handle title="Σύρετε"><x-filament::icon icon="heroicon-m-bars-2" /></span>
                        @if ($item['icon'])<x-filament::icon :icon="$item['icon']" class="myat-menu-icon" />@endif
                        <span class="myat-menu-label">{{ $item['label'] }}</span>
                        <button type="button" class="myat-menu-btn is-on" wire:click="toggleFavourite(@js($item['key']))" title="Αφαίρεση από τα αγαπημένα">
                            <x-filament::icon icon="heroicon-s-star" />
                        </button>
                    </li>
                @empty
                    <li class="myat-menu-empty">Πατήστε <x-filament::icon icon="heroicon-o-star" class="myat-menu-inline-icon" /> σε όποια σελίδα θέλετε να έχετε πρώτη.</li>
                @endforelse
            </ul>
        </section>

        {{-- Groups --}}
        <div class="myat-menu-groups" x-ref="groups" wire:key="groups-{{ md5(json_encode($menu['groups'])) }}">
            @foreach ($menu['groups'] as $group)
                <section class="myat-menu-card" data-group="{{ $group['id'] }}" @if ($group['top']) data-top @endif wire:key="g-{{ $group['id'] }}"
                    x-data="{ editing: false, name: @js($group['label']) }">
                    <header class="myat-menu-card-h">
                        @unless ($group['top'])
                            <span class="myat-menu-handle" data-group-handle title="Σύρετε την ομάδα"><x-filament::icon icon="heroicon-m-arrows-up-down" /></span>
                        @endunless
                        <template x-if="! editing">
                            <span class="myat-menu-card-title" @unless ($group['top']) x-on:dblclick="editing = true; $nextTick(() => { $refs.name.focus(); $refs.name.select() })" @endunless>
                                {{ $group['top'] ? 'Πάνω από τις ομάδες' : $group['label'] }}
                                @if (! $group['own'] && ! $group['top'] && $group['label'] !== $group['from'])
                                    <span class="myat-menu-was">({{ $group['from'] }})</span>
                                @endif
                            </span>
                        </template>
                        <template x-if="editing">
                            <input x-ref="name" x-model="name" maxlength="40" class="myat-menu-rename"
                                x-on:keydown.enter.prevent="editing = false; $wire.renameGroup(@js($group['id']), name)"
                                x-on:keydown.escape="editing = false; name = @js($group['label'])"
                                x-on:blur="if (editing) { editing = false; $wire.renameGroup(@js($group['id']), name) }">
                        </template>
                        <span class="myat-menu-count">{{ count($group['items']) }}</span>
                        @unless ($group['top'])
                            <button type="button" class="myat-menu-btn" x-on:click="editing = true; $nextTick(() => { $refs.name.focus(); $refs.name.select() })" title="Μετονομασία">
                                <x-filament::icon icon="heroicon-m-pencil-square" />
                            </button>
                        @endunless
                        @if ($group['own'])
                            <button type="button" class="myat-menu-btn is-danger" wire:click="deleteGroup(@js($group['id']))"
                                title="Διαγραφή ομάδας (οι σελίδες της γυρίζουν στις ομάδες τους)">
                                <x-filament::icon icon="heroicon-m-trash" />
                            </button>
                        @endif
                    </header>
                    <ul class="myat-menu-items" data-items data-empty="Σύρετε σελίδες εδώ.">
                        @foreach ($group['items'] as $item)
                            <li class="myat-menu-item {{ $item['hidden'] ? 'is-hidden' : '' }}" data-item="{{ $item['key'] }}" wire:key="i-{{ $item['key'] }}">
                                <span class="myat-menu-handle" data-handle title="Σύρετε"><x-filament::icon icon="heroicon-m-bars-2" /></span>
                                @if ($item['icon'])<x-filament::icon :icon="$item['icon']" class="myat-menu-icon" />@endif
                                <span class="myat-menu-label">
                                    {{ $item['label'] }}
                                    @if ($item['home'] !== ($group['from'] ?? null) && $item['home'] !== '')
                                        <span class="myat-menu-was">{{ $item['home'] }}</span>
                                    @endif
                                </span>
                                <button type="button" class="myat-menu-btn {{ $item['favourite'] ? 'is-on' : '' }}" wire:click="toggleFavourite(@js($item['key']))"
                                    title="{{ $item['favourite'] ? 'Αφαίρεση από τα αγαπημένα' : 'Στα αγαπημένα' }}">
                                    <x-filament::icon :icon="$item['favourite'] ? 'heroicon-s-star' : 'heroicon-o-star'" />
                                </button>
                                <button type="button" class="myat-menu-btn" wire:click="toggleHidden(@js($item['key']))"
                                    title="{{ $item['hidden'] ? 'Εμφάνιση στο μενού' : 'Απόκρυψη από το μενού' }}">
                                    <x-filament::icon :icon="$item['hidden'] ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
