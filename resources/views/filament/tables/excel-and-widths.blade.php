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

    /* ---------------- table box fits the screen (computers) ----------------
       The table scrolls inside a box that ends at the bottom of the window, so its horizontal
       scrollbar is in view without scrolling the page. The box height is what is left of the
       window below the box's top, minus the table footer (pagination). */
    const fitBoxes = () => {
        const wide = innerWidth >= 1024;
        document.querySelectorAll('.fi-ta-ctn .fi-ta-content-ctn').forEach((box) => {
            if (! wide || ! box.querySelector(':scope > .fi-ta-table')) { box.style.maxHeight = ''; return; }
            const top = box.getBoundingClientRect().top + scrollY;
            const footer = box.closest('.fi-ta-ctn')?.querySelector('.fi-ta-footer, .fi-pagination')?.getBoundingClientRect().height ?? 0;
            const h = Math.max(320, Math.floor(innerHeight - top - footer - 24));
            box.style.maxHeight = h + 'px';
        });
    };
    let fitQueued = false;
    const queueFit = () => { if (fitQueued) return; fitQueued = true; requestAnimationFrame(() => { fitQueued = false; fitBoxes(); }); };
    addEventListener('resize', queueFit);
    document.addEventListener('DOMContentLoaded', queueFit);
    document.addEventListener('livewire:init', () => window.Livewire.hook('morph.updated', queueFit));
    new ResizeObserver(queueFit).observe(document.documentElement);

    /* ---------------- widths ---------------- */
    const css = () => {
        const out = [];
        for (const [name, cols] of Object.entries(T.widths)) {
            for (const [k, w] of Object.entries(cols || {})) {
                const s = `[wire\\:name="${CSS.escape(name)}"]`;
                out.push(`${s} .fi-ta-table th.fi-ta-header-cell-${CSS.escape(k)}, ${s} .fi-ta-table td.fi-ta-cell-${CSS.escape(k)} { width:${w}px; min-width:${w}px; max-width:${w}px; overflow:hidden; }`);
                // A resized column wraps its text onto more lines instead of cutting it off.
                out.push(`${s} .fi-ta-table td.fi-ta-cell-${CSS.escape(k)} *, ${s} .fi-ta-table th.fi-ta-header-cell-${CSS.escape(k)} * { min-width:0 !important; max-width:100%; white-space:normal !important; overflow-wrap:break-word; word-break:normal; text-overflow:clip !important; -webkit-line-clamp:unset !important; }`);
                out.push(`${s} .fi-ta-table th.fi-ta-header-cell-${CSS.escape(k)} { white-space:normal !important; }`);
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

    /* ---------------- Excel export (choose columns, saved exports) and saved filters ---------------- */
    const panelClose = () => { document.querySelector('.myat-panel-wrap')?.remove(); };
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') panelClose(); });

    const openExport = async (btn) => {
        const c = comp(btn), w = wire(c);
        panelClose();
        const wrap = document.createElement('div');
        wrap.className = 'myat-panel-wrap';
        wrap.innerHTML = `<div class="myat-panel-backdrop"></div>
            <aside class="myat-panel" role="dialog" aria-modal="true" aria-labelledby="myat-exp-title">
                <header class="myat-panel-head"><h2 id="myat-exp-title">Εξαγωγή σε Excel</h2>
                    <button type="button" class="myat-panel-x" aria-label="Κλείσιμο">&times;</button></header>
                <div class="myat-panel-body"><div class="myat-xf-loading">…</div></div>
            </aside>`;
        document.body.appendChild(wrap);
        wrap.querySelector('.myat-panel-backdrop').onclick = panelClose;
        wrap.querySelector('.myat-panel-x').onclick = panelClose;

        let cols, presets;
        try { [cols, presets] = await Promise.all([w.call('exportColumns'), w.call('tablePresets', 'export')]); } catch (e) { cols = []; presets = []; }
        const body = wrap.querySelector('.myat-panel-body');
        if (! cols?.length) { body.innerHTML = '<p class="myat-xf-loading">Δεν υπάρχουν στήλες για εξαγωγή.</p>'; return; }
        const sel = new Set(cols.filter((x) => x.visible).map((x) => x.name));
        const title = (document.querySelector('.fi-header-heading')?.innerText || 'export').trim();

        body.innerHTML = `
            <label class="myat-panel-field"><span>Αποθηκευμένες εξαγωγές</span>
                <span class="myat-panel-row"><select class="myat-panel-input myat-exp-preset"><option value="">—</option>
                ${presets.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select>
                <button type="button" class="myat-zb-btn myat-exp-del" title="Διαγραφή" aria-label="Διαγραφή αποθηκευμένης εξαγωγής">&times;</button></span></label>
            <div class="myat-panel-row myat-exp-quick">
                <button type="button" class="myat-zb-btn" data-q="visible">Ορατές στήλες</button>
                <button type="button" class="myat-zb-btn" data-q="all">Όλες</button>
                <button type="button" class="myat-zb-btn" data-q="none">Καμία</button>
                <span class="myat-exp-count"></span>
            </div>
            <input type="search" class="myat-panel-input myat-exp-search" placeholder="Αναζήτηση στήλης…">
            <div class="myat-exp-list">${cols.map((x) => `<label><input type="checkbox" value="${esc(x.name)}"> ${esc(x.label)}</label>`).join('')}</div>
            <p class="myat-panel-note">Εξάγονται οι γραμμές που δείχνει ο πίνακας τώρα (καρτέλα, αναζήτηση, φίλτρα), όλες οι σελίδες.</p>
            <div class="myat-panel-row"><input type="text" class="myat-panel-input myat-exp-name" placeholder="Όνομα για αποθήκευση…" maxlength="80">
                <button type="button" class="myat-zb-btn myat-exp-save">Αποθήκευση</button></div>`;
        const foot = document.createElement('footer');
        foot.className = 'myat-panel-foot';
        foot.innerHTML = '<button type="button" class="myat-xf-cancel">Άκυρο</button><button type="button" class="myat-xf-ok myat-exp-go">Λήψη Excel</button>';
        wrap.querySelector('.myat-panel').appendChild(foot);
        foot.querySelector('.myat-xf-cancel').onclick = panelClose;

        const boxes = () => [...body.querySelectorAll('.myat-exp-list input')];
        const sync = () => { boxes().forEach((b) => b.checked = sel.has(b.value)); body.querySelector('.myat-exp-count').textContent = `${sel.size} / ${cols.length}`; foot.querySelector('.myat-exp-go').disabled = sel.size === 0; };
        sync();
        body.querySelector('.myat-exp-list').addEventListener('change', (e) => { e.target.checked ? sel.add(e.target.value) : sel.delete(e.target.value); sync(); });
        body.querySelectorAll('[data-q]').forEach((b) => b.onclick = () => {
            sel.clear();
            if (b.dataset.q === 'all') cols.forEach((x) => sel.add(x.name));
            if (b.dataset.q === 'visible') cols.filter((x) => x.visible).forEach((x) => sel.add(x.name));
            sync();
        });
        const search = body.querySelector('.myat-exp-search');
        search.oninput = () => { const q = search.value.toLowerCase(); body.querySelectorAll('.myat-exp-list label').forEach((l) => l.hidden = q && ! l.innerText.toLowerCase().includes(q)); };
        const presetSel = body.querySelector('.myat-exp-preset');
        presetSel.onchange = () => {
            const p = presets.find((x) => String(x.id) === presetSel.value);
            if (! p) return;
            sel.clear(); (p.data.columns || []).forEach((n) => cols.some((x) => x.name === n) && sel.add(n));
            body.querySelector('.myat-exp-name').value = p.name; sync();
        };
        body.querySelector('.myat-exp-del').onclick = async () => {
            const id = presetSel.value; if (! id) return;
            await w.call('deletePreset', 'export', +id);
            presets = presets.filter((x) => String(x.id) !== id); presetSel.querySelector(`option[value="${id}"]`)?.remove(); presetSel.value = '';
        };
        body.querySelector('.myat-exp-save').onclick = async () => {
            const name = body.querySelector('.myat-exp-name').value.trim(); if (! name || ! sel.size) return;
            const ordered = cols.map((x) => x.name).filter((n) => sel.has(n));
            const id = await w.call('savePreset', 'export', name, ordered);
            presets = presets.filter((x) => x.id !== id).concat([{ id, name, data: { columns: ordered } }]);
            presetSel.innerHTML = '<option value="">—</option>' + presets.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
            presetSel.value = String(id);
        };
        foot.querySelector('.myat-exp-go').onclick = async (e) => {
            const go = e.currentTarget; go.disabled = true; go.textContent = 'Ετοιμάζεται…';
            try {
                const url = await w.call('exportTable', cols.map((x) => x.name).filter((n) => sel.has(n)), title);
                if (url) { window.location.href = url; panelClose(); }
            } catch (err) { go.disabled = false; go.textContent = 'Λήψη Excel'; }
        };
    };

    /* Saved filters: a list to apply/delete and a name box to save, drawn in `box` (the toolbar popover
       or the filter side panel). From the side panel, saving first applies what is set in the panel. */
    const savedUi = async (box, c, { inPanel = false, onApplied = () => {} } = {}) => {
        const w = wire(c);
        box.innerHTML = '<div class="myat-xf-loading">…</div>';
        let presets = [];
        try { presets = await w.call('tablePresets', 'filters'); } catch (e) {}
        const render = () => {
            box.innerHTML = `<div class="myat-xf-head">Αποθηκευμένα φίλτρα</div>
                <div class="myat-saved-list">${presets.length ? presets.map((p) => `<div class="myat-saved-item"><button type="button" class="myat-saved-apply" data-id="${p.id}">${esc(p.name)}</button><button type="button" class="myat-saved-del" data-id="${p.id}" aria-label="Διαγραφή ${esc(p.name)}">&times;</button></div>`).join('') : '<div class="myat-xf-loading">Δεν έχετε αποθηκεύσει φίλτρα σε αυτόν τον πίνακα.</div>'}</div>
                <div class="myat-panel-row" style="margin-top:.5rem"><input type="text" class="myat-panel-input myat-saved-name" placeholder="Όνομα…" maxlength="80"><button type="button" class="myat-xf-ok myat-saved-save">Αποθήκευση</button></div>
                <div class="myat-panel-note">${inPanel ? 'Εφαρμόζει και αποθηκεύει τα φίλτρα του πίνακα, την καρτέλα, την αναζήτηση και τα φίλτρα στηλών.' : 'Αποθηκεύει την καρτέλα, την αναζήτηση, τα φίλτρα και τα φίλτρα στηλών που ισχύουν τώρα.'}</div>`;
            box.querySelectorAll('.myat-saved-apply').forEach((b) => b.onclick = async () => {
                b.disabled = true;
                const active = await w.call('applyFilterPreset', +b.dataset.id);
                T.active[sessKey(nameOf(c))] = Array.isArray(active) ? active : [];
                onApplied();
                inject();
            });
            box.querySelectorAll('.myat-saved-del').forEach((b) => b.onclick = async () => { await w.call('deletePreset', 'filters', +b.dataset.id); presets = presets.filter((p) => String(p.id) !== b.dataset.id); render(); });
            const save = async () => {
                const name = box.querySelector('.myat-saved-name').value.trim(); if (! name) return box.querySelector('.myat-saved-name').focus();
                const btn = box.querySelector('.myat-saved-save'); btn.disabled = true;
                try {
                    const id = await w.call(inPanel ? 'savePanelPreset' : 'savePreset', 'filters', name);
                    presets = presets.filter((p) => p.id !== id).concat([{ id, name }]).sort((a, b) => a.name.localeCompare(b.name));
                } finally { render(); }
            };
            box.querySelector('.myat-saved-save').onclick = save;
            box.querySelector('.myat-saved-name').onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } };
        };
        render();
    };

    const openSaved = async (btn) => {
        const c = comp(btn);
        close();
        pop = document.createElement('div');
        pop.className = 'myat-xf-pop myat-saved-pop'; pop.dataset.for = '__saved';
        document.body.appendChild(pop);
        const r = btn.getBoundingClientRect();
        pop.style.top = (scrollY + r.bottom + 6) + 'px';
        pop.style.left = Math.max(8, Math.min(scrollX + r.right - 288, scrollX + document.documentElement.clientWidth - 300)) + 'px';
        await savedUi(pop, c, { onApplied: close });
    };

    // Inside the filter side panel (the box is wire:ignore'd, so it is filled once per panel).
    const fillPanels = () => document.querySelectorAll('[data-myat-saved-inline]:not([data-ready])').forEach((box) => {
        const c = comp(box); if (! c || ! wire(c)) return;
        box.dataset.ready = '1';
        savedUi(box, c, { inPanel: true });
    });
    new MutationObserver(() => fillPanels()).observe(document.documentElement, { childList: true, subtree: true });

    document.addEventListener('click', (e) => {
        const ex = e.target.closest?.('[data-myat-export]');
        if (ex) { e.preventDefault(); return openExport(ex); }
        const sv = e.target.closest?.('[data-myat-saved]');
        if (sv) { e.preventDefault(); if (pop && pop.dataset.for === '__saved') return close(); return openSaved(sv); }
    });

    /* ---------------- Excel-style filters ---------------- */
    const inject = () => {
        document.querySelectorAll('.fi-ta-table thead th.fi-ta-header-cell').forEach((th) => {
            const key = th.dataset.myatCol, c = comp(th);
            // Columns worked out per row (not in the database) have no filter button.
            if (th.dataset.myatNofilter || th.querySelector('[data-myat-nofilter]')) return;
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
