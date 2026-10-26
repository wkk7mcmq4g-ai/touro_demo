<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\WorksOnTasks;
use App\Models\MyatTask;
use App\Support\Tasks;
use BackedEnum;
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
        $card = fn (MyatTask $t): array => [
            'id' => $t->ID, 'title' => $t->Title, 'status' => isset(Tasks::STATUSES[$t->Status]) ? $t->Status : 'todo',
            'priority' => isset(Tasks::PRIORITIES[$t->Priority]) ? $t->Priority : 'medium',
            'due' => $t->DueDate?->format('d/m'), 'overdue' => $t->DueDate && $t->Status !== 'done' && $t->DueDate->lt($today),
            'today' => (bool) $t->DueDate?->isSameDay($today), 'project' => trim(($t->project?->Emoji ?? '').' '.($t->project?->Name ?? '')),
            'color' => $t->project?->Color ?: '#0096ff', 'transfer' => $t->TransferID,
        ];

        return [
            'open' => $this->taskScope()->with('project:ID,Name,Emoji,Color')->where('Status', '<>', 'done')
                ->orderByRaw('DueDate IS NULL, DueDate')->orderBy('ID')->get()->map($card)->all(),
            'done' => $this->taskScope()->with('project:ID,Name,Emoji,Color')->where('Status', 'done')
                ->orderByDesc('CompletedAt')->limit(10)->get()->map($card)->all(),
        ];
    }
}
