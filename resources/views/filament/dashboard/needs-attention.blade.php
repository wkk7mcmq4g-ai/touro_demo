<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Χρειάζονται προσοχή</x-slot>
        <ul class="myat-attention">
            @forelse ($items as $i => $item)
                <li wire:key="attention-{{ $i }}" x-data="{ open: @js($item['open'] ?? false) }">
                    <div class="myat-attention-row">
                        <a href="{{ $item['url'] }}" @class(['myat-attention-item', 'is-clear' => $item['count'] === 0, 'is-urgent' => $item['urgent'] ?? false])>
                            <span class="myat-attention-icon myat-tone-{{ $item['count'] === 0 ? 'green' : $item['tone'] }}">
                                <x-filament::icon :icon="$item['count'] === 0 ? 'heroicon-o-check' : $item['icon']" />
                            </span>
                            <span class="myat-attention-text">
                                <span class="myat-attention-label">{{ $item['label'] }}</span>
                                <span class="myat-attention-hint">{{ $item['hint'] }}</span>
                            </span>
                            <span class="myat-attention-count">{{ $item['count'] }}</span>
                        </a>
                        @if (! empty($item['jobs']))
                            <button type="button" class="myat-attention-toggle" x-on:click="open = ! open" :aria-expanded="open"
                                :title="open ? 'Απόκρυψη' : 'Δώστε όχημα εδώ'">
                                <x-filament::icon icon="heroicon-m-chevron-down" x-bind:class="open && 'is-open'" />
                            </button>
                        @endif
                    </div>

                    {{-- "Χωρίς όχημα": each job with an Όχημα button (the quick-edit window, App\Support\TransferCellEdit). --}}
                    @if (! empty($item['jobs']))
                        <ul class="myat-attention-jobs" x-show="open">
                            @foreach ($item['jobs'] as $job)
                                <li wire:key="unassigned-{{ $job['id'] }}">
                                    <span class="myat-attention-job-time">{{ $job['time'] ?: '—' }}</span>
                                    <span class="myat-attention-job-text">
                                        <span class="myat-attention-job-route">{{ $job['route'] ?: '—' }}</span>
                                        <span class="myat-attention-job-meta">
                                            #{{ $job['id'] }} · {{ $job['type'] }}@if ($job['pax'] !== '') · {{ $job['pax'] }} άτ.@endif
                                            @if ($job['customer'] !== '') · {{ $job['customer'] }}@endif
                                        </span>
                                    </span>
                                    <button type="button" class="myat-attention-job-go"
                                        x-on:click="$dispatch('myat-cell', @js(['id' => $job['id'], 'field' => 'CoachCompanyID', 'value' => '']))">
                                        <x-filament::icon icon="heroicon-m-truck" /> Όχημα
                                    </button>
                                </li>
                            @endforeach
                            @if (count($item['jobs']) < $item['count'])
                                <li class="myat-attention-jobs-more"><a href="{{ $item['url'] }}">και {{ $item['count'] - count($item['jobs']) }} ακόμα στη λίστα →</a></li>
                            @endif
                        </ul>
                    @endif
                </li>
            @empty
                <li class="myat-attention-hint">Τίποτα για εσάς εδώ.</li>
            @endforelse
        </ul>
    </x-filament::section>

    @if ($canFix)
        @include('filament.transfers.cell-editor')
    @endif
</x-filament-widgets::widget>
