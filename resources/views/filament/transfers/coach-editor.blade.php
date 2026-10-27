{{--
    Transfers list: double-click a Coach cell → this small window (ListTransfers::setCoach).
    Opening, searching and Cancel stay in the browser (no round trip, so nothing redraws);
    only Αποθήκευση talks to the server, which then refreshes the table with the new values.
--}}
<div wire:ignore
    x-data="{
        open: false,
        id: null,
        current: '',
        value: '',
        q: '',
        saving: false,
        coaches: @js(array_values(\App\Support\Lookups::coaches())),
        norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        get shown() { const q = this.norm(this.q).trim(); return q ? this.coaches.filter((c) => this.norm(c).includes(q)) : this.coaches },
        show(detail) {
            this.id = detail.id; this.current = detail.coach || ''; this.value = this.current; this.q = ''; this.saving = false; this.open = true
            this.$nextTick(() => { this.$refs.q.focus(); this.$refs.list.querySelector('[aria-selected=true]')?.scrollIntoView({ block: 'nearest' }) })
        },
        pick(c) { this.value = c },
        async save() {
            if (this.saving) return
            if (this.value === this.current) { this.open = false; return }
            this.saving = true
            try { await $wire.setCoach(this.id, this.value) } finally { this.saving = false; this.open = false }
        },
    }"
    x-on:myat-coach.window="show($event.detail)"
    x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="myat-coach-wrap">
            <div class="myat-coach-backdrop" x-on:click="open = false"></div>
            <div class="myat-coach" role="dialog" aria-modal="true" aria-label="Coach">
                <div class="myat-coach-head">
                    <p>Coach · Transfer #<span x-text="id"></span></p>
                    <button type="button" class="myat-coach-x" x-on:click="open = false" aria-label="Κλείσιμο">&times;</button>
                </div>
                <label class="myat-coach-search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" />
                    <input type="search" x-ref="q" x-model="q" placeholder="Αναζήτηση οχήματος…"
                        x-on:keydown.enter.prevent="shown.length === 1 ? (pick(shown[0]), save()) : save()">
                </label>
                <ul class="myat-coach-list" x-ref="list" role="listbox">
                    <li role="option" :aria-selected="value === ''" :class="{ 'is-on': value === '' }"
                        x-on:click="pick('')" x-on:dblclick="pick(''); save()">— Χωρίς όχημα —</li>
                    <template x-for="c in shown" :key="c">
                        <li role="option" :aria-selected="value === c" :class="{ 'is-on': value === c }"
                            x-on:click="pick(c)" x-on:dblclick="pick(c); save()" x-text="c"></li>
                    </template>
                    <li class="myat-coach-none" x-show="shown.length === 0">Δεν βρέθηκε όχημα.</li>
                </ul>
                <p class="myat-coach-hint">Τα δικά μας οχήματα μηδενίζουν την τιμή αγοράς.</p>
                <div class="myat-coach-actions">
                    <button type="button" class="myat-coach-save" x-on:click="save()" :disabled="saving">
                        <span x-show="! saving">Αποθήκευση</span><span x-show="saving">Αποθήκευση…</span>
                    </button>
                    <button type="button" class="myat-coach-cancel" x-on:click="open = false">Ακύρωση</button>
                </div>
            </div>
        </div>
    </template>
</div>
