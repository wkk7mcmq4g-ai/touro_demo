{{-- Εργασίες · Έργα (App\Filament\Pages\Tasks\Projects): project cards, starred first; archived folded below. --}}
@php
    $data = $this->projects();
    $admin = \App\Support\Tasks::canManageProjects();
@endphp
<x-filament-panels::page>
    @if ($data['active'] === [] && $data['archived'] === [])
        <div class="myat-tk-empty">
            <x-filament::icon icon="heroicon-o-rectangle-stack" class="myat-tk-empty-icon" />
            <p class="myat-tk-empty-title">Δεν υπάρχουν έργα ακόμα</p>
            <p>{{ $admin ? 'Πατήστε «Νέο έργο» για να ξεκινήσετε.' : 'Τα έργα τα δημιουργεί ένας Admin.' }}</p>
        </div>
    @endif

    <div class="myat-tk-projects">
        @foreach ($data['active'] as $p)
            @php($pct = $p['total'] ? (int) round($p['done'] / $p['total'] * 100) : 0)
            <div class="myat-tk-project" style="--project: {{ $p['color'] }}" wire:key="p-{{ $p['id'] }}">
                <a href="{{ $p['url'] }}" class="myat-tk-project-link" aria-label="{{ $p['name'] }}"></a>
                <div class="myat-tk-project-top">
                    <span class="myat-tk-project-emoji">{{ $p['emoji'] ?: mb_substr($p['name'], 0, 1) }}</span>
                    <div class="myat-tk-project-title">
                        <span class="myat-tk-project-name">{{ $p['name'] }}</span>
                        <span class="myat-tk-project-meta">{{ $p['total'] }} εργασίες · {{ $p['total'] - $p['done'] }} ανοιχτές</span>
                    </div>
                    <button type="button" class="myat-tk-star {{ $p['starred'] ? 'is-on' : '' }}" wire:click="toggleStar({{ $p['id'] }})"
                        title="{{ $p['starred'] ? 'Αφαίρεση από τα αγαπημένα' : 'Στα αγαπημένα (και στο πλαϊνό μενού)' }}">
                        <x-filament::icon :icon="$p['starred'] ? 'heroicon-s-star' : 'heroicon-o-star'" />
                    </button>
                </div>
                @if ($p['description'] !== '')
                    <p class="myat-tk-project-desc">{{ \Illuminate\Support\Str::limit($p['description'], 140) }}</p>
                @endif
                <div class="myat-tk-progress" title="{{ $p['done'] }}/{{ $p['total'] }} ολοκληρωμένες">
                    <span style="width: {{ $pct }}%"></span>
                </div>
                <div class="myat-tk-project-foot">
                    <span>{{ $pct }}% ολοκληρωμένο</span>
                    @if ($p['overdue'])
                        <span class="myat-tk-overdue">{{ $p['overdue'] }} εκπρόθεσμες</span>
                    @endif
                    @if ($admin)
                        <button type="button" class="myat-tk-linkbtn" wire:click="setArchived({{ $p['id'] }}, true)" title="Αρχειοθέτηση">
                            <x-filament::icon icon="heroicon-m-archive-box-arrow-down" />
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($data['archived'] !== [])
        <details class="myat-tk-archived">
            <summary>Αρχειοθετημένα ({{ count($data['archived']) }})</summary>
            <ul>
                @foreach ($data['archived'] as $p)
                    <li wire:key="a-{{ $p['id'] }}">
                        <a href="{{ $p['url'] }}"><span>{{ $p['emoji'] }}</span> {{ $p['name'] }}</a>
                        <span class="myat-tk-project-meta">{{ $p['done'] }}/{{ $p['total'] }}</span>
                        @if ($admin)
                            <button type="button" class="myat-tk-linkbtn" wire:click="setArchived({{ $p['id'] }}, false)">Επαναφορά</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-filament-panels::page>
