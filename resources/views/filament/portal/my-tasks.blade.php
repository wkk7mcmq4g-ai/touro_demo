{{-- Drivers' tasks (App\Filament\Portal\Pages\MyTasks): cards; a tap opens the task panel (limited). --}}
@php
    use App\Support\Tasks;
    $cards = $this->cards();
    $panel = $this->panel();
@endphp
<x-filament-panels::page>
    @if ($cards['open'] === [])
        <div class="myat-tk-empty">
            <x-filament::icon icon="heroicon-o-check-badge" class="myat-tk-empty-icon" />
            <p class="myat-tk-empty-title">Καμία ανοιχτή εργασία</p>
            <p>Όταν το γραφείο σας αναθέσει κάτι, θα εμφανιστεί εδώ.</p>
        </div>
    @else
        <div class="myat-tk-drv">
            @foreach ($cards['open'] as $t)
                @php($prio = Tasks::PRIORITIES[$t['priority']])
                <button type="button" class="myat-tk-card myat-tk-drv-card" style="--prio: {{ $prio[1] }}" wire:click="open({{ $t['id'] }})" wire:key="d-{{ $t['id'] }}">
                    <div class="myat-tk-card-top">
                        <span class="myat-tk-status" style="--c: {{ Tasks::STATUSES[$t['status']][1] }}">{{ Tasks::STATUSES[$t['status']][0] }}</span>
                        @if ($t['due'])
                            <span class="myat-tk-due {{ $t['overdue'] ? 'is-overdue' : ($t['today'] ? 'is-today' : '') }}">
                                <x-filament::icon icon="heroicon-m-calendar" /> {{ $t['today'] ? 'Σήμερα' : $t['due'] }}
                            </span>
                        @endif
                    </div>
                    <p class="myat-tk-card-title">{{ $t['title'] }}</p>
                    <div class="myat-tk-card-foot">
                        <span class="myat-tk-mine-project" style="--project: {{ $t['color'] }}">{{ $t['project'] }}</span>
                        @if ($t['transfer'])<span class="myat-tk-meta"><x-filament::icon icon="heroicon-m-truck" />#{{ $t['transfer'] }}</span>@endif
                        <span class="myat-tk-prio" style="--c: {{ $prio[1] }}; margin-left:auto">{{ $prio[0] }}</span>
                    </div>
                </button>
            @endforeach
        </div>
    @endif

    @if ($cards['done'] !== [])
        <details class="myat-tk-archived">
            <summary>Ολοκληρωμένες πρόσφατα ({{ count($cards['done']) }})</summary>
            <ul>
                @foreach ($cards['done'] as $t)
                    <li wire:key="dd-{{ $t['id'] }}">
                        <a href="#" wire:click.prevent="open({{ $t['id'] }})">{{ $t['title'] }}</a>
                        <span class="myat-tk-project-meta">{{ $t['project'] }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($panel)
        @include('filament.tasks.panel', ['task' => $panel, 'people' => Tasks::people()])
    @endif
</x-filament-panels::page>
