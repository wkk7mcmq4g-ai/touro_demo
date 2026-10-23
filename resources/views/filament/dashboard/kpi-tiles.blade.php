@php
    $progress = $today['jobs'] > 0 ? round($today['done'] / $today['jobs'] * 100) : 0;
    $tile = fn (?string $url) => $url ? 'a href="'.e($url).'"' : 'div';
@endphp
<x-filament-widgets::widget class="myat-kpis">
    <div class="myat-kpi-grid">
        <{!! $tile($urls['today']) !!} class="myat-kpi myat-kpi-hero">
            <div class="myat-kpi-top">
                <span class="myat-kpi-label">Σήμερα</span>
                <x-filament::icon icon="heroicon-o-calendar" class="myat-kpi-icon" />
            </div>
            <div class="myat-kpi-value">{{ $today['jobs'] }} <small>transfers</small></div>
            <div class="myat-kpi-meta">{{ $today['pax'] }} επιβάτες · {{ $today['done'] }} ολοκληρωμένα</div>
            <div class="myat-kpi-bar" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Ολοκληρωμένα σήμερα">
                <span style="width: {{ $progress }}%"></span>
            </div>
        </{{ $urls['today'] ? 'a' : 'div' }}>

        <{!! $tile($urls['tomorrow']) !!} class="myat-kpi">
            <div class="myat-kpi-top">
                <span class="myat-kpi-label">Αύριο</span>
                <x-filament::icon icon="heroicon-o-calendar-days" class="myat-kpi-icon" />
            </div>
            <div class="myat-kpi-value">{{ $tomorrow['jobs'] }}</div>
            <div class="myat-kpi-meta">{{ $tomorrowLabel }} · {{ $tomorrow['pax'] }} επιβάτες</div>
            @if ($tomorrow['unassigned'] > 0)
                <div class="myat-kpi-flag myat-tone-amber">{{ $tomorrow['unassigned'] }} χωρίς όχημα</div>
            @endif
        </{{ $urls['tomorrow'] ? 'a' : 'div' }}>

        <{!! $tile($urls['pending']) !!} class="myat-kpi">
            <div class="myat-kpi-top">
                <span class="myat-kpi-label">Pending · 7 ημέρες</span>
                <x-filament::icon icon="heroicon-o-clock" class="myat-kpi-icon" />
            </div>
            <div class="myat-kpi-value">{{ $pending }}</div>
            <div class="myat-kpi-meta">Δεν έχουν ολοκληρωθεί ακόμα</div>
        </{{ $urls['pending'] ? 'a' : 'div' }}>

        <{!! $tile($urls['issues']) !!} @class(['myat-kpi', 'myat-kpi-alert' => $issues > 0])>
            <div class="myat-kpi-top">
                <span class="myat-kpi-label">Θέματα · 7 ημέρες</span>
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="myat-kpi-icon" />
            </div>
            <div class="myat-kpi-value">{{ $issues }}</div>
            <div class="myat-kpi-meta">{{ $issues > 0 ? 'Λείπουν στοιχεία (TBD, διαδρομή, πτήση)' : 'Όλα εντάξει' }}</div>
        </{{ $urls['issues'] ? 'a' : 'div' }}>
    </div>
</x-filament-widgets::widget>
