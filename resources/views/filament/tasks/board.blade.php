{{--
    One project (App\Filament\Pages\Tasks\ProjectBoard): toolbar (Πίνακας / Λίστα, search, filters),
    the Kanban board (cards dragged with SortableJS, Filament's window.Sortable) or the list, and the
    task panel on the right when ?task= is set.
--}}
@php
    use App\Support\Tasks;
    $tasks = $this->tasks();
    $people = Tasks::people();
    $panel = $this->panel();
    $total = count($tasks);
    $done = collect($tasks)->where('status', 'done')->count();
@endphp
<x-filament-panels::page>
    <div class="myat-tk" style="--project: {{ $this->record()->Color ?: '#0096ff' }}">
        {{-- Toolbar --}}
        <div class="myat-tk-bar">
            <div class="myat-tk-seg" role="tablist">
                <button type="button" class="{{ $mode === 'board' ? 'is-active' : '' }}" wire:click="$set('mode', 'board')">
                    <x-filament::icon icon="heroicon-m-view-columns" /> Πίνακας
                </button>
                <button type="button" class="{{ $mode === 'list' ? 'is-active' : '' }}" wire:click="$set('mode', 'list')">
                    <x-filament::icon icon="heroicon-m-list-bullet" /> Λίστα
                </button>
            </div>
            <label class="myat-tk-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                <input type="search" placeholder="Αναζήτηση εργασιών…" wire:model.live.debounce.350ms="search">
            </label>
            <select class="myat-tk-select" wire:model.live="who" aria-label="Υπεύθυνος">
                <option value="">Όλοι οι υπεύθυνοι</option>
                <option value="me">Εγώ</option>
                <option value="none">Χωρίς υπεύθυνο</option>
                @foreach ($people as $user => $name)
                    <option value="{{ $user }}">{{ $name }}</option>
                @endforeach
            </select>
            <select class="myat-tk-select" wire:model.live="priority" aria-label="Προτεραιότητα">
                <option value="">Κάθε προτεραιότητα</option>
                @foreach (Tasks::PRIORITIES as $key => [$label])
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @if ($this->filtered())
                <button type="button" class="myat-tk-clear" wire:click="clearFilters">
                    <x-filament::icon icon="heroicon-m-x-mark" /> Καθαρισμός
                </button>
            @endif
            <div class="myat-tk-bar-end">
                <span class="myat-tk-summary" title="{{ $done }}/{{ $total }} ολοκληρωμένες">
                    <span class="myat-tk-progress is-small"><span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span></span>
                    {{ $done }}/{{ $total }}
                </span>
                <button type="button" class="myat-tk-star {{ $this->starred() ? 'is-on' : '' }}" wire:click="toggleStar"
                    title="{{ $this->starred() ? 'Αφαίρεση από τα αγαπημένα' : 'Στα αγαπημένα (και στο πλαϊνό μενού)' }}">
                    <x-filament::icon :icon="$this->starred() ? 'heroicon-s-star' : 'heroicon-o-star'" />
                </button>
            </div>
        </div>

        @if ($mode === 'board')
            {{-- Kanban --}}
            <div class="myat-tk-board"
                x-data="{
                    init() {
                        this.$nextTick(() => this.bind())
                        Livewire.hook('morphed', ({ component }) => { if (component.id === $wire.$id) this.$nextTick(() => this.bind()) })
                    },
                    bind() {
                        this.$root.querySelectorAll('[data-status]').forEach((el) => {
                            if (el._myatSortable) return
                            el._myatSortable = window.Sortable.create(el, {
                                group: 'myat-tasks', draggable: '[data-task]', animation: 160, ghostClass: 'is-ghost', chosenClass: 'is-chosen',
                                delay: 120, delayOnTouchOnly: true, filter: 'button, a, input', preventOnFilter: false,
                                onEnd: (e) => {
                                    const to = e.to
                                    if (e.from === to && e.oldIndex === e.newIndex) return
                                    $wire.moveTask(Number(e.item.dataset.task), to.dataset.status, [...to.querySelectorAll('[data-task]')].map((c) => Number(c.dataset.task)))
                                },
                            })
                        })
                    },
                }">
                @foreach (Tasks::STATUSES as $status => [$label, $color])
                    @php($cards = array_values(array_filter($tasks, fn ($t) => $t['status'] === $status)))
                    <section class="myat-tk-col" style="--col: {{ $color }}" wire:key="col-{{ $status }}-{{ md5(json_encode(array_column($cards, 'id'))) }}">
                        <header class="myat-tk-col-h">
                            <span class="myat-tk-dot"></span>
                            <span class="myat-tk-col-title">{{ $label }}</span>
                            <span class="myat-tk-count">{{ count($cards) }}</span>
                        </header>
                        <div class="myat-tk-cards" data-status="{{ $status }}" data-empty="{{ match ($status) { 'todo' => 'Τίποτα σε αναμονή', 'doing' => 'Τίποτα σε εξέλιξη', 'review' => 'Τίποτα για έλεγχο', default => 'Τίποτα ολοκληρωμένο ακόμα' } }}">
                            @foreach ($cards as $t)
                                @include('filament.tasks.card', ['t' => $t])
                            @endforeach
                        </div>
                        <div class="myat-tk-add" x-data="{ adding: false, title: '' }">
                            <button type="button" x-show="! adding" x-on:click="adding = true; $nextTick(() => $refs.input.focus())">
                                <x-filament::icon icon="heroicon-m-plus" /> Νέα εργασία
                            </button>
                            <form x-show="adding" x-cloak x-on:submit.prevent="if (title.trim()) { $wire.createTask(@js($status), title); title = '' } ; $refs.input.focus()">
                                <textarea x-ref="input" x-model="title" rows="2" maxlength="250" placeholder="Τίτλος εργασίας…"
                                    x-on:keydown.enter.prevent="$el.form.requestSubmit()" x-on:keydown.escape="adding = false; title = ''"></textarea>
                                <div class="myat-tk-add-actions">
                                    <button type="submit" class="myat-tk-btn is-primary">Προσθήκη</button>
                                    <button type="button" class="myat-tk-btn" x-on:click="adding = false; title = ''">Άκυρο</button>
                                </div>
                            </form>
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            {{-- List --}}
            @php($rows = $this->rows())
            @php($arrow = fn (string $k) => ltrim($sort, '-') === $k ? (str_starts_with($sort, '-') ? '↓' : '↑') : '')
            <div class="myat-tk-list">
                @if ($rows === [])
                    <div class="myat-tk-empty is-inline">{{ $this->filtered() ? 'Καμία εργασία με αυτά τα φίλτρα.' : 'Δεν υπάρχουν εργασίες ακόμα. Προσθέστε από τον Πίνακα.' }}</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th><button type="button" wire:click="sortBy('title')">Εργασία {{ $arrow('title') }}</button></th>
                                <th><button type="button" wire:click="sortBy('status')">Κατάσταση {{ $arrow('status') }}</button></th>
                                <th><button type="button" wire:click="sortBy('priority')">Προτεραιότητα {{ $arrow('priority') }}</button></th>
                                <th><button type="button" wire:click="sortBy('due')">Προθεσμία {{ $arrow('due') }}</button></th>
                                <th>Υπεύθυνοι</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $t)
                                <tr wire:key="row-{{ $t['id'] }}" wire:click="open({{ $t['id'] }})" class="{{ $openTask === $t['id'] ? 'is-open' : '' }} {{ $t['status'] === 'done' ? 'is-done' : '' }}">
                                    <td>
                                        <div class="myat-tk-list-title">
                                            <span>{{ $t['title'] }}</span>
                                            @if ($t['subTotal'])<span class="myat-tk-meta"><x-filament::icon icon="heroicon-m-check-circle" />{{ $t['subDone'] }}/{{ $t['subTotal'] }}</span>@endif
                                        </div>
                                    </td>
                                    <td><span class="myat-tk-status" style="--c: {{ Tasks::STATUSES[$t['status']][1] }}">{{ Tasks::STATUSES[$t['status']][0] }}</span></td>
                                    <td><span class="myat-tk-prio" style="--c: {{ Tasks::PRIORITIES[$t['priority']][1] }}">{{ Tasks::PRIORITIES[$t['priority']][0] }}</span></td>
                                    <td><span class="{{ $t['overdue'] ? 'myat-tk-overdue' : '' }}">{{ $t['dueFull'] ?? '—' }}</span></td>
                                    <td>
                                        <span class="myat-tk-avatars">
                                            @foreach ($t['people'] as $p)<span class="myat-tk-avatar" title="{{ $p['name'] }}">{{ $p['initials'] }}</span>@endforeach
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>

    {{-- Task panel --}}
    @if ($panel)
        @include('filament.tasks.panel', ['task' => $panel, 'people' => $people])
    @endif
</x-filament-panels::page>
