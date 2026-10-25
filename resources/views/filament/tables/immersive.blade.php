{{--
    Full screen ("immersive") mode and table zoom for wide tables, on phones, tablets and computers.
    Active only on pages that show the controls (App\Support\ImmersiveTables).

    - Full screen hides the menus (sidebar, phone menu button, bottom tab bar, breadcrumbs) and gives the
      page the whole screen. Turning a phone, tablet or foldable sideways (a touch screen wider than tall;
      any screen under 500px tall) enters it by itself; the button enters it anywhere
      (on Android also browser full screen, without the address bar). Exit or Esc leaves; if left while
      sideways it stays off until the phone is upright again.
    - Zoom (only in full screen): sideways touch screens fit the table to the width (until zoomed by hand),
      upright phones start at 57%, others at 100%. −/+ buttons, % resets; ctrl+wheel / trackpad pinch;
      two-finger pinch zooms only the table (scaled while the fingers move, laid out once at the end, the
      content under the fingers kept in place). Zoom 25–160%.
    The zoom lives in a stylesheet, not on the table element, so Livewire re-renders do not drop it.
--}}
<style id="myat-immersive-zoom"></style>
<script>
(() => {
    if (window.myatImmersive) return;
    const S = window.myatImmersive = { forced: false, optedOut: false, active: false, zoom: 1, manual: false, hist: [] };
    const MIN = 0.25, MAX = 1.6, START_UPRIGHT = 0.57, STEP = 1.15;
    const css = document.getElementById('myat-immersive-zoom');
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
    const round2 = (v) => Math.round(v * 100) / 100;
    const bar = () => document.querySelector('.myat-zoombar');
    const table = () => bar()?.closest('.fi-ta-ctn')?.querySelector('.fi-ta-content-ctn table.fi-ta-table');
    const scroller = () => table()?.parentElement;
    const touch = matchMedia('(pointer: coarse)').matches;
    // Held sideways: a phone (under 500px tall), or any touch screen wider than tall (tablets, foldables,
    // dual-screen phones opened up). Computers with a mouse keep the page as it is.
    const sideways = () => innerWidth > innerHeight && (innerHeight < 500 || touch);
    const phoneUpright = () => innerWidth < 640 && innerHeight >= innerWidth;
    let landscape = sideways();

    const apply = () => {
        css.textContent = S.active
            ? `html.myat-immersive .fi-ta-ctn table.fi-ta-table { zoom: ${S.zoom}; touch-action: pan-x pan-y; }`
            : '';
        const pct = bar()?.querySelector('.myat-zb-pct');
        if (pct) {
            pct.textContent = Math.round(S.zoom * 100) + '%';
            pct.title = pct.ariaLabel = fitting() ? 'Προσαρμογή στο πλάτος της οθόνης' : 'Πίσω στο αρχικό ζουμ';
        }
    };
    const fitting = () => S.active && landscape && ! S.manual;
    const setZoom = (z, manual) => { S.zoom = clamp(z, MIN, MAX); if (manual) S.manual = true; apply(); };
    const defaultZoom = () => (landscape ? 1 : phoneUpright() ? START_UPRIGHT : 1);

    // Held sideways: fit the table to the width. Cells re-wrap at each zoom, so the result can flip
    // between two values: when a value comes back, settle on the smaller one.
    const fit = () => {
        const t = table(), w = scroller();
        if (! fitting() || ! t || ! w) return;
        const width = t.getBoundingClientRect().width;
        if (! width) return;
        const next = clamp(round2(S.zoom * (w.clientWidth / width)), 0.4, 1);
        if (next === S.zoom) return;
        const h = S.hist;
        if (h.length >= 2 && next === h[h.length - 2]) { S.zoom = Math.min(next, h[h.length - 1]); S.hist = []; apply(); return; }
        h.push(next); if (h.length > 4) h.shift();
        S.zoom = next; apply();
        requestAnimationFrame(fit);
    };
    const ro = new ResizeObserver(() => requestAnimationFrame(fit));
    let watched = [];
    const watch = () => {
        const els = [scroller(), table()].filter(Boolean);
        if (els.length === watched.length && els.every((e, i) => e === watched[i])) return;
        ro.disconnect(); watched = els; els.forEach((e) => ro.observe(e));
    };

    const update = () => {
        const on = !! bar() && ((landscape && ! S.optedOut) || S.forced);
        if (on !== S.active) {
            S.active = on;
            document.documentElement.classList.toggle('myat-immersive', on);
            if (on) { S.manual = false; S.hist = []; S.zoom = defaultZoom(); }
        }
        apply();
        if (on) { watch(); fit(); requestAnimationFrame(fit); }
    };

    const enter = () => {
        S.forced = true; update();
        if (touch) {
            const el = document.documentElement;
            try {
                (el.requestFullscreen?.({ navigationUI: 'hide' }) ?? el.webkitRequestFullscreen?.())?.catch?.(() => {});
            } catch (e) { /* iPhone: not supported */ }
        }
    };
    const exit = () => {
        S.forced = false;
        if (landscape) S.optedOut = true;
        try { if (document.fullscreenElement) document.exitFullscreen?.(); else if (document.webkitFullscreenElement) document.webkitExitFullscreen?.(); } catch (e) {}
        update();
    };

    const onTurn = () => {
        const was = landscape; landscape = sideways();
        if (! landscape) S.optedOut = false;
        if (was !== landscape && S.active) { S.manual = false; S.hist = []; S.zoom = defaultZoom(); }
        update();
    };
    addEventListener('resize', onTurn);
    addEventListener('orientationchange', () => setTimeout(onTurn, 150));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && S.active && ! document.querySelector('.fi-modal-open, .fi-modal[open]')) exit(); });

    document.addEventListener('click', (e) => {
        const b = e.target.closest?.('[data-myat-imm]');
        if (! b) return;
        e.preventDefault();
        ({
            enter, exit,
            in: () => setZoom(S.zoom * STEP, true),
            out: () => setZoom(S.zoom / STEP, true),
            reset: () => { S.manual = false; S.hist = []; S.zoom = defaultZoom(); apply(); fit(); },
        })[b.dataset.myatImm]?.();
    });

    // Trackpad pinch / ctrl+wheel: one update per frame.
    let wheelFactor = 1, wheelQueued = false;
    document.addEventListener('wheel', (e) => {
        if (! S.active || ! e.ctrlKey || ! e.target.closest?.('.fi-ta-ctn')) return;
        e.preventDefault();
        wheelFactor *= Math.exp(-e.deltaY * 0.0015);
        if (wheelQueued) return;
        wheelQueued = true;
        requestAnimationFrame(() => { wheelQueued = false; setZoom(S.zoom * wheelFactor, true); wheelFactor = 1; });
    }, { passive: false });

    // Two-finger pinch on the table only.
    let pinch = null;
    const dist = (t) => Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
    document.addEventListener('touchstart', (e) => {
        if (! S.active || e.touches.length !== 2) return;
        const t = table(), w = scroller();
        if (! t || ! w || ! w.contains(e.target)) return;
        const r = t.getBoundingClientRect();
        const mx = (e.touches[0].clientX + e.touches[1].clientX) / 2, my = (e.touches[0].clientY + e.touches[1].clientY) / 2;
        pinch = { d: dist(e.touches), z: S.zoom, s: 1, mx, my, t, w, wr: w.getBoundingClientRect(), top: r.top + scrollY, sl: w.scrollLeft, sy: scrollY };
        t.style.transformOrigin = `${(mx - r.left)}px ${(my - r.top)}px`;
        t.style.willChange = 'transform';
    }, { passive: true });
    let moveQueued = false;
    document.addEventListener('touchmove', (e) => {
        if (! pinch || e.touches.length !== 2) return;
        e.preventDefault();
        pinch.s = clamp(pinch.z * dist(e.touches) / pinch.d, MIN, MAX) / pinch.z;
        if (moveQueued) return;
        moveQueued = true;
        requestAnimationFrame(() => { moveQueued = false; if (pinch) pinch.t.style.transform = `scale(${pinch.s})`; });
    }, { passive: false });
    const endPinch = () => {
        if (! pinch) return;
        const p = pinch; pinch = null;
        p.t.style.transform = ''; p.t.style.transformOrigin = ''; p.t.style.willChange = '';
        const z1 = clamp(p.z * p.s, MIN, MAX);
        // the point under the fingers, in unzoomed table units
        const x = (p.sl + p.mx - p.wr.left) / p.z, y = (p.sy + p.my - p.top) / p.z;
        setZoom(z1, true);
        requestAnimationFrame(() => requestAnimationFrame(() => {
            p.w.scrollLeft = x * z1 - (p.mx - p.wr.left);
            scrollTo({ top: p.top + y * z1 - p.my, behavior: 'instant' });
        }));
    };
    document.addEventListener('touchend', endPinch);
    document.addEventListener('touchcancel', endPinch);
    ['gesturestart', 'gesturechange'].forEach((ev) => document.addEventListener(ev, (e) => { if (S.active) e.preventDefault(); }, { passive: false }));

    // Livewire re-renders and page changes (wire:navigate): keep the label right, refit, or leave.
    const refresh = () => requestAnimationFrame(() => { if (! bar() && S.active) { S.forced = false; } update(); });
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morph.updated', refresh);
        window.Livewire.hook('commit', ({ succeed }) => succeed(refresh));
    });
    document.addEventListener('livewire:navigated', () => { S.forced = false; S.optedOut = false; refresh(); });
    document.addEventListener('DOMContentLoaded', update);
    update();
})();
</script>
