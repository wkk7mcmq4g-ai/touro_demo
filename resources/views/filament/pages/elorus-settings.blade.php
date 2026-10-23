<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-6">
        {{ $this->form }}

        <div>
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Elorus is {{ config('elorus.enabled') ? 'switched on' : 'switched off' }} on this server (ELORUS_ENABLED in .env).
    </p>
</x-filament-panels::page>
