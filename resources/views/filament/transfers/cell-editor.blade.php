{{--
    Quick edit of one transfer field (App\Support\TransferCellEdit): double-click a Coach, Status,
    Transfer Time, Assistant or Driver Notes cell on the Transfers list, or the "Όχημα" button on the
    Dashboard. The cell sends {id, field, value} with a `myat-cell` browser event. Opening, searching
    and Cancel stay in the browser (nothing redraws); only Αποθήκευση calls $wire.saveTransferCell
    (trait EditsTransferCells), which redraws the page with the new values or returns a message.
--}}
<div wire:ignore
    x-data="{
        fields: @js(\App\Support\TransferCellEdit::config()),
        open: false, id: null, field: null, current: '', value: '', notes: '', q: '', saving: false, error: '',
        get f() { return this.fields[this.field] || {} },
        norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
        get shown() {
            const q = this.norm(this.q).trim(), all = this.f.options || []
            return q ? all.filter((o) => this.norm(o).includes(q)) : all
        },
        get typedIsNew() {
            const t = this.q.trim()
            return this.f.free && t !== '' && ! (this.f.options || []).some((o) => this.norm(o) === this.norm(t))
        },
        show(d) {
            if (! this.fields[d.field]) return
            this.id = d.id; this.field = d.field; this.current = d.value || ''; this.value = this.current
            this.notes = d.notes || ''; this.q = ''; this.error = ''; this.saving = false; this.open = true
            this.$nextTick(() => {
                const el = this.$refs['in_' + this.f.kind]; el?.focus()
                if (this.f.kind === 'text') el?.select()
                this.$refs.list?.querySelector('[aria-selected=true]')?.scrollIntoView({ block: 'nearest' })
            })
        },
        pick(v) { this.value = v; this.error = '' },
        enter() {
            if (this.f.kind !== 'list') return this.save()
            if (this.typedIsNew && this.shown.length === 0) { this.pick(this.q.trim()); return this.save() }
            if (this.shown.length === 1) this.pick(this.shown[0])
            this.save()
        },
        async save() {
            if (this.saving) return
            if (this.value === this.current) { this.open = false; return }
            this.saving = true; this.error = ''
            try {
                const error = await $wire.saveTransferCell(this.id, this.field, this.value)
                if (error) { this.error = error; return }
                this.open = false
            } catch (e) { this.error = 'Δεν αποθηκεύτηκε. Δοκιμάστε ξανά.' } finally { this.saving = false }
        },
    }"
    x-on:myat-cell.window="show($event.detail)"
    x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="myat-coach-wrap">
            <div class="myat-coach-backdrop" x-on:click="open = false"></div>
            <div class="myat-coach" role="dialog" aria-modal="true" :aria-label="f.label">
                <div class="myat-coach-head">
                    <p><span x-text="f.label"></span> · Transfer #<span x-text="id"></span></p>
                    <button type="button" class="myat-coach-x" x-on:click="open = false" aria-label="Κλείσιμο">&times;</button>
                </div>

                {{-- A list (Coach, Status, Assistant): search + pick; Assistant also takes a new name. --}}
                <template x-if="f.kind === 'list'">
                    <div class="myat-cell-body">
                        <label class="myat-coach-search" x-show="f.free || (f.options || []).length > 8">
                            <x-filament::icon icon="heroicon-m-magnifying-glass" />
                            <input type="search" x-ref="in_list" x-model="q" :placeholder="f.search" x-on:keydown.enter.prevent="enter()">
                        </label>
                        <ul class="myat-coach-list" x-ref="list" role="listbox">
                            <template x-if="f.empty">
                                <li role="option" class="is-empty" :aria-selected="value === ''" :class="{ 'is-on': value === '' }"
                                    x-on:click="pick('')" x-on:dblclick="pick(''); save()" x-text="f.empty"></li>
                            </template>
                            <template x-if="typedIsNew">
                                <li role="option" class="is-new" :aria-selected="value === q.trim()" :class="{ 'is-on': value === q.trim() }"
                                    x-on:click="pick(q.trim())" x-on:dblclick="pick(q.trim()); save()">Χρήση: «<span x-text="q.trim()"></span>»</li>
                            </template>
                            <template x-for="o in shown" :key="o">
                                <li role="option" :aria-selected="value === o" :class="{ 'is-on': value === o }"
                                    x-on:click="pick(o)" x-on:dblclick="pick(o); save()" x-text="o"></li>
                            </template>
                            <template x-if="current !== '' && ! (f.options || []).includes(current) && q === ''">
                                <li role="option" :aria-selected="value === current" :class="{ 'is-on': value === current }"
                                    x-on:click="pick(current)" x-text="current"></li>
                            </template>
                            <li class="myat-coach-none" x-show="shown.length === 0 && ! typedIsNew">Δεν βρέθηκε τίποτα.</li>
                        </ul>
                    </div>
                </template>

                {{-- One line (Transfer Time). --}}
                <template x-if="f.kind === 'text'">
                    <input type="text" class="myat-cell-input" x-ref="in_text" x-model="value" :placeholder="f.placeholder" :maxlength="f.max"
                        x-on:keydown.enter.prevent="save()">
                </template>

                {{-- Free text (Driver Notes): Ctrl/⌘+Enter saves; ">>" copies the Transfer Notes like on the form. --}}
                <template x-if="f.kind === 'textarea'">
                    <div class="myat-cell-body">
                        <textarea class="myat-cell-input" rows="5" x-ref="in_textarea" x-model="value" :maxlength="f.max"
                            x-on:keydown.enter="if ($event.ctrlKey || $event.metaKey) { $event.preventDefault(); save() }"></textarea>
                        <button type="button" class="myat-cell-copy" x-show="f.copy && notes !== ''" x-on:click="value = notes; $refs.in_textarea.focus()" x-text="f.copy"></button>
                    </div>
                </template>

                <p class="myat-coach-hint" x-show="f.hint && ! error" x-text="f.hint"></p>
                <p class="myat-cell-error" x-show="error" x-text="error" role="alert"></p>

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
