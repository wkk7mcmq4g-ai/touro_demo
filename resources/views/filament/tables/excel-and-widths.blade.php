{{--
    Every table: Excel-style filters from the column headers (a funnel per column, dates grouped by
    year and month) and column widths dragged on the header's right edge (double-click resets).
    Both are kept per user (App\Support\ExcelFilters, App\Support\TableLayouts). Plain JS with event
    delegation, outside Livewire, so it survives the tables re-rendering.
--}}
@php
    $excelState = collect((array) session('myat_excel', []))->map(fn ($cols) => array_keys((array) $cols))->all();
@endphp
<style id="myat-column-widths"></style>
<script>
(() => {
    if (window.myatTables) return;

    /* Feedback while the server answers: a thin bar at the top, and a tab looks pressed at once. */
    let busy = 0;
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ succeed, fail }) => {
            busy++; document.body.classList.add('myat-busy');
            const done = () => { if (--busy <= 0) { busy = 0; document.body.classList.remove('myat-busy'); } };
            succeed(done); fail(done);
        });
    });
    document.addEventListener('click', (e) => {
        const tab = e.target.closest?.('.fi-tabs-item');
        if (! tab) return;
        tab.parentElement?.querySelectorAll('.fi-tabs-item').forEach((t) => t.classList.toggle('myat-tab-pending', t === tab));
    }, true);
    const T = window.myatTables = {
        widths: @json((object) session('myat.widths', [])),
        active: @json((object) $excelState),
    };
    const EDGE = 7;
    const MONTHS = ['Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'];
    const FUNNEL = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.6 3.5A1 1 0 0 1 3.5 2h13a1 1 0 0 1 .8 1.6L12 10.3V16a1 1 0 0 1-.55.9l-2 1A1 1 0 0 1 8 17v-6.7L2.7 3.6a1 1 0 0 1-.1-.1Z"/></svg>';

    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const comp = (el) => el.closest('[wire\\:id]');
    const nameOf = (c) => c?.getAttribute('wire:name') || '';
    const keyOf = (th) => [...th.classList].find((c) => c.startsWith('fi-ta-header-cell-') && c !== 'fi-ta-header-cell-sorted')?.slice(18);
    const sessKey = (name) => name.replaceAll('.', '_');
    const wire = (c) => window.Livewire?.find(c.getAttribute('wire:id'));

    /* ---------------- widths ---------------- */
    const css = () => {
        const out = [];
        for (const [name, cols] of Object.entries(T.widths)) {
            for (const [k, w] of Object.entries(cols || {})) {
                const s = `[wire\\:name="${CSS.escape(name)}"]`;
                out.push(`${s} .fi-ta-table th.fi-ta-header-cell-${CSS.escape(k)}, ${s} .fi-ta-table td.fi-ta-cell-${CSS.escape(k)} { width:${w}px; min-width:${w}px; max-width:${w}px; overflow:hidden; text-overflow:ellipsis; }`);
                out.push(`${s} .fi-ta-table td.fi-ta-cell-${CSS.escape(k)} * { min-width:0 !important; max-width:100%; }`);
            }
        }
        document.getElementById('myat-column-widths').textContent = out.join('\n');
    };
    const edgeHeader = (e) => {
        const th = e.target.closest?.('.fi-ta-table th.fi-ta-header-cell');
        if (! th || ! keyOf(th)) return null;
        const r = th.getBoundingClientRect();
        return e.clientX >= r.right - EDGE && e.clientX <= r.right + 2 ? th : null;
    };
    let drag = null, swallow = false;
    const saveWidths = (c) => wire(c)?.call('saveColumnWidths', T.widths[nameOf(c)] || {});

    document.addEventListener('mousemove', (e) => {
        if (drag) {
            (T.widths[drag.name] ||= {})[drag.key] = Math.max(40, Math.round(drag.width + e.clientX - drag.x));
            css();
            return;
        }
        const th = e.target.closest?.('.fi-ta-table th.fi-ta-header-cell');
        if (th) th.style.cursor = edgeHeader(e) ? 'col-resize' : '';
    });
    document.addEventListener('mousedown', (e) => {
        const th = e.button === 0 && edgeHeader(e);
        if (! th) return;
        e.preventDefault(); e.stopPropagation();
        drag = { c: comp(th), name: nameOf(comp(th)), key: keyOf(th), x: e.clientX, width: th.getBoundingClientRect().width };
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    }, true);
    document.addEventListener('mouseup', () => {
        if (! drag) return;
        const c = drag.c; drag = null; swallow = true; setTimeout(() => swallow = false, 0);
        document.body.style.cursor = ''; document.body.style.userSelect = '';
        saveWidths(c);
    });
    document.addEventListener('click', (e) => { if (swallow || edgeHeader(e)) { e.preventDefault(); e.stopPropagation(); } }, true);
    document.addEventListener('dblclick', (e) => {
        const th = edgeHeader(e);
        if (! th) return;
        e.preventDefault();
        const c = comp(th);
        delete (T.widths[nameOf(c)] || {})[keyOf(th)];
        css(); saveWidths(c);
    });
    window.myatResetColumnWidths = () => {
        document.querySelectorAll('[wire\\:id]').forEach((c) => {
            if (T.widths[nameOf(c)] && c.querySelector('.fi-ta-table')) { T.widths[nameOf(c)] = {}; saveWidths(c); }
        });
        css();
    };

    /* ---------------- Excel-style filters ---------------- */
    const inject = () => {
        document.querySelectorAll('.fi-ta-table thead th.fi-ta-header-cell').forEach((th) => {
            const key = th.dataset.myatCol, c = comp(th);
            if (! key || ! c || ! /^[A-Za-z0-9_ ]+(\.[A-Za-z0-9_]+)?$/.test(key)) return;
            let btn = th.querySelector('.myat-xf-btn');
            if (! btn) {
                // Next to the label, inside Filament's sort control when there is one (the click is
                // caught first, so it never sorts).
                btn = document.createElement('span');
                btn.setAttribute('role', 'button'); btn.tabIndex = 0;
                btn.className = 'myat-xf-btn'; btn.innerHTML = FUNNEL;
                btn.title = 'Φίλτρο'; btn.dataset.col = key;
                const label = th.querySelector(':scope > .fi-ta-header-cell-sort-btn, :scope > button, :scope > [role=button], :scope > span');
                const chevron = label?.querySelector(':scope > svg.fi-icon:not(.fi-loading-indicator)');
                chevron ? chevron.after(btn) : (label || th).appendChild(btn);
            }
            btn.classList.toggle('myat-xf-on', (T.active[sessKey(nameOf(c))] || []).includes(key));
        });
    };
    let t = null;
    new MutationObserver(() => { clearTimeout(t); t = setTimeout(inject, 60); }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', () => { css(); inject(); });
    css();

    let pop = null;
    const close = () => { pop?.remove(); pop = null; };
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
        const btn = (e.key === 'Enter' || e.key === ' ') && e.target.closest?.('.myat-xf-btn');
        if (btn) { e.preventDefault(); e.stopPropagation(); openFilter(btn); }
    }, true);
    document.addEventListener('mousedown', (e) => { if (pop && ! pop.contains(e.target) && ! e.target.closest('.myat-xf-btn')) close(); });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('.myat-xf-btn');
        if (! btn || swallow || edgeHeader(e)) return;
        e.preventDefault(); e.stopPropagation();
        openFilter(btn);
    }, true);

    const openFilter = async (btn) => {
        if (pop && pop.dataset.for === btn.dataset.col) return close();
        close();
        const c = comp(btn), w = wire(c), col = btn.dataset.col;
        const title = btn.closest('th').innerText.trim();
        pop = document.createElement('div');
        pop.className = 'myat-xf-pop'; pop.dataset.for = col;
        pop.innerHTML = '<div class="myat-xf-loading">…</div>';
        document.body.appendChild(pop);
        const r = btn.getBoundingClientRect();
        pop.style.top = (window.scrollY + r.bottom + 6) + 'px';
        pop.style.left = Math.max(8, Math.min(window.scrollX + r.left - 10, window.scrollX + document.documentElement.clientWidth - 300)) + 'px';

        let data;
        try { data = await w.call('excelFilterValues', col); } catch (err) { data = null; }
        if (! pop || pop.dataset.for !== col) return;
        if (! data || ! data.values) { pop.innerHTML = '<div class="myat-xf-loading">Δεν γίνεται φίλτρο σε αυτή τη στήλη.</div>'; return; }

        const all = data.values.map((x) => x.v);
        const sel = new Set(data.selected ?? all);
        const isDate = data.type === 'date';

        // date tree: year > month > day
        const tree = {};
        if (isDate) data.values.forEach(({ v }) => {
            if (v === '__empty__') return;
            const [y, m] = v.split('-');
            ((tree[y] ||= {})[m] ||= []).push(v);
        });

        const render = (q = '') => {
            const ql = q.toLowerCase();
            const box = pop.querySelector('.myat-xf-list');
            let html = '';
            if (isDate && ! q) {
                const years = Object.keys(tree).sort().reverse();
                for (const y of years) {
                    const ydays = Object.values(tree[y]).flat();
                    html += `<details class="myat-xf-year" ${years.length === 1 ? 'open' : ''}><summary>${chk(ydays)} <b>${y}</b></summary>`;
                    for (const m of Object.keys(tree[y]).sort()) {
                        const days = tree[y][m];
                        html += `<details class="myat-xf-month"><summary>${chk(days)} ${MONTHS[+m - 1]}</summary>`;
                        html += days.map((d) => `<label class="myat-xf-day">${chk([d])} ${d.slice(8)}</label>`).join('');
                        html += '</details>';
                    }
                    html += '</details>';
                }
                if (all.includes('__empty__')) html += `<label>${chk(['__empty__'])} (Κενά)</label>`;
            } else {
                html = data.values.filter((x) => ! ql || String(x.label).toLowerCase().includes(ql))
                    .map((x) => `<label>${chk([x.v])} ${esc(isDate && x.v !== '__empty__' ? x.v.split('-').reverse().join('/') : x.label)}</label>`).join('')
                    || '<div class="myat-xf-loading">Κανένα αποτέλεσμα</div>';
            }
            box.innerHTML = html;
            sync();
        };
        // Ticks follow the selection without re-drawing, so open years and months stay open.
        const sync = () => {
            pop.querySelectorAll('.myat-xf-list input[data-v]').forEach((i) => {
                const vs = JSON.parse(i.dataset.v), n = vs.filter((v) => sel.has(v)).length;
                i.checked = n === vs.length; i.indeterminate = n > 0 && n < vs.length;
            });
            const all_ = pop.querySelector('.myat-xf-all'), n = all.filter((v) => sel.has(v)).length;
            all_.checked = n === all.length; all_.indeterminate = n > 0 && n < all.length;
            pop.querySelector('.myat-xf-ok').disabled = n === 0;
        };
        const chk = (vs) => `<input type="checkbox" data-v='${esc(JSON.stringify(vs))}' ${vs.every((v) => sel.has(v)) ? 'checked' : ''}>`;

        pop.innerHTML = `
            <div class="myat-xf-head">${esc(title)}</div>
            <input type="search" class="myat-xf-search" placeholder="Αναζήτηση…">
            <label class="myat-xf-allrow"><input type="checkbox" class="myat-xf-all"> (Επιλογή όλων)</label>
            <div class="myat-xf-list"></div>
            ${data.truncated ? '<div class="myat-xf-note">Εμφανίζονται οι πρώτες 2000 τιμές.</div>' : ''}
            ${(T.active[sessKey(nameOf(c))] || []).some((x) => x !== col) ? '<button type="button" class="myat-xf-clearall">Καθαρισμός φίλτρων σε όλες τις στήλες</button>' : ''}
            <div class="myat-xf-actions">
                <button type="button" class="myat-xf-clear">Καθαρισμός</button>
                <span></span>
                <button type="button" class="myat-xf-cancel">Άκυρο</button>
                <button type="button" class="myat-xf-ok">OK</button>
            </div>`;
        render();
        const search = pop.querySelector('.myat-xf-search');
        search.addEventListener('input', () => render(search.value));
        search.focus();
        pop.addEventListener('change', (ev) => {
            const i = ev.target;
            if (i.classList.contains('myat-xf-all')) {
                const visible = search.value ? [...pop.querySelectorAll('.myat-xf-list input[data-v]')].flatMap((x) => JSON.parse(x.dataset.v)) : all;
                visible.forEach((v) => i.checked ? sel.add(v) : sel.delete(v));
            } else if (i.dataset.v) {
                JSON.parse(i.dataset.v).forEach((v) => i.checked ? sel.add(v) : sel.delete(v));
            }
            sync();
        });
        const done = async (values) => {
            close();
            const s = sessKey(nameOf(c));
            const set = new Set(T.active[s] || []);
            values === null ? set.delete(col) : set.add(col);
            T.active[s] = [...set];
            await w.call('applyExcelFilter', col, values);
            inject();
        };
        pop.querySelector('.myat-xf-cancel').onclick = close;
        const clearAll = pop.querySelector('.myat-xf-clearall');
        if (clearAll) clearAll.onclick = async () => { close(); T.active[sessKey(nameOf(c))] = []; await w.call('clearExcelFilters'); inject(); };
        pop.querySelector('.myat-xf-clear').onclick = () => done(null);
        pop.querySelector('.myat-xf-ok').onclick = () => done(all.every((v) => sel.has(v)) ? null : [...sel]);
    };
})();
</script>
