{{--
    Drivers' work schedule (PHPRunner driver_cal). Rendered by Alpine from one JSON payload so a
    change only touches the cell and the counters: the 0s/9s of the first month per driver, and per
    day the ΡΕΠΟ count (0, 8, 9; red above 2) and the Sprinter count (3; red below 3).
--}}
@php($cal = $this->calendar())
<x-filament-panels::page>
    <div @class(["myat-cal", "is-readonly" => ! $this->canEditSchedule()]) wire:key="cal-{{ $offset }}-{{ $revision }}"
         x-data="driverCalendar(@js($cal), @js($this->canEditSchedule()))">

        <div class="myat-cal-bar">
            <div class="myat-cal-nav">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="move(-1)">Προηγούμενος</x-filament::button>
                @if ($offset !== 0)
                    <x-filament::button color="gray" size="sm" wire:click="move(0)">Σήμερα</x-filament::button>
                @endif
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" wire:click="move(1)">Επόμενος</x-filament::button>
            </div>
            <div class="myat-cal-legend">
                @foreach (\App\Filament\Pages\DriverCalendar::STATUSES as $code => [$label, $color])
                    <span style="background:{{ $color }};{{ in_array($code, [3, 8, 5]) ? 'color:#fff' : '' }}">{{ $code }} · {{ $label }}</span>
                @endforeach
            </div>
        </div>

        <div class="myat-cal-scroll" @mouseleave="hr = null; hc = null">
            <table>
                <thead>
                    <tr>
                        <th class="myat-cal-name" rowspan="2">Οδηγός</th>
                        <th class="myat-cal-sum" rowspan="2" style="background:#FFC7CE" title="ΡΕΠΟ τον πρώτο μήνα">0s</th>
                        <th class="myat-cal-sum" rowspan="2" style="background:#b4a7d6" title="ΑΔΕΙΑ τον πρώτο μήνα">9s</th>
                        <template x-for="m in c.months" :key="m.name">
                            <th class="myat-cal-month" :colspan="m.count" x-text="m.name"></th>
                        </template>
                    </tr>
                    <tr>
                        <template x-for="(d, i) in c.days" :key="d.date">
                            <th class="myat-cal-day" :class="{ 'is-last': d.last, 'is-weekend': d.weekend, 'is-today': d.date === c.today, 'is-hl': hc === i }">
                                <span x-text="d.n"></span><small x-text="d.w"></small>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(dr, r) in c.drivers" :key="dr.id">
                        <tr>
                            <td class="myat-cal-name" :class="{ 'is-hl': hr === r }" :style="dr.style" x-text="dr.name"></td>
                            <td class="myat-cal-sum" style="background:#FFC7CE" x-text="monthCount(dr.id, '0')"></td>
                            <td class="myat-cal-sum" style="background:#b4a7d6" x-text="monthCount(dr.id, '9')"></td>
                            <template x-for="(d, i) in c.days" :key="d.date">
                                <td class="myat-cal-cell"
                                    :class="{ 'is-last': d.last, 'is-today': d.date === c.today, 'is-hl': (hr === r && i <= hc) || (hc === i && r <= hr), 'is-edit': editing === dr.id + '|' + d.date, 'is-dark': ['3', '5', '8'].includes(val(dr.id, d.date)) }"
                                    :style="bg(val(dr.id, d.date))"
                                    @mouseenter="hr = r; hc = i"
                                    @click="open(dr.id, d.date)">
                                    <template x-if="editing === dr.id + '|' + d.date">
                                        <input type="text" inputmode="numeric" maxlength="1" x-init="$nextTick(() => { $el.focus(); $el.select() })"
                                               :value="val(dr.id, d.date)"
                                               @input="$el.value = $el.value.replace(/[^0-9]/g, '')"
                                               @keydown.enter.prevent="$el.blur()"
                                               @keydown.escape.prevent="editing = null"
                                               @keydown.tab.prevent="save(dr.id, d.date, $el.value, i + ($event.shiftKey ? -1 : 1))"
                                               @blur="save(dr.id, d.date, $el.value)">
                                    </template>
                                    <span x-show="editing !== dr.id + '|' + d.date" x-text="val(dr.id, d.date)"></span>
                                </td>
                            </template>
                        </tr>
                    </template>
                    <tr class="myat-cal-total is-first">
                        <td class="myat-cal-name">ΡΕΠΟ</td><td></td><td></td>
                        <template x-for="d in c.days" :key="d.date">
                            <td :class="{ 'is-last': d.last, 'is-bad': dayCount(d.date, ['0', '8', '9']) > 2 }" x-text="dayCount(d.date, ['0', '8', '9'])"></td>
                        </template>
                    </tr>
                    <tr class="myat-cal-total">
                        <td class="myat-cal-name">Sprinter</td><td></td><td></td>
                        <template x-for="d in c.days" :key="d.date">
                            <td :class="{ 'is-last': d.last, 'is-bad': dayCount(d.date, ['3']) < 3 }" x-text="dayCount(d.date, ['3'])"></td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @script
    <script>
        Alpine.data('driverCalendar', (c, canEdit) => ({
            c, canEdit, hr: null, hc: null, editing: null,
            val(id, date) { return (this.c.cells[id] || {})[date] ?? '' },
            bg(v) { return this.c.colors[v] ? 'background:' + this.c.colors[v] : '' },
            monthCount(id, s) {
                const row = this.c.cells[id] || {};
                return Object.keys(row).filter((d) => d.startsWith(this.c.firstMonth + '-') && row[d] === s).length;
            },
            dayCount(date, list) {
                return this.c.drivers.filter((d) => list.includes(this.val(d.id, date))).length;
            },
            open(id, date) {
                if (this.canEdit) this.editing = id + '|' + date;
            },
            async save(id, date, value, nextIndex = null) {
                if (this.editing !== id + '|' + date) return;
                const next = this.c.days[nextIndex];
                this.editing = null;
                if (next) this.$nextTick(() => this.open(id, next.date));
                const before = this.val(id, date);
                if (value === before) return;
                this.put(id, date, value);
                try {
                    await $wire.setStatus(id, date, value);
                } catch (e) {
                    this.put(id, date, before);
                    new FilamentNotification().title('Δεν αποθηκεύτηκε').danger().send();
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
