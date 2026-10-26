<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\WorksOnTasks;
use App\Models\MyatTask;
use App\Support\Tasks;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Drivers: the tasks the office gave them (Εργασίες), as cards. A task opens in the same panel the
 * office uses, limited: the driver moves it along (Σε εξέλιξη, Ολοκληρώθηκε), ticks subtasks, writes
 * comments and adds photos or files; title, dates, people and the description stay the office's.
 */
class MyTasks extends Page
{
    use WorksOnTasks;

    protected string $view = 'filament.portal.my-tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Εργασίες';

    protected static ?string $title = 'Οι εργασίες μου';

    protected static ?string $slug = 'tasks';

    #[Url(as: 'task')]
    public ?int $openTask = null;

    public static function canAccess(): bool
    {
        return Tasks::isDriver() && Tasks::tablesExist();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = (new static)->taskScope()->where('Status', '<>', 'done')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
        if ($this->openTask && ! $this->taskScope()->whereKey($this->openTask)->exists()) {
            $this->openTask = null;
        }
    }

    /** Only tasks given to this driver, in projects that aren't archived. */
    protected function taskScope(): Builder
    {
        return MyatTask::query()
            ->whereExists(fn ($q) => $q->from('myat_task_assignees')->whereColumn('TaskID', 'myat_tasks.ID')->where('Username', (string) auth()->user()?->username))
            ->whereExists(fn ($q) => $q->from('myat_projects')->whereColumn('myat_projects.ID', 'myat_tasks.ProjectID')->where('Archived', 0));
    }

    protected function limited(): bool
    {
        return true;
    }

    /** @return array{open: list<array>, done: list<array>} open by due date; the last 10 finished */
    public function cards(): array
    {
        $today = Carbon::today();
        $query = fn () => $this->taskScope()->with('project:ID,Name,Emoji,Color')
            ->select('myat_tasks.*')
            ->selectSub(fn ($q) => $q->from('myat_task_subtasks')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'sub_total')
            ->selectSub(fn ($q) => $q->from('myat_task_subtasks')->selectRaw('COALESCE(SUM(Done), 0)')->whereColumn('TaskID', 'myat_tasks.ID'), 'sub_done')
            ->selectSub(fn ($q) => $q->from('myat_task_comments')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'comments_count')
            ->selectSub(fn ($q) => $q->from('myat_task_files')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'files_count');

        $card = fn (MyatTask $t): array => [
            'id' => $t->ID, 'title' => $t->Title, 'status' => isset(Tasks::STATUSES[$t->Status]) ? $t->Status : 'todo',
            'priority' => isset(Tasks::PRIORITIES[$t->Priority]) ? $t->Priority : 'medium',
            'due' => $t->DueDate, 'overdue' => $t->DueDate && $t->Status !== 'done' && $t->DueDate->lt($today),
            'today' => (bool) $t->DueDate?->isSameDay($today), 'tomorrow' => (bool) $t->DueDate?->isSameDay($today->copy()->addDay()),
            'project' => trim(($t->project?->Emoji ?? '').' '.($t->project?->Name ?? '')), 'color' => $t->project?->Color ?: '#0096ff',
            'transfer' => $t->TransferID, 'description' => trim(strip_tags((string) $t->Description)),
            'subTotal' => (int) $t->sub_total, 'subDone' => (int) $t->sub_done, 'comments' => (int) $t->comments_count, 'files' => (int) $t->files_count,
            'completedAt' => $t->CompletedAt?->format('d/m H:i'),
        ];

        return [
            'open' => $query()->where('Status', '<>', 'done')->orderByRaw('DueDate IS NULL, DueDate')->orderBy('ID')->get()->map($card)->all(),
            'done' => $query()->where('Status', 'done')->orderByDesc('CompletedAt')->limit(10)->get()->map($card)->all(),
        ];
    }

    /** The big button on a card: Να γίνει → Σε εξέλιξη, then → Ολοκληρώθηκε. */
    public function advance(int $id): void
    {
        $task = $this->task($id);
        $next = match ($task->Status) {
            'done' => null,
            'todo' => 'doing',
            default => 'done',
        };
        if ($next) {
            $task->Status = $next;
            $task->Sort = Tasks::nextSort($task->ProjectID, $next);
            $task->save();
            Notification::make()->title($next === 'done' ? 'Μπράβο, ολοκληρώθηκε!' : 'Η εργασία ξεκίνησε')->success()->send();
        }
    }
}
