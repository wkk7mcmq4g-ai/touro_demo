<?php

namespace App\Filament\Pages\Tasks;

use App\Models\MyatProject;
use App\Support\Tasks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Εργασίες · Έργα: the projects as cards (starred first) with their progress; archived ones in a
 * fold at the bottom. Starred projects also appear in the side menu under "Έργα".
 */
class Projects extends Page
{
    protected string $view = 'filament.tasks.projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Εργασίες';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Έργα';

    protected static ?string $title = 'Έργα';

    protected static ?string $slug = 'tasks';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return auth()->check() && Tasks::tablesExist();
    }

    /** "Έργα", with the user's starred projects under it. */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();
        $starred = Tasks::starred();
        if ($starred === []) {
            return $items;
        }

        $projects = MyatProject::query()->whereIn('ID', $starred)->where('Archived', 0)->orderBy('Name')->get(['ID', 'Name', 'Emoji']);
        foreach ($projects as $p) {
            $url = ProjectBoard::getUrl(['project' => $p->ID]);
            $items[] = NavigationItem::make(trim($p->Emoji.' '.$p->Name))
                ->key('project-'.$p->ID)
                ->group(static::getNavigationGroup())
                ->parentItem(static::class)
                ->url($url)
                ->isActiveWhen(fn (): bool => \Filament\Support\original_request()->routeIs(ProjectBoard::getRouteName()) && (int) \Filament\Support\original_request()->route('project') === (int) $p->ID);
        }

        return $items;
    }

    /** @return array{active: list<array>, archived: list<array>} */
    public function projects(): array
    {
        $progress = Tasks::progress();
        $starred = Tasks::starred();
        $open = DB::table('myat_tasks')->where('Status', '<>', 'done')->whereNotNull('DueDate')->where('DueDate', '<', today())
            ->selectRaw('ProjectID, COUNT(*) n')->groupBy('ProjectID')->pluck('n', 'ProjectID')->all();

        $all = MyatProject::query()->orderBy('Sort')->orderBy('Name')->get()->map(fn (MyatProject $p): array => [
            'id' => $p->ID, 'name' => $p->Name, 'emoji' => $p->Emoji, 'color' => $p->Color ?: '#0096ff', 'description' => (string) $p->Description,
            'archived' => (bool) $p->Archived, 'starred' => in_array($p->ID, $starred, true),
            'total' => $progress[$p->ID]['total'] ?? 0, 'done' => $progress[$p->ID]['done'] ?? 0, 'overdue' => (int) ($open[$p->ID] ?? 0),
            'url' => ProjectBoard::getUrl(['project' => $p->ID]),
        ]);

        return [
            'active' => $all->where('archived', false)->sortByDesc('starred')->values()->all(),
            'archived' => $all->where('archived', true)->values()->all(),
        ];
    }

    public function toggleStar(int $id): void
    {
        Tasks::toggleStar($id);
        $this->dispatch('refresh-sidebar');
    }

    public function setArchived(int $id, bool $archived): void
    {
        abort_unless(Tasks::canManageProjects(), 403);
        MyatProject::query()->whereKey($id)->update(['Archived' => $archived ? 1 : 0]);
        $this->dispatch('refresh-sidebar');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('myTasks')->label('Οι εργασίες μου')->icon(Heroicon::OutlinedUserCircle)->color('gray')->url(MyTasks::getUrl()),
            self::projectAction(Action::make('create'))
                ->label('Νέο έργο')->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => Tasks::canManageProjects())
                ->action(function (array $data): void {
                    $project = MyatProject::query()->create(self::clean($data) + [
                        'CreatedBy' => (string) auth()->user()->username, 'Sort' => (int) MyatProject::query()->max('Sort') + 1,
                    ]);
                    $this->redirect(ProjectBoard::getUrl(['project' => $project->ID]));
                }),
        ];
    }

    /** The project form (name, emoji, colour, description), for new and existing projects. */
    public static function projectAction(Action $action): Action
    {
        return $action->modalWidth(Width::Large)->schema([
            TextInput::make('Name')->label('Όνομα')->required()->maxLength(150)->autofocus(),
            TextInput::make('Emoji')->label('Emoji')->maxLength(16)->placeholder('🚐')->helperText('Ένα emoji για το έργο (Win + . ή Ctrl + Cmd + Space).'),
            ColorPicker::make('Color')->label('Χρώμα')->default(Tasks::COLORS[0])->regex('/^#[0-9a-fA-F]{6}$/'),
            Textarea::make('Description')->label('Περιγραφή')->rows(3)->maxLength(2000),
        ]);
    }

    public static function clean(array $data): array
    {
        return [
            'Name' => trim((string) $data['Name']), 'Emoji' => trim((string) ($data['Emoji'] ?? '')),
            'Color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($data['Color'] ?? '')) ? $data['Color'] : Tasks::COLORS[0],
            'Description' => (string) ($data['Description'] ?? ''),
        ];
    }
}
