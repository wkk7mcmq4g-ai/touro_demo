{{--
    Drivers' work schedule (PHPRunner driver_cal). Rendered by Alpine from one JSON payload so a
    change only touches the cell and the counters: the 0s/9s of the first month per driver, and per
    day the ΡΕΠΟ count (0, 8, 9; flagged above 2) and the Sprinter count (3; flagged below 3).
    Entry works like a spreadsheet: a digit saves and moves right, arrows/Enter/Tab move, Esc cancels.
--}}
@php($cal = $this->calendar())
@php($canEdit = $this->canEditSchedule())
<x-filament-panels::page>
    <div @class(['myat-cal', 'is-readonly' => ! $canEdit]) wire:key="cal-{{ $offset }}-{{ $revision }}"
         x-data="driverCalendar(@js($cal), @js($canEdit))">

        <div class="myat-cal-bar">
            <div class="myat-cal-nav">
                <div class="myat-cal-step" role="group" aria-label="Μήνες">
                    <button type="button" wire:click="move(-1)" aria-label="Προηγούμενος μήνας" title="Προηγούμενος μήνας">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
                    </button>
                    <button type="button" wire:click="move(0)" @disabled($offset === 0)>Σήμερα</button>
                    <button type="button" wire:click="move(1)" aria-label="Επόμενος μήνας" title="Επόμενος μήνας">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
                    </button>
                </div>
                <h2 class="myat-cal-range">{{ $cal['range'] }}</h2>
                <x-filament::loading-indicator class="h-5 w-5 text-gray-400" wire:loading wire:target="move" />
            </div>
            <ul class="myat-cal-legend" aria-label="Κωδικοί">
                @foreach (\App\Filament\Pages\DriverCalendar::STATUSES as $code => [$label, $color])
                    <li><b style="background:{{ $color }};color:{{ $cal['inks'][$code] }}">{{ $code }}</b>{{ $label }}</li>
                @endforeach
            </ul>
        </div>

        <div class="myat-cal-scroll" @mouseleave="hr = null; hc = null">
            <table>
                <thead>
                    <tr>
                        <th class="myat-cal-name" rowspan="2" scope="col">Οδηγός</th>
                        <th class="myat-cal-sum is-0" rowspan="2" scope="col" title="ΡΕΠΟ στον πρώτο μήνα">0</th>
                        <th class="myat-cal-sum is-9" rowspan="2" scope="col" title="ΑΔΕΙΑ στον πρώτο μήνα">9</th>
                        <template x-for="m in c.months" :key="m.name">
                            <th class="myat-cal-month" :colspan="m.count" scope="colgroup"><span x-text="m.name"></span></th>
                        </template>
                    </tr>
                    <tr>
                        <template x-for="(d, i) in c.days" :key="d.date">
                            <th class="myat-cal-day" scope="col"
                                :class="{ 'is-last': d.last, 'is-weekend': d.weekend, 'is-today': d.date === c.today, 'is-hl': hc === i }">
                                <span x-text="d.n"></span><small x-text="d.w"></small>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(dr, r) in c.drivers" :key="dr.id">
                        <tr>
                            <th class="myat-cal-name" scope="row" :class="{ 'is-hl': hr === r }" :style="dr.style" x-text="dr.name"></th>
                            <td class="myat-cal-sum is-0" x-text="monthCount(dr.id, '0')"></td>
                            <td class="myat-cal-sum is-9" x-text="monthCount(dr.id, '9')"></td>
                            <template x-for="(d, i) in c.days" :key="d.date">
                                <td class="myat-cal-cell"
                                    :class="{
                                        'is-last': d.last, 'is-weekend': d.weekend, 'is-today': d.date === c.today,
                                        'is-hl': (hr === r && i <= hc) || (hc === i && r <= hr),
                                        'is-edit': editing === r + '|' + i,
                                        'is-saving': saving[dr.id + '|' + d.date],
                                    }"
                                    :style="paint(val(dr.id, d.date))"
                                    @mouseenter="hr = r; hc = i"
                                    @click="open(r, i)">
                                    <template x-if="editing === r + '|' + i">
                                        <input type="text" inputmode="numeric" maxlength="1" autocomplete="off"
                                               :aria-label="dr.name + ' ' + d.date"
                                               x-init="$nextTick(() => { $el.focus(); $el.select() })"
                                               :value="val(dr.id, d.date)"
                                               @input="$el.value = $el.value.replace(/[^0-9]/g, ''); if ($el.value !== '') save(r, i, $el.value, 0, 1)"
                                               @keydown.enter.prevent="save(r, i, $el.value, $event.shiftKey ? -1 : 1, 0)"
                                               @keydown.tab.prevent="save(r, i, $el.value, 0, $event.shiftKey ? -1 : 1)"
                                               @keydown.arrow-right.prevent="save(r, i, $el.value, 0, 1)"
                                               @keydown.arrow-left.prevent="save(r, i, $el.value, 0, -1)"
                                               @keydown.arrow-down.prevent="save(r, i, $el.value, 1, 0)"
                                               @keydown.arrow-up.prevent="save(r, i, $el.value, -1, 0)"
                                               @keydown.escape.prevent="editing = null"
                                               @blur="save(r, i, $el.value)">
                                    </template>
                                    <span x-show="editing !== r + '|' + i" x-text="val(dr.id, d.date)"></span>
                                </td>
                            </template>
                        </tr>
                    </template>
                    <tr class="myat-cal-total is-first">
                        <th class="myat-cal-name" scope="row">ΡΕΠΟ <small>0·8·9 · όριο 2</small></th>
                        <td class="myat-cal-sum"></td><td class="myat-cal-sum"></td>
                        <template x-for="d in c.days" :key="d.date">
                            <td x-data="{ get n() { return dayCount(d.date, ['0', '8', '9']) } }"
                                :class="{ 'is-last': d.last, 'is-bad': n > 2 }" :title="n > 2 ? 'Πάνω από 2 ρεπό' : null" x-text="n"></td>
                        </template>
                    </tr>
                    <tr class="myat-cal-total">
                        <th class="myat-cal-name" scope="row">Sprinter <small>3 · τουλάχιστον 3</small></th>
                        <td class="myat-cal-sum"></td><td class="myat-cal-sum"></td>
                        <template x-for="d in c.days" :key="d.date">
                            <td x-data="{ get n() { return dayCount(d.date, ['3']) } }"
                                :class="{ 'is-last': d.last, 'is-bad': n < 3 }" :title="n < 3 ? 'Λιγότεροι από 3 Sprinter' : null" x-text="n"></td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($canEdit)
            <p class="myat-cal-hint">
                Κλικ σε κελί και πληκτρολογήστε τον κωδικό: αποθηκεύεται και πάει στην επόμενη μέρα.
                <kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> μετακίνηση · <kbd>Enter</kbd> κάτω ·
                <kbd>Backspace</kbd> και βέλος για καθάρισμα · <kbd>Esc</kbd> ακύρωση
            </p>
        @endif
    </div>

    @script
    <script>
        Alpine.data('driverCalendar', (c, canEdit) => ({
            c, canEdit, hr: null, hc: null, editing: null, saving: {},
            val(id, date) { return (this.c.cells[id] || {})[date] ?? '' },
            paint(v) { return this.c.colors[v] ? 'background:' + this.c.colors[v] + ';color:' + this.c.inks[v] : '' },
            monthCount(id, s) {
                const row = this.c.cells[id] || {};
                return Object.keys(row).filter((d) => d.startsWith(this.c.firstMonth + '-') && row[d] === s).length;
            },
            dayCount(date, list) {
                return this.c.drivers.filter((d) => list.includes(this.val(d.id, date))).length;
            },
            open(r, i) {
                if (this.canEdit && this.c.drivers[r] && this.c.days[i]) this.editing = r + '|' + i;
            },
            // Save the open cell, then move dr rows / dc days (0, 0: just close).
            async save(r, i, value, dr = 0, dc = 0) {
                if (this.editing !== r + '|' + i) return;
                this.editing = null;
                if (dr || dc) this.$nextTick(() => this.open(r + dr, i + dc));
                const id = this.c.drivers[r].id, date = this.c.days[i].date, key = id + '|' + date;
                const before = this.val(id, date);
                if (value === before) return;
                this.put(id, date, value);
                this.saving[key] = true;
                try {
                    await $wire.setStatus(id, date, value);
                } catch (e) {
                    this.put(id, date, before);
                    new FilamentNotification().title('Δεν αποθηκεύτηκε').body(this.c.drivers[r].name + ' ' + date).danger().send();
                } finally {
                    delete this.saving[key];
                }
            },
            put(id, date, value) {
                const row = { ...(this.c.cells[id] || {}), [date]: value };
                if (value === '') delete row[date];
                this.c.cells[id] = row;
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
