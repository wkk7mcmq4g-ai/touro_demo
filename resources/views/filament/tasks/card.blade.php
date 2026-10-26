{{-- One Kanban card (see board.blade.php). --}}
@php($prio = \App\Support\Tasks::PRIORITIES[$t['priority']])
<article class="myat-tk-card {{ $openTask === $t['id'] ? 'is-open' : '' }} {{ $t['status'] === 'done' ? 'is-done' : '' }}"
    data-task="{{ $t['id'] }}" wire:key="card-{{ $t['id'] }}" style="--prio: {{ $prio[1] }}"
    x-on:click="if (! $event.target.closest('a')) $wire.open({{ $t['id'] }})" tabindex="0" x-on:keydown.enter="$wire.open({{ $t['id'] }})">
    <div class="myat-tk-card-top">
        <span class="myat-tk-prio" style="--c: {{ $prio[1] }}">{{ $prio[0] }}</span>
        @if ($t['due'])
            <span class="myat-tk-due {{ $t['overdue'] ? 'is-overdue' : ($t['today'] ? 'is-today' : '') }}" title="Προθεσμία {{ $t['dueFull'] }}">
                <x-filament::icon icon="heroicon-m-calendar" /> {{ $t['today'] ? 'Σήμερα' : $t['due'] }}
            </span>
        @endif
    </div>
    <p class="myat-tk-card-title">{{ $t['title'] }}</p>
    @if ($t['subTotal'])
        <div class="myat-tk-progress is-small" title="Υποεργασίες {{ $t['subDone'] }}/{{ $t['subTotal'] }}"><span style="width: {{ round($t['subDone'] / $t['subTotal'] * 100) }}%"></span></div>
    @endif
    <div class="myat-tk-card-foot">
        @if ($t['subTotal'])<span class="myat-tk-meta" title="Υποεργασίες"><x-filament::icon icon="heroicon-m-check-circle" />{{ $t['subDone'] }}/{{ $t['subTotal'] }}</span>@endif
        @if ($t['comments'])<span class="myat-tk-meta" title="Σχόλια"><x-filament::icon icon="heroicon-m-chat-bubble-left" />{{ $t['comments'] }}</span>@endif
        @if ($t['files'])<span class="myat-tk-meta" title="Συνημμένα"><x-filament::icon icon="heroicon-m-paper-clip" />{{ $t['files'] }}</span>@endif
        @if ($t['transfer'])<span class="myat-tk-meta" title="Transfer"><x-filament::icon icon="heroicon-m-truck" />#{{ $t['transfer'] }}</span>@endif
        <span class="myat-tk-avatars">
            @foreach ($t['people'] as $p)<span class="myat-tk-avatar" title="{{ $p['name'] }}">{{ $p['initials'] }}</span>@endforeach
        </span>
    </div>
</article>
