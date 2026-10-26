<?php

namespace App\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Each user's own side menu: favourites, the order of groups and items, groups of their own,
 * renamed groups and hidden items. Saved like the table layouts, in MyAthensTransfersAPP_settings
 * (TYPE 100, TABLENAME = KEY, SEARCH = JSON), and kept in the session while signed in.
 *
 * Only the arrangement is stored. The menu itself is still Filament's: a user only ever sees the
 * items their permissions allow, and a screen added later shows up in its usual group.
 *
 * Stored shape: {
 *   groups: [{id, label, from?, items: [key, ...]}, ...]   // from = the built-in group it started as
 *   favourites: [key, ...], hidden: [key, ...]
 * }
 * An item's key is its URL path (e.g. /admin/transfers).
 */
class PersonalMenu
{
    public const KEY = 'myat.menu';

    public const FAVOURITES = 'Αγαπημένα';

    private const SESSION = 'myat.menu';

    public static function config(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if (! session()->has(self::SESSION)) {
            $json = DB::table('MyAthensTransfersAPP_settings')
                ->where('TYPE', TableLayouts::TYPE)->where('USERNAME', $user->username)->where('TABLENAME', self::KEY)
                ->value('SEARCH');
            session()->put(self::SESSION, self::normalise(is_string($json) ? (array) json_decode($json, true) : []));
        }

        return (array) session(self::SESSION);
    }

    public static function save(array $config): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $config = self::normalise($config);
        session()->put(self::SESSION, $config);

        $where = ['TYPE' => TableLayouts::TYPE, 'USERNAME' => $user->username, 'TABLENAME' => self::KEY];
        $config === []
            ? DB::table('MyAthensTransfersAPP_settings')->where($where)->delete()
            : DB::table('MyAthensTransfersAPP_settings')->updateOrInsert($where, ['SEARCH' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'NAME' => 'side menu']);
    }

    /** Keeps only well-formed parts, so a damaged row can never break the menu. */
    public static function normalise(array $config): array
    {
        $keys = fn ($list): array => array_values(array_unique(array_filter((array) $list, fn ($k): bool => is_string($k) && $k !== '' && strlen($k) <= 200)));

        $groups = [];
        foreach ((array) ($config['groups'] ?? []) as $group) {
            if (! is_array($group)) {
                continue;
            }
            $id = is_string($group['id'] ?? null) && preg_match('/^[A-Za-z0-9_:.-]{1,40}$/', $group['id']) ? $group['id'] : 'c-'.Str::lower(Str::random(8));
            $groups[] = array_filter([
                'id' => $id,
                'label' => Str::limit(trim((string) ($group['label'] ?? '')), 40, ''),
                'from' => is_string($group['from'] ?? null) ? $group['from'] : null,
                'items' => $keys($group['items'] ?? []),
            ], fn ($v): bool => $v !== null);
        }

        return array_filter([
            'groups' => $groups,
            'favourites' => $keys($config['favourites'] ?? []),
            'hidden' => $keys($config['hidden'] ?? []),
        ], fn (array $v): bool => $v !== []);
    }

    public static function key(NavigationItem $item): string
    {
        $url = (string) $item->getUrl();
        $path = parse_url($url, PHP_URL_PATH);

        return $path ?: 'label:'.self::text($item->getLabel());
    }

    public static function text(mixed $label): string
    {
        return $label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label;
    }

    /**
     * Every item the user can see, by key: [item, home group label, group object].
     *
     * @param  array<NavigationGroup>  $defaults
     * @return array<string, array{item: NavigationItem, home: string}>
     */
    public static function catalogue(array $defaults): array
    {
        $items = [];
        foreach ($defaults as $group) {
            foreach ($group->getItems() as $item) {
                $items[self::key($item)] ??= ['item' => $item, 'home' => self::text($group->getLabel())];
            }
        }

        return $items;
    }

    /**
     * The full arrangement: every visible item placed in exactly one group, hidden ones included
     * (the editor shows them greyed). Items the user's list doesn't mention yet go to their usual group.
     *
     * @param  array<NavigationGroup>  $defaults
     * @return list<array{id: string, label: string, from?: string, items: list<string>}>
     */
    public static function layout(array $defaults, array $config): array
    {
        $catalogue = self::catalogue($defaults);
        $placed = [];
        $layout = [];

        foreach ((array) ($config['groups'] ?? []) as $group) {
            $items = array_values(array_filter($group['items'] ?? [], function (string $key) use ($catalogue, &$placed): bool {
                if (! isset($catalogue[$key]) || isset($placed[$key])) {
                    return false;
                }

                return $placed[$key] = true;
            }));
            $layout[] = ['items' => $items] + $group;
        }

        foreach ($catalogue as $key => $entry) {
            if (isset($placed[$key])) {
                continue;
            }
            $home = $entry['home'];
            $at = null;
            foreach ($layout as $i => $group) {
                if (($group['from'] ?? $group['label']) === $home) {
                    $at = $i;
                    break;
                }
            }
            if ($at === null) {
                $group = ['id' => 'd-'.substr(md5($home), 0, 8), 'label' => $home, 'from' => $home, 'items' => []];
                // The unnamed group (Dashboard) stays on top.
                $home === '' ? array_unshift($layout, $group) : $layout[] = $group;
                $at = $home === '' ? 0 : array_key_last($layout);
            }
            $layout[$at]['items'][] = $key;
        }

        // The unnamed group (Dashboard) always stays on top, whatever order was sent.
        usort($layout, fn (array $a, array $b): int => (int) (($b['from'] ?? null) === '') <=> (int) (($a['from'] ?? null) === ''));

        return $layout;
    }

    /**
     * The menu Filament draws: unnamed group first, then favourites, then the user's groups.
     *
     * @param  array<NavigationGroup>  $defaults
     * @return array<NavigationGroup>
     */
    public static function apply(array $defaults, array $config): array
    {
        if ($config === []) {
            return $defaults;
        }

        $catalogue = self::catalogue($defaults);
        $hidden = array_flip($config['hidden'] ?? []);
        $byLabel = [];
        foreach ($defaults as $group) {
            $byLabel[self::text($group->getLabel())] ??= $group;
        }

        $items = fn (array $keys): array => array_values(array_map(
            fn (string $key): NavigationItem => clone $catalogue[$key]['item'],
            array_filter($keys, fn (string $key): bool => isset($catalogue[$key]) && ! isset($hidden[$key])),
        ));

        $menu = [];
        foreach (self::layout($defaults, $config) as $group) {
            $list = $items($group['items']);
            if ($list === []) {
                continue;
            }
            $from = $group['from'] ?? null;
            $base = $from !== null && isset($byLabel[$from]) ? clone $byLabel[$from] : NavigationGroup::make();
            $label = $group['label'] !== '' ? $group['label'] : ($from === '' ? null : $from);
            $menu[] = $base->label($label)->items($list);
        }

        $favourites = array_values(array_map(
            fn (string $key): NavigationItem => clone $catalogue[$key]['item'],
            array_filter($config['favourites'] ?? [], fn (string $key): bool => isset($catalogue[$key])),
        ));
        if ($favourites !== []) {
            $top = isset($menu[0]) && blank($menu[0]->getLabel()) ? 1 : 0;
            array_splice($menu, $top, 0, [NavigationGroup::make(self::FAVOURITES)->items($favourites)]);
        }

        return $menu;
    }
}
