{{--
    Ημερήσιο πλάνο (App\Filament\Pages\DayTimeline): rows = vehicles, blocks = the day's jobs by time.
    Alpine draws it from one JSON payload. Overlaps within one of our vehicles are outlined red; a job
    with more passengers than the vehicle's seats gets a warning. Read only: click a block to open the
    transfer.
    Phones and tablets: a List view (one card per vehicle, jobs in time order; the default on upright
    phones) next to the timeline, and full screen like the tables (on by itself when held sideways).
--}}
@php($t = $this->timeline())
<x-filament-panels::page>
    <div class="myat-tl" wire:key="tl-{{ $t['date'] }}" x-data="dayTimeline(@js($t))" :class="{ 'is-list': view === 'list', 'is-full': full }">

        <div class="myat-tl-bar">
            <div class="myat-tl-nav">
                <div class="myat-cal-step" role="group" aria-label="Ημέρα">
                    <button type="button" wire:click="move(-1)" aria-label="Προηγούμενη ημέρα" title="Προηγούμενη ημέρα"><x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" /></button>
                    <button type="button" wire:click="move(0)" @disabled($t['date'] === now()->toDateString())>Σήμερα</button>
                    <button type="button" wire:click="move(1)" aria-label="Επόμενη ημέρα" title="Επόμενη ημέρα"><x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" /></button>
                </div>
                <input type="date" class="myat-tl-date" wire:model.live="date" aria-label="Ημερομηνία">
                <h2 class="myat-tl-title">{{ \Carbon\Carbon::parse($t['date'])->locale('el')->translatedFormat('l j F Y') }}</h2>
                <x-filament::loading-indicator class="h-5 w-5 text-gray-400" wire:loading />
            </div>
            <div class="myat-tl-stats">
                <span><b x-text="c.jobs.length"></b> δρομολόγια</span>
                <span :class="{ 'is-warn': unassigned > 0 }"><b x-text="unassigned"></b> χωρίς όχημα</span>
                <span :class="{ 'is-bad': clashes > 0 }"><b x-text="clashes"></b> επικαλύψεις</span>
            </div>
            <div class="myat-tl-tools">
                <div class="myat-tl-seg" role="tablist" aria-label="Προβολή">
                    <button type="button" role="tab" :aria-selected="view === 'timeline'" :class="{ 'is-active': view === 'timeline' }" @click="setView('timeline')" title="Χρονογραμμή">
                        <x-filament::icon icon="heroicon-m-bars-3-center-left" class="h-4 w-4" /><span>Χρονογραμμή</span>
                    </button>
                    <button type="button" role="tab" :aria-selected="view === 'list'" :class="{ 'is-active': view === 'list' }" @click="setView('list')" title="Λίστα">
                        <x-filament::icon icon="heroicon-m-list-bullet" class="h-4 w-4" /><span>Λίστα</span>
                    </button>
                </div>
                <div class="myat-tl-zoom" role="group" aria-label="Κλίμακα" x-show="view === 'timeline'">
                    <button type="button" @click="zoom(-1)" aria-label="Σμίκρυνση">−</button>
                    <button type="button" @click="zoom(1)" aria-label="Μεγέθυνση">+</button>
                </div>
                <a class="myat-tl-pdfbtn" :href="c.pdfUrl" target="_blank" rel="noopener" title="Εξαγωγή της ημέρας σε PDF">
                    <x-filament::icon icon="heroicon-m-document-arrow-down" class="h-4 w-4" /><span>PDF</span>
                </a>
                <button type="button" class="myat-tl-fullbtn" @click="toggleFull()" :aria-pressed="full" :title="full ? 'Έξοδος από πλήρη οθόνη' : 'Πλήρης οθόνη'">
                    <x-filament::icon icon="heroicon-m-arrows-pointing-out" class="h-4 w-4" x-show="! full" />
                    <x-filament::icon icon="heroicon-m-arrows-pointing-in" class="h-4 w-4" x-show="full" x-cloak />
                </button>
            </div>
        </div>

        <ul class="myat-tl-legend" x-show="view === 'timeline'">
            <li><i class="myat-tl-k is-Arrival"></i>Arrival</li>
            <li><i class="myat-tl-k is-Departure"></i>Departure</li>
            <li><i class="myat-tl-k is-Tour"></i>Tour</li>
            <li><i class="myat-tl-k is-other"></i>Άλλο</li>
            <li><i class="myat-tl-k is-est"></i>Εκτιμώμενη διάρκεια</li>
            <li><i class="myat-tl-k is-clash"></i>Επικάλυψη</li>
            <li class="myat-tl-hint">Κλικ σε δρομολόγιο: ανοίγει σε νέα καρτέλα</li>
        </ul>

        {{-- List: one card per vehicle with jobs (unassigned first), then the idle vehicles in one line --}}
        <div class="myat-tl-list" x-show="view === 'list'" x-cloak>
            <template x-for="row in rows.filter((r) => r.jobs.length)" :key="'l' + row.key">
                <section class="myat-tl-card" :class="'is-' + row.kind">
                    <header>
                        <div>
                            <h3 x-text="row.name"></h3>
                            <p :class="{ 'is-warn': row.noDriver }" x-text="row.sub"></p>
                        </div>
                        <span class="myat-tl-card-n" x-text="row.jobs.length"></span>
                    </header>
                    <ol>
                        <template x-for="j in [...row.timed, ...row.tbd]" :key="'li' + j.id">
                            <li>
                                <button type="button" class="myat-tl-item" :class="['is-' + typeClass(j), j.clash && 'is-clash']" @click="open(j)">
                                    <span class="myat-tl-item-time">
                                        <b x-text="j.start !== null ? hhmm(j.start) : (j.time || 'TBD')"></b>
                                        <small x-show="j.start !== null" x-text="'έως ' + hhmm(j.end) + (j.estimated ? '*' : '')"></small>
                                    </span>
                                    <span class="myat-tl-item-main">
                                        <span class="myat-tl-item-route" x-text="j.type === 'Tour' ? j.to : (j.from + ' → ' + j.to)"></span>
                                        <span class="myat-tl-item-meta" x-text="[j.type, j.pax ? j.pax + ' άτομα' : '', j.lead, j.flight].filter(Boolean).join(' · ')"></span>
                                        <span class="myat-tl-item-warn" x-show="j.clash">⚠ Επικαλύπτεται με άλλο δρομολόγιο</span>
                                        <span class="myat-tl-item-warn" x-show="j.over">⚠ Περισσότεροι επιβάτες από θέσεις</span>
                                    </span>
                                </button>
                            </li>
                        </template>
                    </ol>
                </section>
            </template>
            <p class="myat-tl-idle" x-show="rows.some((r) => r.kind === 'own' && ! r.jobs.length)">
                <b>Χωρίς δρομολόγια:</b> <span x-text="rows.filter((r) => r.kind === 'own' && ! r.jobs.length).map((r) => r.name).join(' · ')"></span>
            </p>
            <p class="myat-tl-idle" x-show="! c.jobs.length">Κανένα δρομολόγιο αυτή την ημέρα.</p>
            <p class="myat-tl-foot" x-show="c.jobs.some((j) => j.estimated && j.start !== null)">* εκτιμώμενη ώρα λήξης</p>
        </div>

        <div class="myat-tl-scroll" x-ref="scroll" x-show="view === 'timeline'">
            <div class="myat-tl-grid" :style="`--tl-w:${trackWidth}px`">
                {{-- hours --}}
                <div class="myat-tl-head">
                    <div class="myat-tl-corner">Όχημα</div>
                    <div class="myat-tl-hours">
                        <template x-for="h in hours" :key="h">
                            <span :style="`left:${(h * 60 - from) * ppm}px`" x-text="String(h % 24).padStart(2, '0') + ':00'"></span>
                        </template>
                    </div>
                </div>

                <template x-for="row in rows" :key="row.key">
                    <div class="myat-tl-row" :class="'is-' + row.kind">
                        <div class="myat-tl-label">
                            <span class="myat-tl-name" x-text="row.name"></span>
                            <span class="myat-tl-sub" :class="{ 'is-warn': row.noDriver && row.jobs.length }" x-text="row.sub"></span>
                            <span class="myat-tl-tbd" x-show="row.tbd.length">
                                <template x-for="j in row.tbd" :key="j.id">
                                    <button type="button" class="myat-tl-chip" :class="'is-' + typeClass(j)" x-text="'TBD #' + j.id"
                                            x-tooltip="{ content: tip(j), theme: $store.theme }"
                                            @click="open(j)"></button>
                                </template>
                            </span>
                        </div>
                        <div class="myat-tl-track" :style="`height:${Math.max(1, row.lanes) * 2.6 + 0.5}rem`">
                            <template x-for="h in hours" :key="'g' + h">
                                <i class="myat-tl-gridline" :style="`left:${(h * 60 - from) * ppm}px`"></i>
                            </template>
                            <i class="myat-tl-now" x-show="nowMin !== null" :style="`left:${(nowMin - from) * ppm}px`"></i>
                            <template x-for="j in row.timed" :key="j.id">
                                <button type="button" class="myat-tl-job"
                                        :class="['is-' + typeClass(j), j.estimated && 'is-est', j.clash && 'is-clash', j.over && 'is-over']"
                                        :style="`left:${(j.start - from) * ppm}px;width:${Math.max(28, (j.end - j.start) * ppm)}px;top:${j.lane * 2.6 + 0.25}rem`"
                                        x-tooltip="{ content: tip(j), theme: $store.theme }"
                                        @click="open(j)">
                                    <b x-text="j.time"></b>
                                    <span x-text="short(j)"></span>
                                    <em x-show="j.over" title="Περισσότεροι επιβάτες από θέσεις">⚠</em>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('dayTimeline', (c) => ({
            c, ppm: (() => { try { return Number(localStorage.getItem('myat-tl-ppm')) || 2 } catch (e) { return 2 } })(),
            nowMin: null,
            // List by default on upright phones; the choice is remembered on this device.
            view: (() => { try { return localStorage.getItem('myat-tl-view') || (innerWidth < 640 ? 'list' : 'timeline') } catch (e) { return innerWidth < 640 ? 'list' : 'timeline' } })(),
            full: false, forced: false, optedOut: false,
            sideways() { return innerWidth > innerHeight && (innerHeight < 500 || matchMedia('(pointer: coarse)').matches); },
            applyFull() {
                this.full = this.forced || (this.sideways() && ! this.optedOut);
                document.documentElement.classList.toggle('myat-immersive', this.full);
                document.documentElement.classList.toggle('myat-tl-full', this.full);
            },
            toggleFull() {
                if (this.full) { this.forced = false; if (this.sideways()) this.optedOut = true; }
                else { this.forced = true; this.optedOut = false; }
                this.applyFull();
            },
            setView(v) {
                this.view = v;
                try { localStorage.setItem('myat-tl-view', v) } catch (e) {}
                if (v === 'timeline') this.$nextTick(() => this.place());
            },
            // Same day redrawn: keep where the user was looking; otherwise open at the first job (or now).
            place() {
                const el = this.$refs.scroll, kept = window.myatTimelineScroll;
                if (! el || ! el.offsetParent) return;
                if (kept && kept.date === this.c.date) { el.scrollLeft = kept.left; el.scrollTop = kept.top; }
                else {
                    const first = Math.min(...this.c.jobs.filter((j) => j.start !== null).map((j) => j.start), this.nowMin ?? 1e9);
                    if (isFinite(first)) el.scrollLeft = Math.max(0, (first - this.from - 30) * this.ppm);
                }
                if (! el._kept) {
                    el._kept = true;
                    el.addEventListener('scroll', () => { window.myatTimelineScroll = { date: this.c.date, left: el.scrollLeft, top: el.scrollTop }; }, { passive: true });
                }
            },
            hhmm(m) { return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); },
            destroy() {
                removeEventListener('resize', this._onTurn);
                document.documentElement.classList.remove('myat-immersive', 'myat-tl-full');
            },
            init() {
                this._onTurn = () => { if (! this.sideways()) this.optedOut = false; this.applyFull(); };
                addEventListener('resize', this._onTurn);
                this.applyFull();
                const tick = () => {
                    const now = new Date();
                    const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                    this.nowMin = today === this.c.date ? now.getHours() * 60 + now.getMinutes() : null;
                };
                tick(); setInterval(tick, 60000);
                this.$nextTick(() => this.place());
            },
            get from() {
                const starts = this.c.jobs.filter((j) => j.start !== null).map((j) => j.start);
                return Math.min(5 * 60, Math.floor(Math.min(...starts, 1e9) / 60) * 60);
            },
            get to() {
                const ends = this.c.jobs.filter((j) => j.end !== null).map((j) => j.end);
                return Math.max(24 * 60, Math.ceil(Math.max(...ends, 0) / 60) * 60);
            },
            get hours() { const h = []; for (let m = this.from; m < this.to; m += 60) h.push(m / 60); return h; },
            get trackWidth() { return (this.to - this.from) * this.ppm; },
            get rows() {
                return this.c.rows.map((row) => {
                    const jobs = this.c.jobs.filter((j) => j.coach === row.key);
                    const timed = jobs.filter((j) => j.start !== null).sort((a, b) => a.start - b.start).map((j) => ({ ...j }));
                    // lanes: each block goes in the first lane free at its start
                    const laneEnds = [];
                    timed.forEach((j) => {
                        let lane = laneEnds.findIndex((end) => end <= j.start);
                        if (lane === -1) { lane = laneEnds.length; laneEnds.push(0); }
                        laneEnds[lane] = j.end; j.lane = lane;
                        // one of our vehicles can't be in two places: flag overlaps
                        j.clash = row.kind === 'own' && timed.some((o) => o.id !== j.id && o.start < j.end && j.start < o.end);
                        j.over = row.seats && j.pax > row.seats;
                    });
                    return { ...row, jobs, timed, tbd: jobs.filter((j) => j.start === null), lanes: laneEnds.length };
                });
            },
            get unassigned() { return this.c.jobs.filter((j) => j.coach === '').length; },
            get clashes() { return this.rows.reduce((n, r) => n + r.timed.filter((j) => j.clash).length, 0); },
            typeClass(j) { return ['Arrival', 'Departure', 'Tour'].includes(j.type) ? j.type : 'other'; },
            short(j) { return (j.type === 'Tour' ? j.to : (j.from + ' → ' + j.to)) + (j.pax ? ' · ' + j.pax + 'p' : ''); },
            tip(j) {
                const hhmm = (m) => String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
                return [
                    '#' + j.id + ' · ' + j.type + (j.customer ? ' · ' + j.customer : ''),
                    j.start !== null ? hhmm(j.start) + '–' + hhmm(j.end) + (j.estimated ? ' (εκτίμηση)' : '') : 'Ώρα: ' + (j.time || 'TBD'),
                    j.from + ' → ' + j.to,
                    (j.pax ? j.pax + ' άτομα' : '') + (j.lead ? ' · ' + j.lead : ''),
                    j.flight ? 'Πτήση/πλοίο: ' + j.flight : '',
                    j.clash ? '⚠ Επικαλύπτεται με άλλο δρομολόγιο του οχήματος' : '',
                    j.over ? '⚠ Περισσότεροι επιβάτες από τις θέσεις του οχήματος' : '',
                ].filter(Boolean).join('\n');
            },
            zoom(dir) {
                const steps = [1, 1.5, 2, 3, 4, 6];
                const i = steps.indexOf(this.ppm);
                this.ppm = steps[Math.max(0, Math.min(steps.length - 1, (i === -1 ? 2 : i) + dir))];
                try { localStorage.setItem('myat-tl-ppm', this.ppm) } catch (e) {}
            },
            open(j) {
                window.open(this.c.editUrl.replace('__ID__', j.id), '_blank');
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
