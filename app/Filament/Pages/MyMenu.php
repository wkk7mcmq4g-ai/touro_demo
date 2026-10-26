<?php

namespace App\Filament\Pages;

use App\Navigation\PersonalNavigationManager;
use App\Support\PersonalMenu;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * "Το μενού μου": each user arranges their own side menu. Drag items and groups into the order
 * they want, star favourites (shown in a group of their own at the top), make their own groups,
 * rename groups, hide items they never use, or go back to the standard menu. Saved per user
 * (App\Support\PersonalMenu); changes show in the side menu straight away.
 */
class MyMenu extends Page
{
    protected string $view = 'filament.pages.my-menu';

    protected static ?string $slug = 'my-menu';

    protected static ?string $title = 'Το μενού μου';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /** What the editor shows: the arrangement, each item with its label, icon and flags. */
    public function editor(): array
    {
        $defaults = $this->defaults();
        $config = PersonalMenu::config();
        $catalogue = PersonalMenu::catalogue($defaults);
        $hidden = array_flip($config['hidden'] ?? []);
        $favourites = array_values(array_filter($config['favourites'] ?? [], fn (string $k): bool => isset($catalogue[$k])));

        $item = fn (string $key): array => [
            'key' => $key,
            'label' => PersonalMenu::text($catalogue[$key]['item']->getLabel()),
            'icon' => $catalogue[$key]['item']->getIcon(),
            'home' => $catalogue[$key]['home'],
            'hidden' => isset($hidden[$key]),
            'favourite' => in_array($key, $favourites, true),
        ];

        return [
            'favourites' => array_map($item, $favourites),
            'groups' => array_map(fn (array $g): array => [
                'id' => $g['id'],
                'label' => $g['label'] !== '' ? $g['label'] : ($g['from'] ?? ''),
                'from' => $g['from'] ?? null,
                'own' => ! array_key_exists('from', $g),
                'top' => ($g['from'] ?? null) === '',
                'items' => array_map($item, $g['items']),
            ], PersonalMenu::layout($defaults, $config)),
            'customised' => $config !== [],
        ];
    }

    /** After a drag: the groups in screen order, each with its items in order. */
    public function saveOrder(array $order): void
    {
        $layout = collect($this->layout())->keyBy('id');
        $groups = [];
        foreach ($order as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($layout->has($id)) {
                $groups[] = ['items' => array_values(array_filter((array) ($entry['items'] ?? []), 'is_string'))] + $layout[$id];
                $layout->forget($id);
            }
        }
        // Anything the screen didn't send keeps its place at the end.
        $this->store(['groups' => [...$groups, ...$layout->values()->all()]]);
    }

    public function saveFavourites(array $keys): void
    {
        $this->store(['favourites' => array_values(array_filter($keys, 'is_string'))]);
    }

    public function toggleFavourite(string $key): void
    {
        $list = PersonalMenu::config()['favourites'] ?? [];
        $this->store(['favourites' => in_array($key, $list, true) ? array_values(array_diff($list, [$key])) : [...$list, $key]]);
    }

    public function toggleHidden(string $key): void
    {
        $list = PersonalMenu::config()['hidden'] ?? [];
        $this->store(['hidden' => in_array($key, $list, true) ? array_values(array_diff($list, [$key])) : [...$list, $key]]);
    }

    public function renameGroup(string $id, string $label): void
    {
        $this->store(['groups' => array_map(fn (array $g): array => $g['id'] === $id && ($g['from'] ?? null) !== ''
            ? ['label' => trim($label) !== '' ? trim($label) : ($g['from'] ?? $g['label'])] + $g : $g, $this->layout())]);
    }

    /** Only groups the user made can go; their items return to their usual groups. */
    public function deleteGroup(string $id): void
    {
        $this->store(['groups' => array_values(array_filter($this->layout(), fn (array $g): bool => $g['id'] !== $id || array_key_exists('from', $g)))]);
    }

    public function addGroupAction(): Action
    {
        return Action::make('addGroup')
            ->label('Νέα ομάδα')
            ->icon(Heroicon::OutlinedFolderPlus)
            ->modalWidth('sm')
            ->schema([TextInput::make('label')->label('Όνομα ομάδας')->required()->maxLength(40)->autofocus()])
            ->action(function (array $data): void {
                $this->store(['groups' => [...$this->layout(), ['id' => 'c-'.Str::lower(Str::random(8)), 'label' => trim($data['label']), 'items' => []]]]);
            });
    }

    public function resetAction(): Action
    {
        return Action::make('reset')
            ->label('Επαναφορά')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Επαναφορά στο βασικό μενού;')
            ->modalDescription('Αγαπημένα, σειρά, δικές σας ομάδες και κρυμμένα θα χαθούν.')
            ->modalSubmitActionLabel('Επαναφορά')
            ->visible(fn (): bool => PersonalMenu::config() !== [])
            ->action(function (): void {
                PersonalMenu::save([]);
                $this->dispatch('refresh-sidebar');
                Notification::make()->title('Το βασικό μενού επανήλθε')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->addGroupAction(), $this->resetAction()];
    }

    private function defaults(): array
    {
        $manager = app(NavigationManager::class);

        return $manager instanceof PersonalNavigationManager ? $manager->defaults() : $manager->get();
    }

    private function layout(): array
    {
        return PersonalMenu::layout($this->defaults(), PersonalMenu::config());
    }

    /** Saves a change over the current arrangement and redraws the side menu. */
    private function store(array $change): void
    {
        $config = PersonalMenu::config();
        $config['groups'] ??= $this->layout();
        PersonalMenu::save($change + $config);
        $this->dispatch('refresh-sidebar');
    }
}
