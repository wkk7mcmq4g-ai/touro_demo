{{--
    Βοήθεια (App\Filament\Pages\Help, App\Filament\Portal\Pages\Help): topics with a search box on
    the left, the article on the right. On phones the topics fold above the article.
--}}
@php($m = $this->manual())
<x-filament-panels::page>
    <div class="myat-help"
        x-data="{
            q: '',
            items: @js($m['search']),
            norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase() },
            get hits() {
                const q = this.norm(this.q).trim()
                if (q.length < 2) return null
                return this.items.filter((i) => this.norm(i.title).includes(q) || this.norm(i.text).includes(q)).map((i) => i.slug)
            },
            shown(slug) { return this.hits === null || this.hits.includes(slug) },
        }">
        <nav class="myat-help-nav" aria-label="Θέματα">
            <label class="myat-tk-search myat-help-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                <input type="search" x-model="q" placeholder="Αναζήτηση στη βοήθεια…">
            </label>
            <details class="myat-help-toc" open x-init="if (window.matchMedia('(max-width: 1023px)').matches) $el.open = false">
                <summary>Θέματα</summary>
                @foreach ($m['groups'] as $group => $articles)
                    <div class="myat-help-group" x-show="{{ json_encode(array_column($articles, 'slug')) }}.some((s) => shown(s))">
                        <p class="myat-help-group-h">{{ $group }}</p>
                        <ul>
                            @foreach ($articles as $a)
                                <li x-show="shown(@js($a['slug']))">
                                    <button type="button" wire:click="show(@js($a['slug']))" @class(['is-active' => $topic === $a['slug']])>{{ $a['title'] }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <p class="myat-help-none" x-show="hits !== null && hits.length === 0" x-cloak>Δεν βρέθηκε τίποτα για «<span x-text="q"></span>».</p>
            </details>
        </nav>

        <article class="myat-help-article" wire:key="article-{{ $topic }}">
            @if ($m['article'])
                <p class="myat-help-crumb">{{ $m['article']['group'] }}</p>
                <h2 class="myat-help-title">{{ $m['article']['title'] }}</h2>
                <div class="myat-help-md">{{ $m['html'] }}</div>
            @else
                <p class="myat-help-none">Δεν υπάρχουν ακόμα άρθρα βοήθειας.</p>
            @endif
        </article>
    </div>
</x-filament-panels::page>
