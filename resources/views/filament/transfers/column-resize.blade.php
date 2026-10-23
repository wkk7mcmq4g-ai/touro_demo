{{-- Drag the right edge of a column header to resize it; double-click the edge to reset.
     Widths are kept per browser (localStorage). Event delegation + one <style> element,
     so it survives Livewire re-rendering the table. --}}
<style id="myat-column-widths"></style>
<script>
(() => {
    if (window.myatColumnResize) return;
    window.myatColumnResize = true;

    const KEY = 'myat.transfers.columnWidths';
    const EDGE = 7;
    const style = () => document.getElementById('myat-column-widths');
    let widths = {};
    try { widths = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}

    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(widths)); } catch (e) {} };
    const apply = () => {
        const el = style();
        if (! el) return;
        el.textContent = Object.entries(widths).map(([k, w]) => `
            .fi-ta-table th.fi-ta-header-cell-${k}, .fi-ta-table td.fi-ta-cell-${k} {
                width: ${w}px; min-width: ${w}px; max-width: ${w}px; overflow: hidden; text-overflow: ellipsis;
            }
            .fi-ta-table td.fi-ta-cell-${k} * { min-width: 0 !important; max-width: 100%; }
        `).join('');
    };
    const keyOf = (th) => [...th.classList]
        .find((c) => c.startsWith('fi-ta-header-cell-') && ! ['fi-ta-header-cell-sorted'].includes(c))
        ?.slice('fi-ta-header-cell-'.length);
    const edgeHeader = (e) => {
        const th = e.target.closest?.('.fi-ta-table th.fi-ta-header-cell');
        if (! th || ! keyOf(th)) return null;
        const r = th.getBoundingClientRect();
        return e.clientX >= r.right - EDGE && e.clientX <= r.right + 2 ? th : null;
    };

    let drag = null;
    let swallowClick = false;

    document.addEventListener('mousemove', (e) => {
        if (drag) {
            widths[drag.key] = Math.max(40, Math.round(drag.width + e.clientX - drag.x));
            apply();
            return;
        }
        const th = e.target.closest?.('.fi-ta-table th.fi-ta-header-cell');
        if (th) th.style.cursor = edgeHeader(e) ? 'col-resize' : '';
    });

    document.addEventListener('mousedown', (e) => {
        const th = e.button === 0 && edgeHeader(e);
        if (! th) return;
        e.preventDefault();
        e.stopPropagation();
        drag = { key: keyOf(th), x: e.clientX, width: th.getBoundingClientRect().width };
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    }, true);

    document.addEventListener('mouseup', () => {
        if (! drag) return;
        drag = null;
        swallowClick = true; // don't sort the column we just resized
        setTimeout(() => swallowClick = false, 0);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        save();
    });

    document.addEventListener('click', (e) => {
        if (swallowClick || edgeHeader(e)) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    document.addEventListener('dblclick', (e) => {
        const th = edgeHeader(e);
        if (! th) return;
        e.preventDefault();
        delete widths[keyOf(th)];
        save();
        apply();
    }, true);

    window.myatResetColumnWidths = () => { widths = {}; save(); apply(); };

    apply();
    document.addEventListener('livewire:navigated', apply);
})();
</script>
