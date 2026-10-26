{{-- The help side panel: the "?" buttons send the article's address here; it is fetched and shown. --}}
<div class="myat-help-drawer" x-data="{ open: false, loading: false, title: '', html: '', link: '',
        async show(url) {
            this.open = true; this.loading = true
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } })
                const d = await r.json()
                this.title = d.title; this.html = d.html; this.link = d.url
            } catch (e) { this.title = 'Βοήθεια'; this.html = '<p>Δεν ήταν δυνατή η φόρτωση.</p>' }
            this.loading = false
            this.$nextTick(() => this.$refs.body.scrollTop = 0)
        } }"
    x-on:myat-help.window="show($event.detail.url)" x-on:keydown.escape.window="open = false">
    <template x-if="open">
        <div class="myat-tk-panel-wrap">
            <div class="myat-tk-backdrop" x-on:click="open = false"></div>
            <aside class="myat-tk-panel myat-help-panel" role="dialog" aria-label="Βοήθεια">
                <header class="myat-tk-panel-h">
                    <x-filament::icon icon="heroicon-m-question-mark-circle" class="myat-help-panel-icon" />
                    <span class="myat-help-panel-title" x-text="title"></span>
                    <a :href="link" class="myat-tk-iconbtn" title="Άνοιγμα στο εγχειρίδιο"><x-filament::icon icon="heroicon-m-book-open" /></a>
                    <button type="button" class="myat-tk-iconbtn" x-on:click="open = false" title="Κλείσιμο (Esc)"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                </header>
                <div class="myat-tk-panel-body" x-ref="body">
                    <p x-show="loading" class="myat-help-none">Φόρτωση…</p>
                    <div x-show="! loading" class="myat-help-md" x-html="html"></div>
                </div>
            </aside>
        </div>
    </template>
</div>
