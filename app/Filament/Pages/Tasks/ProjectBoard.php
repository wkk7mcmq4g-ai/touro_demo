<?php

namespace App\Filament\Pages\Tasks;

use App\Filament\Concerns\WorksOnTasks;
use App\Models\MyatProject;
use App\Models\MyatTask;
use App\Support\Tasks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * One project: its tasks as a Kanban board (drag between columns) or a list, with search and
 * filters; a task opens in a panel on the right (its link is shareable: ?task=<id>).
 */
class ProjectBoard extends Page
{
    use WorksOnTasks;

    protected string $view = 'filament.tasks.board';

    protected static ?string $slug = 'tasks/{project}';

    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = Width::Full;

    public int $project;

    /** Board (Kanban) or list. */
    #[Url(as: 'view')]
    public string $mode = 'board';

    #[Url(as: 'task')]
    public ?int $openTask = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $who = '';

    #[Url]
    public string $priority = '';

    /** List view: column to sort by (title, status, priority, due), "-" in front for descending. */
    #[Url]
    public string $sort = '';

    public static function canAccess(): bool
    {
        return auth()->check() && Tasks::tablesExist();
    }

    public function mount(int $project): void
    {
        $this->project = Tasks::project($project)->ID;
        if (! in_array($this->mode, ['board', 'list'], true)) {
            $this->mode = 'board';
        }
        if ($this->openTask && ! $this->task($this->openTask, false)) {
            $this->openTask = null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        $p = $this->record();

        return trim($p->Emoji.' '.$p->Name);
    }

    public function getBreadcrumbs(): array
    {
        return [Projects::getUrl() => 'Έργα', $this->record()->Name];
    }

    public function record(): MyatProject
    {
        return once(fn (): MyatProject => Tasks::project($this->project));
    }

    // ----- Board and list --------------------------------------------------------------------

    /** The project's tasks after search and filters, each with what the cards show. */
    public function tasks(): array
    {
        $query = MyatTask::query()->where('ProjectID', $this->project)
            ->select(['ID', 'Title', 'Status', 'Priority', 'DueDate', 'Sort', 'TransferID', 'CompletedAt'])
            ->selectSub(fn ($q) => $q->from('myat_task_comments')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'comments_count')
            ->selectSub(fn ($q) => $q->from('myat_task_files')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'files_count')
            ->selectSub(fn ($q) => $q->from('myat_task_subtasks')->selectRaw('COUNT(*)')->whereColumn('TaskID', 'myat_tasks.ID'), 'sub_total')
            ->selectSub(fn ($q) => $q->from('myat_task_subtasks')->selectRaw('COALESCE(SUM(Done), 0)')->whereColumn('TaskID', 'myat_tasks.ID'), 'sub_done')
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('Title', 'like', '%'.trim($this->search).'%')->orWhere('Description', 'like', '%'.trim($this->search).'%')))
            ->when(isset(Tasks::PRIORITIES[$this->priority]), fn ($q) => $q->where('Priority', $this->priority))
            ->when($this->who === 'none', fn ($q) => $q->whereNotExists(fn ($q) => $q->from('myat_task_assignees')->whereColumn('TaskID', 'myat_tasks.ID')))
            ->when($this->who !== '' && $this->who !== 'none', fn ($q) => $q->whereExists(fn ($q) => $q->from('myat_task_assignees')->whereColumn('TaskID', 'myat_tasks.ID')
                ->where('Username', $this->who === 'me' ? (string) auth()->user()->username : $this->who)))
            ->orderBy('Sort')->orderBy('ID')
            ->get();

        $assignees = Tasks::assignees($query->pluck('ID')->all());
        $people = Tasks::people();
        $drivers = Tasks::peopleByGroup()['Οδηγοί'];
        $today = Carbon::today();

