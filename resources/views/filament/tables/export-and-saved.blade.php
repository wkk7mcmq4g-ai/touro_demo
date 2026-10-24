{{-- Table toolbar: Excel export (choose columns, saved exports) and saved filters. Behaviour in excel-and-widths.blade.php. --}}
<span class="myat-tb-extra">
    @if ($hasFilters)
        <button type="button" class="myat-zb-btn myat-tb-btn" data-myat-saved="filters" title="Αποθηκευμένα φίλτρα" aria-label="Αποθηκευμένα φίλτρα">
            <x-filament::icon icon="heroicon-m-bookmark" class="myat-zb-icon" />
            <span class="myat-tb-label">Φίλτρα</span>
        </button>
    @endif
    @if ($canExport)
        <button type="button" class="myat-zb-btn myat-tb-btn" data-myat-export title="Εξαγωγή σε Excel" aria-label="Εξαγωγή σε Excel">
            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="myat-zb-icon" />
            <span class="myat-tb-label">Excel</span>
        </button>
    @endif
</span>
