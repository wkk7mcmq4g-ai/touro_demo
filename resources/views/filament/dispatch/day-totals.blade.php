{{-- Totals per day of what the dispatch list shows now (DispatchDayTotals widget). --}}
<x-filament-widgets::widget>
    <div class="myat-daytotals" role="group" aria-label="Σύνολα ανά ημέρα">
        @forelse ($days as $day)
            <div @class(['myat-day', 'myat-day-today' => $day['date']->isToday()])>
                <span class="myat-day-date">{{ $day['date']->isToday() ? 'Σήμερα' : ($day['date']->isTomorrow() ? 'Αύριο' : ucfirst($day['date']->locale('el')->translatedFormat('D'))) }} {{ $day['date']->format('d/m') }}</span>
                <span class="myat-day-main">{{ $day['jobs'] }} <small>transfers</small></span>
                <span class="myat-day-sub">{{ $day['pax'] }} άτομα · {{ $day['drivers'] }} οδηγοί</span>
                <span class="myat-day-sub">{{ strtoupper(config('business.short_name')) }} {{ $day['own'] }} · Partner {{ $day['partner'] }}</span>
            </div>
        @empty
            <div class="myat-day myat-day-empty">Κανένα transfer</div>
        @endforelse
    </div>
</x-filament-widgets::widget>
