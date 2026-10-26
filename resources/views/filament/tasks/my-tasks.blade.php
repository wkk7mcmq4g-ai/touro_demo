{{-- Οι εργασίες μου (App\Filament\Pages\Tasks\MyTasks): open tasks given to me, by priority. --}}
@php($groups = $this->groups())
<x-filament-panels::page>
    @if ($groups === [])
        <div class="myat-tk-empty">
            <x-filament::icon icon="heroicon-o-check-badge" class="myat-tk-empty-icon" />
            <p class="myat-tk-empty-title">Καμία ανοιχτή εργασία για εσάς</p>
            <p><a class="myat-tk-linkbtn" href="{{ \App\Filament\Pages\Tasks\Projects::getUrl() }}">Δείτε τα έργα</a></p>
        </div>
    @endif
    <div class="myat-tk-mine">
        @foreach ($groups as $priority => $tasks)
            @php([$label, $color] = \App\Support\Tasks::PRIORITIES[$priority])
            <section class="myat-tk-mine-group" style="--c: {{ $color }}">
                <h2><span class="myat-tk-dot"></span>{{ $label }} <span class="myat-tk-count">{{ count($tasks) }}</span></h2>
                <ul>
                    @foreach ($tasks as $t)
                        <li>
                            <a href="{{ $t['url'] }}" class="myat-tk-mine-row">
                                <span class="myat-tk-mine-title">{{ $t['title'] }}</span>
                                <span class="myat-tk-mine-project" style="--project: {{ $t['color'] }}">{{ $t['project'] }}</span>
                                <span class="myat-tk-status" style="--c: {{ \App\Support\Tasks::STATUSES[$t['status']][1] }}">{{ \App\Support\Tasks::STATUSES[$t['status']][0] }}</span>
                                <span class="myat-tk-due {{ $t['overdue'] ? 'is-overdue' : ($t['today'] ? 'is-today' : '') }}">{{ $t['today'] ? 'Σήμερα' : ($t['due'] ?? 'Χωρίς προθεσμία') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
