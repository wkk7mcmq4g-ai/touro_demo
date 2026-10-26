{{--
    Drivers' tasks (App\Filament\Portal\Pages\MyTasks), drawn like their job cards (.myat-job):
    project and number on top, the due day big, status and priority as tags, the task in bold, then
    the details; one big button moves it along (Ξεκίνησα → Ολοκληρώθηκε). A tap opens the panel.
--}}
@php
    use App\Support\Tasks;
    $cards = $this->cards();
    $panel = $this->panel();
    $dayLabel = fn ($t) => match (true) {
        ! $t['due'] => null,
        $t['today'] => 'Σήμερα',
        $t['tomorrow'] => 'Αύριο',
        default => $t['due']->format('d/m'),
    };
@endphp
<x-filament-panels::page>
    @if ($cards['open'] === [])
        <div class="myat-tk-empty">
            <x-filament::icon icon="heroicon-o-check-badge" class="myat-tk-empty-icon" />
            <p class="myat-tk-empty-title">Καμία ανοιχτή εργασία</p>
            <p>Όταν το γραφείο σας αναθέσει κάτι, θα εμφανιστεί εδώ.</p>
        </div>
    @else
        <div class="myat-tk-jobs">
            @foreach ($cards['open'] as $t)
                @php
                    [$statusLabel] = Tasks::STATUSES[$t['status']];
                    [$prioLabel] = Tasks::PRIORITIES[$t['priority']];
                    $urgent = in_array($t['priority'], ['critical', 'high'], true);
                @endphp
                <div @class(['myat-tk-jobcard', 'is-overdue' => $t['overdue'], 'is-today' => $t['today']])
                    wire:key="d-{{ $t['id'] }}" wire:click="open({{ $t['id'] }})" role="button" tabindex="0" x-on:keydown.enter="$wire.open({{ $t['id'] }})">
                    <div class="myat-job">
                        @if ($t['overdue'])
                            <div class="myat-job-nextbar is-overdue"><span>Εκπρόθεσμη</span><b>από {{ $t['due']->format('d/m') }}</b></div>
                        @elseif ($t['today'])
                            <div class="myat-job-nextbar"><span>Για σήμερα</span><b>{{ $statusLabel }}</b></div>
                        @endif

                        <div class="myat-job-top">
                            <span class="myat-job-vehicle myat-tk-job-project" style="--project: {{ $t['color'] }}">{{ $t['project'] }}</span>
                            <span class="myat-job-id">#{{ $t['id'] }}</span>
                        </div>

                        <div class="myat-job-head">
                            <div class="myat-job-count myat-tk-job-ring" style="--pct: {{ $t['subTotal'] ? round($t['subDone'] / $t['subTotal'] * 100) : 0 }}"
                                title="{{ $t['subTotal'] ? 'Υποεργασίες '.$t['subDone'].'/'.$t['subTotal'] : 'Εργασία' }}">
                                @if ($t['subTotal'])
                                    <span>{{ $t['subDone'] }}</span><small>/{{ $t['subTotal'] }}</small>
                                @else
                                    <x-filament::icon icon="heroicon-s-clipboard-document-check" class="myat-tk-job-ring-icon" />
                                @endif
                            </div>
                            <div class="myat-job-when">
                                @if ($t['due'])
                                    <div class="myat-job-time">{{ $dayLabel($t) }}</div>
                                    <div class="myat-job-date">{{ $t['today'] || $t['tomorrow'] ? $t['due']->locale('el')->translatedFormat('l d/m') : $t['due']->locale('el')->translatedFormat('l') }}</div>
                                @else
                                    <div class="myat-job-time is-muted">—</div>
                                    <div class="myat-job-date" style="text-transform:none">Χωρίς προθεσμία</div>
                                @endif
                            </div>
                            <div class="myat-job-tags">
                                <span class="myat-job-tag myat-tk-job-status is-{{ $t['status'] }}">{{ $statusLabel }}</span>
                                <span @class(['myat-job-tag', 'myat-job-tag-warn' => $urgent, 'myat-job-tag-soft' => ! $urgent])>{{ $prioLabel }}</span>
                            </div>
                        </div>

                        <div class="myat-tk-job-title">{{ $t['title'] }}</div>

                        @if ($t['description'] !== '')
                            <div class="myat-job-notes">{{ \Illuminate\Support\Str::limit($t['description'], 180) }}</div>
                        @endif

                        @if ($t['transfer'] || $t['comments'] || $t['files'])
                            <div class="myat-tk-job-meta">
                                @if ($t['transfer'])<span><x-filament::icon icon="heroicon-m-truck" /> Transfer #{{ $t['transfer'] }}</span>@endif
                                @if ($t['comments'])<span><x-filament::icon icon="heroicon-m-chat-bubble-left" /> {{ $t['comments'] }}</span>@endif
                                @if ($t['files'])<span><x-filament::icon icon="heroicon-m-paper-clip" /> {{ $t['files'] }}</span>@endif
                            </div>
                        @endif

                        <div class="myat-tk-job-actions">
                            <button type="button" @class(['myat-tk-job-go', 'is-done' => $t['status'] !== 'todo']) wire:click.stop="advance({{ $t['id'] }})"
                                wire:loading.attr="disabled" wire:target="advance({{ $t['id'] }})">
                                <x-filament::icon :icon="$t['status'] === 'todo' ? 'heroicon-m-play' : 'heroicon-m-check'" />
                                {{ $t['status'] === 'todo' ? 'Ξεκίνησα' : 'Ολοκληρώθηκε' }}
                            </button>
                            <button type="button" class="myat-tk-job-more" wire:click.stop="open({{ $t['id'] }})">Λεπτομέρειες</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($cards['done'] !== [])
        <details class="myat-tk-archived">
            <summary>Ολοκληρωμένες πρόσφατα ({{ count($cards['done']) }})</summary>
            <ul>
                @foreach ($cards['done'] as $t)
                    <li wire:key="dd-{{ $t['id'] }}">
                        <x-filament::icon icon="heroicon-s-check-circle" class="myat-tk-done-icon" />
                        <a href="#" wire:click.prevent="open({{ $t['id'] }})">{{ $t['title'] }}</a>
                        <span class="myat-tk-project-meta">{{ $t['completedAt'] }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($panel)
        @include('filament.tasks.panel', ['task' => $panel, 'people' => Tasks::people()])
    @endif
</x-filament-panels::page>
