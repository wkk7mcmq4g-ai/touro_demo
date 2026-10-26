<?php

namespace App\Filament\Pages\Tasks;

use App\Models\MyatTask;
use App\Support\Tasks;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Οι εργασίες μου: every open task given to the signed-in user, across all projects, grouped by
 * priority, overdue ones in red. A click opens the task on its project's board.
 */
class MyTasks extends Page
{
    protected string $view = 'filament.tasks.my-tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Εργασίες';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Οι εργασίες μου';

    protected static ?string $title = 'Οι εργασίες μου';

    protected static ?string $slug = 'my-tasks';

    public static function canAccess(): bool
    {
        return auth()->check() && Tasks::tablesExist();
    }

    /** Open tasks due today or earlier. */
    public static function getNavigationBadge(): ?string
    {
        $n = self::query()->whereNotNull('t.DueDate')->where('t.DueDate', '<=', Carbon::today())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Εκπρόθεσμες ή για σήμερα';
    }

    private static function query()
    {
        return MyatTask::query()->from('myat_tasks as t')
            ->join('myat_projects as p', 'p.ID', '=', 't.ProjectID')
            ->join('myat_task_assignees as a', 'a.TaskID', '=', 't.ID')
            ->where('a.Username', (string) auth()->user()?->username)
            ->where('t.Status', '<>', 'done')
            ->where('p.Archived', 0);
    }

    /** @return array<string, list<array>> priority => tasks (due date first, no date last) */
    public function groups(): array
    {
        $today = Carbon::today();
        $rows = self::query()
            ->orderByRaw('t.DueDate IS NULL, t.DueDate')->orderBy('t.ID')
            ->get(['t.ID', 't.Title', 't.Status', 't.Priority', 't.DueDate', 't.ProjectID', 'p.Name as ProjectName', 'p.Emoji', 'p.Color']);

        $groups = array_fill_keys(array_keys(Tasks::PRIORITIES), []);
        foreach ($rows as $t) {
            $due = $t->DueDate ? Carbon::parse($t->DueDate) : null;
            $groups[isset($groups[$t->Priority]) ? $t->Priority : 'medium'][] = [
                'id' => $t->ID, 'title' => $t->Title, 'status' => isset(Tasks::STATUSES[$t->Status]) ? $t->Status : 'todo',
                'due' => $due?->format('d/m/Y'), 'overdue' => $due?->lt($today), 'today' => $due?->isSameDay($today),
                'project' => trim($t->Emoji.' '.$t->ProjectName), 'color' => $t->Color ?: '#0096ff',
                'url' => ProjectBoard::getUrl(['project' => $t->ProjectID, 'task' => $t->ID]),
            ];
        }

        return array_filter($groups);
    }
}
