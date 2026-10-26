<?php

namespace App\Support;

use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The manual (Βοήθεια): Markdown articles in resources/help/<panel>/*.md, written in the repo.
 *
 * Each file starts with a small header:
 *   ---
 *   title: Transfers
 *   group: Καθημερινή δουλειά
 *   order: 10
 *   pages: ListTransfers, CreateTransfer, EditTransfer   (screens whose "?" opens this article)
 *   audience: drivers                                     (portal only: drivers, customers or all)
 *   ---
 * In the text, {business.name} and the other {business.*} keys come from config/business.php,
 * and [link](help:slug) points to another article.
 */
class Help
{
    public const PANELS = ['admin', 'portal'];

    /** @return Collection<string, array{slug: string, title: string, group: string, order: int, pages: list<string>, audience: string, body: string}> */
    public static function articles(string $panel): Collection
    {
        return once(fn (): Collection => self::load($panel))
            ->filter(fn (array $a): bool => self::visible($panel, $a));
    }

    public static function find(string $panel, string $slug): ?array
    {
        return self::articles($panel)->get($slug);
    }

    /** The article for a screen (its Livewire page class, or the page's resource), if there is one. */
    public static function forPage(string $panel, string $class): ?array
    {
        $names = [$class, class_basename($class)];
        if (is_subclass_of($class, ResourcePage::class)) {
            $names[] = class_basename($class::getResource());
        }

        return self::articles($panel)->first(fn (array $a): bool => array_intersect($names, $a['pages']) !== []);
    }

    /** @return array<string, list<array>> group => articles, in order */
    public static function grouped(string $panel): array
    {
        return self::articles($panel)->sortBy([['order', 'asc'], ['title', 'asc']])->groupBy('group')->map(fn ($list): array => $list->values()->all())->all();
    }

    public static function html(string $panel, array $article): HtmlString
    {
        $text = preg_replace_callback('/\{business\.([a-z_.]+)\}/', fn (array $m): string => e((string) config('business.'.$m[1], '')), $article['body']);
        $text = preg_replace_callback('/\]\(help:([a-z0-9-]+)\)/', fn (array $m): string => ']('.self::url($panel, $m[1]).')', (string) $text);

        return new HtmlString(Str::markdown((string) $text, ['html_input' => 'allow', 'allow_unsafe_links' => false]));
    }

    /** Plain text of an article, for the search box. */
    public static function text(array $article): string
    {
        return Str::of($article['body'])->replaceMatches('/[#*_>`|\[\]()-]+/u', ' ')->squish()->lower()->toString();
    }

    public static function url(string $panel, string $slug): string
    {
        $page = $panel === 'portal' ? \App\Filament\Portal\Pages\Help::class : \App\Filament\Pages\Help::class;

        return $page::getUrl(['topic' => $slug], panel: $panel);
    }

    private static function visible(string $panel, array $article): bool
    {
        if ($panel !== 'portal' || $article['audience'] === 'all') {
            return true;
        }

        $driver = (bool) auth()->user()?->group()?->isDriver();

        return match ($article['audience']) {
            'drivers' => $driver,
            'customers' => ! $driver,
            default => false,
        };
    }

    private static function load(string $panel): Collection
    {
        $dir = resource_path('help/'.$panel);
        $articles = [];

        foreach (glob($dir.'/*.md') ?: [] as $file) {
            $raw = (string) file_get_contents($file);
            $meta = [];
            if (preg_match('/\A---\R(.*?)\R---\R/su', $raw, $m)) {
                foreach (preg_split('/\R/u', $m[1]) as $line) {
                    if (str_contains($line, ':')) {
                        [$k, $v] = array_map('trim', explode(':', $line, 2));
                        $meta[$k] = $v;
                    }
                }
                $raw = substr($raw, strlen($m[0]));
            }
            $slug = basename($file, '.md');
            $articles[$slug] = [
                'slug' => $slug,
                'title' => $meta['title'] ?? Str::headline($slug),
                'group' => $meta['group'] ?? 'Γενικά',
                'order' => (int) ($meta['order'] ?? 100),
                'pages' => array_values(array_filter(array_map('trim', explode(',', $meta['pages'] ?? '')))),
                'audience' => $meta['audience'] ?? 'all',
                'body' => trim($raw),
            ];
        }

        return collect($articles);
    }
}
