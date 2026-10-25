{{--
    Search box at the top of the column picker window: hides the columns whose name doesn't match,
    so a long list (Transfers' 57 columns) needs no scrolling. Plain Alpine over Filament's own
    picker markup; ticking and dragging still work as usual.
--}}
<div x-data="{
        q: '',
        filter() {
            const term = this.q.trim().toLocaleLowerCase('el').normalize('NFD').replace(/[̀-ͯ]/g, '');
            const box = this.$root.closest('.fi-modal-window')?.querySelector('.fi-ta-col-manager-items');
            let shown = 0;
            box?.querySelectorAll(':scope > div').forEach((item) => {
                const text = item.textContent.toLocaleLowerCase('el').normalize('NFD').replace(/[̀-ͯ]/g, '');
                const hit = term === '' || text.includes(term);
                item.style.display = hit ? '' : 'none';
                shown += hit ? 1 : 0;
            });
            this.none = shown === 0;
        },
        none: false,
     }"
     x-init="$watch('q', () => filter())"
     class="myat-colsearch">
    <label class="myat-colsearch-box">
        <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4 text-gray-400" />
        <input type="search" x-model="q" placeholder="Αναζήτηση στήλης…" aria-label="Αναζήτηση στήλης" autocomplete="off"
               x-on:keydown.escape.stop="q = ''">
    </label>
    <p class="myat-colsearch-none" x-show="none" x-cloak>Καμία στήλη με «<span x-text="q"></span>»</p>
</div>
