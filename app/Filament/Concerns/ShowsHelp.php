<?php

namespace App\Filament\Concerns;

use App\Support\Help;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * The manual page (Βοήθεια) for a panel: topics on the left with a search box, the article on the
 * right. ?topic=<slug> opens an article; the first one opens otherwise.
 */
trait ShowsHelp
{
    #[Url]
    public ?string $topic = null;

    public function mount(): void
    {
        if (! $this->topic || ! Help::find($this->helpPanel(), $this->topic)) {
            $this->topic = Help::articles($this->helpPanel())->sortBy('order')->keys()->first();
        }
    }

    abstract protected function helpPanel(): string;

    public function show(string $slug): void
    {
        if (Help::find($this->helpPanel(), $slug)) {
            $this->topic = $slug;
        }
    }

    /** @return array{groups: array, article: ?array, html: ?HtmlString, search: list<array>} */
    public function manual(): array
    {
        $panel = $this->helpPanel();
        $article = $this->topic ? Help::find($panel, $this->topic) : null;

        return [
            'groups' => Help::grouped($panel),
            'article' => $article,
            'html' => $article ? Help::html($panel, $article) : null,
            'search' => Help::articles($panel)->map(fn (array $a): array => ['slug' => $a['slug'], 'title' => $a['title'], 'text' => Help::text($a)])->values()->all(),
        ];
    }
}
