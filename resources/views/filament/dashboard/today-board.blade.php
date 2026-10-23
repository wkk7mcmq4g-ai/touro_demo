<x-filament-widgets::widget>
    <x-filament::section class="myat-board">
        <x-slot name="heading">Το πρόγραμμα της ημέρας</x-slot>
        <x-slot name="description">{{ $jobs->where('Status', '!=', 'Cancelled')->count() }} transfers · τώρα {{ $now }}</x-slot>
        @if ($all)
            <x-slot name="afterHeader">
                <x-filament::link :href="$all" icon="heroicon-m-arrow-right" icon-position="after" size="sm">Όλα</x-filament::link>
            </x-slot>
        @endif

        @if ($jobs->isEmpty())
            <div class="myat-board-empty">
                <x-filament::icon icon="heroicon-o-sun" />
                <p>Δεν υπάρχουν transfers σήμερα.</p>
            </div>
        @else
            <ol class="myat-timeline">
                @foreach ($jobs as $job)
                    @if ($nextId !== null && $job->TransferID === $nextId)
                        <li class="myat-now" aria-label="Τώρα {{ $now }}"><span>Τώρα {{ $now }}</span></li>
                    @endif
                    @php
                        $time = substr((string) $job->TransferTime, 0, 5);
                        $past = $nextIndex === null || $loop->index < $nextIndex;
                        $url = $edit($job->TransferID);
                        $vehicle = trim((string) $job->CoachCompanyID);
                    @endphp
                    <li @class(['myat-tl-row', 'is-past' => $past, 'is-cancelled' => $job->Status === 'Cancelled'])>
                        <{!! $url ? 'a href="'.e($url).'"' : 'div' !!} class="myat-tl-link">
                            <span class="myat-tl-time">{{ $time ?: '—' }}</span>
                            <span class="myat-tl-dot" style="--dot: {{ $colour($job->TransferType) }}" title="{{ $job->TransferType }}"></span>
                            <span class="myat-tl-main">
                                <span class="myat-tl-route">{{ $job->From ?: '—' }} <span aria-hidden="true">→</span><span class="sr-only">προς</span> {{ $job->To ?: '—' }}</span>
                                <span class="myat-tl-sub">
                                    #{{ $job->TransferID }} · {{ $job->TransferType }} · {{ $job->Pax ?: '?' }} pax
                                    @if ($job->customer?->SupplierName) · {{ $job->customer->SupplierName }} @endif
                                    @if ($job->Flight_Ship_Num_From) · {{ $job->Flight_Ship_Num_From }} @endif
                                </span>
                            </span>
                            <span class="myat-tl-side">
                                @if ($job->Status === 'Cancelled')
                                @elseif ($vehicle !== '')
                                    <span class="myat-chip">{{ $vehicle }}@if ($job->DriverName) <em>· {{ $job->DriverName }}</em>@endif</span>
                                @else
                                    <span class="myat-chip myat-tone-amber">Χωρίς όχημα</span>
                                @endif
                                @if ($job->Status && $job->Status !== 'Pending')
                                    <span @class(['myat-status', 'myat-tone-green' => $job->Status === 'Completed', 'myat-tone-rose' => $job->Status === 'Cancelled'])>{{ $job->Status }}</span>
                                @endif
                            </span>
                        </{{ $url ? 'a' : 'div' }}>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
