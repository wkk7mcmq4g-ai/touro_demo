{{-- Phone navigation: the portal's pages as a tab bar at the bottom of the screen. --}}
@php
    $items = collect(filament()->getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->filter(fn ($item) => $item->isVisible())
        ->take(5);
@endphp
@if ($items->count() > 1)
    <nav class="myat-tabbar" aria-label="Menu">
        @foreach ($items as $item)
            <a href="{{ $item->getUrl() }}" @class(['myat-tabbar-item', 'myat-tabbar-active' => $item->isActive()])>
                @if ($icon = $item->isActive() ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon())
                    <x-filament::icon :icon="$icon" class="myat-tabbar-icon" />
                @endif
                <span>{{ $item->getLabel() }}</span>
            </a>
        @endforeach
    </nav>
@endif
