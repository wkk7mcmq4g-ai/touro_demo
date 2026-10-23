<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Χρειάζονται προσοχή</x-slot>
        <ul class="myat-attention">
            @forelse ($items as $item)
                <li>
                    <a href="{{ $item['url'] }}" @class(['myat-attention-item', 'is-clear' => $item['count'] === 0])>
                        <span class="myat-attention-icon myat-tone-{{ $item['count'] === 0 ? 'green' : $item['tone'] }}">
                            <x-filament::icon :icon="$item['count'] === 0 ? 'heroicon-o-check' : $item['icon']" />
                        </span>
                        <span class="myat-attention-text">
                            <span class="myat-attention-label">{{ $item['label'] }}</span>
                            <span class="myat-attention-hint">{{ $item['hint'] }}</span>
                        </span>
                        <span class="myat-attention-count">{{ $item['count'] }}</span>
                    </a>
                </li>
            @empty
                <li class="myat-attention-hint">Τίποτα για εσάς εδώ.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
