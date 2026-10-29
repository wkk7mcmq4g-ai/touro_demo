{{--
    «Σήμερα» (App\Filament\Pages\TodayJobs): the day's jobs as cards, for the phone. Day buttons on top,
    then a filter (all / without a vehicle) and a search that works in the browser. Each card: time,
    type, route, passengers, the driver big with call/WhatsApp, the passenger with call/WhatsApp.
--}}
@php($cards = \App\Filament\Pages\TodayJobs::cards($this->day()))
@php($canFix = $this->canFix())
<x-filament-panels::page>
    <div class="myat-today" wire:key="today-{{ $this->day()->toDateString() }}"
        x-data="{
            show: 'all', q: '',
            norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
            match(el) {
                if (this.show === 'open' && el.dataset.open !== '1') return false
                const q = this.norm(this.q).trim()
                return q === '' || this.norm(el.dataset.text).includes(q)
            },
        }">

        <div class="myat-today-bar">
            <div class="myat-cal-step" role="group" aria-label="Ημέρα">
                <button type="button" wire:click="move(-1)" aria-label="Προηγούμενη ημέρα"><x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" /></button>
                <button type="button" wire:click="move(0)" @disabled($this->day()->isToday())>Σήμερα</button>
                <button type="button" wire:click="move(1)" aria-label="Επόμενη ημέρα"><x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" /></button>
            </div>
            <input type="date" class="myat-tl-date" wire:model.live="date" aria-label="Ημερομηνία">
            <span class="myat-today-date">{{ $this->day()->locale('el')->translatedFormat('l j F') }}</span>
            <x-filament::loading-indicator class="h-5 w-5 text-gray-400" wire:loading />
        </div>

        @php($open = count(array_filter($cards, fn ($c) => $c['coach'] === '')))
        <div class="myat-today-filter">
            <button type="button" :class="{ 'is-on': show === 'all' }" x-on:click="show = 'all'">Όλα <b>{{ count($cards) }}</b></button>
            <button type="button" :class="{ 'is-on': show === 'open' }" x-on:click="show = 'open'" @class(['is-warn' => $open > 0])>Χωρίς όχημα <b>{{ $open }}</b></button>
            <label class="myat-today-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" />
                <input type="search" x-model="q" placeholder="Όνομα, όχημα, οδηγός…">
            </label>
        </div>

        @forelse ($cards as $c)
            <article class="myat-today-card @if ($c['coach'] === '') is-open @endif" x-show="match($el)"
                data-open="{{ $c['coach'] === '' ? '1' : '0' }}"
                data-text="{{ implode(' ', [$c['id'], $c['lead'], $c['customer'], $c['coach'], $c['driver'], $c['from'], $c['to'], $c['flight'], $c['assistant']]) }}">
                <header class="myat-today-head">
                    <span class="myat-today-time">{{ $c['time'] !== '' ? $c['time'] : '—' }}</span>
                    <span class="myat-today-type">{{ $c['type'] }}@if ($c['pax']) · {{ $c['pax'] }} άτ.@endif</span>
                    <a class="myat-today-id" href="{{ $c['url'] }}">#{{ $c['id'] }}</a>
                </header>

                <p class="myat-today-route">
                    <span>{{ $c['from'] ?: '—' }}</span>
                    @if ($c['type'] !== 'Tour' || $c['to'] !== '')
                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-4 w-4" />
                        <span>{{ $c['to'] ?: '—' }}</span>
                    @endif
                </p>
                @if ($c['flight'] !== '')
                    <p class="myat-today-flight"><x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />{{ $c['flight'] }}</p>
                @endif

                <div class="myat-today-driver">
                    @if ($c['coach'] === '')
                        <p class="myat-today-none">Χωρίς όχημα</p>
                        @if ($canFix)
                            <button type="button" class="myat-today-fix"
                                x-on:click="$dispatch('myat-cell', { id: {{ $c['id'] }}, field: 'CoachCompanyID', value: '' })">
                                <x-filament::icon icon="heroicon-m-truck" class="h-4 w-4" />Όχημα
                            </button>
                        @endif
                    @else
                        <div>
                            <p class="myat-today-who">{{ $c['driver'] !== '' ? $c['driver'] : 'Χωρίς οδηγό' }}</p>
                            <p class="myat-today-sub">{{ $c['coach'] }}{{ $c['ownVehicle'] ? '' : ' · συνεργάτης' }}</p>
                        </div>
                        @if ($c['driverPhone'])
                            <div class="myat-today-call">
                                <a href="{{ $c['driverPhone']['tel'] }}" aria-label="Κλήση οδηγού" title="Κλήση {{ $c['driverPhone']['shown'] }}"><x-filament::icon icon="heroicon-m-phone" class="h-5 w-5" /></a>
                                <a href="{{ $c['driverPhone']['wa'] }}" target="_blank" rel="noopener" class="is-wa" aria-label="WhatsApp οδηγού" title="WhatsApp"><x-filament::icon icon="heroicon-m-chat-bubble-oval-left" class="h-5 w-5" /></a>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="myat-today-pax">
                    <div>
                        <p class="myat-today-lead">{{ $c['lead'] ?: '—' }}</p>
                        <p class="myat-today-sub">{{ $c['customer'] }}@if ($c['leadPhone']) · {{ $c['leadPhone']['shown'] }}@endif</p>
                    </div>
                    @if ($c['leadPhone'])
                        <div class="myat-today-call">
                            <a href="{{ $c['leadPhone']['tel'] }}" aria-label="Κλήση επιβάτη" title="Κλήση"><x-filament::icon icon="heroicon-m-phone" class="h-5 w-5" /></a>
                            <a href="{{ $c['leadPhone']['wa'] }}" target="_blank" rel="noopener" class="is-wa" aria-label="WhatsApp επιβάτη" title="WhatsApp"><x-filament::icon icon="heroicon-m-chat-bubble-oval-left" class="h-5 w-5" /></a>
                        </div>
                    @endif
                </div>

                @if ($c['assistant'] !== '' || $c['notes'] !== '' || $c['status'] !== 'Pending')
                    <footer class="myat-today-foot">
                        @if ($c['status'] !== '')<span class="myat-today-status">{{ $c['status'] }}</span>@endif
                        @if ($c['assistant'] !== '')<span>Ξεναγός: {{ $c['assistant'] }}</span>@endif
                        @if ($c['notes'] !== '')<p class="myat-today-notes">{{ $c['notes'] }}</p>@endif
                    </footer>
                @endif
            </article>
        @empty
            <p class="myat-today-empty">Δεν υπάρχουν δρομολόγια αυτή την ημέρα.</p>
        @endforelse

        @if (count($cards))
            <p class="myat-today-empty" x-cloak
                x-show="(q, show) && ! [...$root.querySelectorAll('.myat-today-card')].some((el) => match(el))">Τίποτα με αυτό το φίλτρο.</p>
        @endif
    </div>

    @if ($canFix)
        @include('filament.transfers.cell-editor')
    @endif
</x-filament-panels::page>