        return $query->map(fn (MyatTask $t): array => [
            'id' => $t->ID, 'title' => $t->Title, 'status' => isset(Tasks::STATUSES[$t->Status]) ? $t->Status : 'todo',
            'priority' => isset(Tasks::PRIORITIES[$t->Priority]) ? $t->Priority : 'medium',
            'due' => $t->DueDate?->format('d/m'), 'dueFull' => $t->DueDate?->format('d/m/Y'), 'dueSort' => $t->DueDate?->toDateString() ?? '9999',
            'overdue' => $t->DueDate && $t->Status !== 'done' && $t->DueDate->lt($today),
            'today' => $t->DueDate && $t->DueDate->isSameDay($today),
            'people' => array_map(fn (string $u): array => ['user' => $u, 'name' => $people[$u] ?? $u, 'initials' => Tasks::initials($people[$u] ?? $u), 'driver' => isset($drivers[$u])], $assignees[$t->ID] ?? []),
            'subTotal' => (int) $t->sub_total, 'subDone' => (int) $t->sub_done,
            'comments' => (int) $t->comments_count, 'files' => (int) $t->files_count, 'transfer' => $t->TransferID,
        ])->all();
    }

    /** The list view's rows, in the chosen order (default: status, then due date). */
    public function rows(): array
    {
        $tasks = collect($this->tasks());
        $desc = str_starts_with($this->sort, '-');
        $key = ltrim($this->sort, '-');
        $statuses = array_flip(array_keys(Tasks::STATUSES));
        $priorities = array_flip(array_keys(Tasks::PRIORITIES));

        $sorted = match ($key) {
            'title' => $tasks->sortBy(fn (array $t): string => mb_strtolower($t['title']), SORT_NATURAL),
            'priority' => $tasks->sortBy(fn (array $t): int => $priorities[$t['priority']]),
            'due' => $tasks->sortBy('dueSort'),
            'status' => $tasks->sortBy(fn (array $t): int => $statuses[$t['status']]),
            default => $tasks->sortBy(fn (array $t): string => $statuses[$t['status']].$t['dueSort']),
        };

        return ($desc ? $sorted->reverse() : $sorted)->values()->all();
    }

    public function sortBy(string $key): void
    {
        $this->sort = $this->sort === $key ? "-{$key}" : $key;
    }

    public function filtered(): bool
    {
        return trim($this->search) !== '' || $this->who !== '' || $this->priority !== '';
    }

    public function clearFilters(): void
    {
        $this->search = $this->who = $this->priority = '';
    }

    /** Quick add at the bottom of a column. */
    public function createTask(string $status, string $title): void
    {
        $title = trim($title);
        if ($title === '' || ! isset(Tasks::STATUSES[$status])) {
            return;
        }

        MyatTask::query()->create([
            'ProjectID' => $this->project, 'Title' => mb_substr($title, 0, 250), 'Status' => $status, 'Priority' => 'medium',
            'Sort' => Tasks::nextSort($this->project, $status), 'CreatedBy' => (string) auth()->user()->username,
        ]);
    }

    /** A card dropped in a column: its new status, and the column's order as it is on screen. */
    public function moveTask(int $id, string $status, array $order): void
    {
        $task = $this->task($id);
        if (! isset(Tasks::STATUSES[$status])) {
            return;
        }

        DB::transaction(function () use ($task, $status, $order): void {
            if ($task->Status !== $status) {
                $task->Status = $status;
                $task->save();
            }
            $ids = MyatTask::query()->where('ProjectID', $this->project)->where('Status', $status)->pluck('ID')->all();
            foreach (array_values(array_intersect(array_map('intval', $order), $ids)) as $i => $taskId) {
                MyatTask::query()->whereKey($taskId)->update(['Sort' => $i + 1]);
            }
        });
    }

    // ----- Project -----------------------------------------------------------------------------

    public function toggleStar(): void
    {
        Tasks::toggleStar($this->project);
        $this->dispatch('refresh-sidebar');
    }

    public function starred(): bool
    {
        return in_array($this->project, Tasks::starred(), true);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Projects::projectAction(Action::make('edit'))
                    ->label('Ρυθμίσεις έργου')->icon(Heroicon::OutlinedCog6Tooth)
                    ->fillForm(fn (): array => $this->record()->only(['Name', 'Emoji', 'Color', 'Description']))
                    ->action(function (array $data): void {
                        $this->record()->update(Projects::clean($data));
                        $this->dispatch('refresh-sidebar');
                    }),
                Action::make('archive')
                    ->label(fn (): string => $this->record()->Archived ? 'Επαναφορά από το αρχείο' : 'Αρχειοθέτηση')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->action(function (): void {
                        $this->record()->update(['Archived' => $this->record()->Archived ? 0 : 1]);
                        $this->dispatch('refresh-sidebar');
                    }),
                Action::make('delete')
                    ->label('Διαγραφή έργου')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->modalHeading('Διαγραφή έργου')
                    ->modalDescription('Θα διαγραφούν οριστικά όλες οι εργασίες του, με τα σχόλια και τα αρχεία τους. Γράψτε το όνομα του έργου για επιβεβαίωση.')
                    ->schema([TextInput::make('confirm')->label('Όνομα έργου')->required()
                        ->rule(fn () => fn (string $attribute, $value, $fail) => trim((string) $value) === $this->record()->Name ? null : $fail('Το όνομα δεν ταιριάζει.'))])
                    ->modalSubmitActionLabel('Διαγραφή')
                    ->action(function (): void {
                        DB::transaction(function (): void {
                            MyatTask::query()->where('ProjectID', $this->project)->get()->each->delete();
                            $this->record()->delete();
                        });
                        $this->redirect(Projects::getUrl());
                    }),
            ])->label('Έργο')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray')
                ->visible(fn (): bool => Tasks::canManageProjects()),
        ];
    }

    /** The tasks this board may open: this project's. */
    protected function taskScope(): Builder
    {
        return MyatTask::query()->where('ProjectID', $this->project);
    }
}
