{{--
    Full screen + table zoom buttons, in the table's toolbar. Behaviour: filament/tables/immersive.blade.php.
    Pages opt in through App\Support\ImmersiveTables.
--}}
<div class="myat-zoombar" role="group" aria-label="Προβολή πίνακα">
    <button type="button" class="myat-zb-btn myat-zb-enter" data-myat-imm="enter" title="Πλήρης οθόνη" aria-label="Πλήρης οθόνη">
        <x-filament::icon icon="heroicon-m-arrows-pointing-out" class="myat-zb-icon" />
    </button>
    <span class="myat-zb-group">
        <button type="button" class="myat-zb-btn" data-myat-imm="out" title="Σμίκρυνση" aria-label="Σμίκρυνση">
            <x-filament::icon icon="heroicon-m-minus" class="myat-zb-icon" />
        </button>
        <button type="button" class="myat-zb-btn myat-zb-pct" data-myat-imm="reset" title="Πίσω στο αρχικό ζουμ" aria-label="Πίσω στο αρχικό ζουμ">100%</button>
        <button type="button" class="myat-zb-btn" data-myat-imm="in" title="Μεγέθυνση" aria-label="Μεγέθυνση">
            <x-filament::icon icon="heroicon-m-plus" class="myat-zb-icon" />
        </button>
        <button type="button" class="myat-zb-btn" data-myat-imm="exit" title="Έξοδος από πλήρη οθόνη" aria-label="Έξοδος από πλήρη οθόνη">
            <x-filament::icon icon="heroicon-m-arrows-pointing-in" class="myat-zb-icon" />
        </button>
    </span>
</div>
