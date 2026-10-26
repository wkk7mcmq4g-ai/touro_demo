<?php

namespace App\Filament\Pages\Tasks;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\MyatProject;
use App\Models\MyatTask;
use App\Models\MyatTaskComment;
use App\Models\MyatTaskFile;
use App\Models\MyatTaskSubtask;
use App\Support\Tasks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * One project: its tasks as a Kanban board (drag between columns) or a list, with search and
 * filters; a task opens in a panel on the right (its link is shareable: ?task=<id>).
 */
class ProjectBoard extends Page
{
    use WithFileUploads;

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

    /** Files dropped on the open task. */
    public array $uploads = [];

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
        $today = Carbon::today();

        return $query->map(fn (MyatTask $t): array => [
            'id' => $t->ID, 'title' => $t->Title, 'status' => isset(Tasks::STATUSES[$t->Status]) ? $t->Status : 'todo',
            'priority' => isset(Tasks::PRIORITIES[$t->Priority]) ? $t->Priority : 'medium',
            'due' => $t->DueDate?->format('d/m'), 'dueFull' => $t->DueDate?->format('d/m/Y'), 'dueSort' => $t->DueDate?->toDateString() ?? '9999',
            'overdue' => $t->DueDate && $t->Status !== 'done' && $t->DueDate->lt($today),
            'today' => $t->DueDate && $t->DueDate->isSameDay($today),
            'people' => array_map(fn (string $u): array => ['user' => $u, 'name' => $people[$u] ?? $u, 'initials' => Tasks::initials($people[$u] ?? $u)], $assignees[$t->ID] ?? []),
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

    // ----- The task panel ----------------------------------------------------------------------

    public function open(int $id): void
    {
        $this->openTask = $this->task($id)->ID;
        $this->uploads = [];
    }

    public function closePanel(): void
    {
        $this->openTask = null;
        $this->uploads = [];
    }

    /** Everything the open task's panel shows, or null. */
    public function panel(): ?array
    {
        $task = $this->openTask ? $this->task($this->openTask, false) : null;
        if (! $task) {
            return null;
        }

        $people = Tasks::people();
        $me = (string) auth()->user()->username;
        $admin = Tasks::canManageProjects();
        $subtasks = $task->subtasks()->get();

        return [
            'id' => $task->ID, 'title' => $task->Title, 'description' => (string) $task->Description,
            'html' => Tasks::markdown($task->Description), 'status' => $task->Status, 'priority' => $task->Priority,
            'due' => $task->DueDate?->toDateString(), 'transfer' => $task->TransferID,
            'transferUrl' => $task->TransferID ? TransferResource::getUrl('edit', ['record' => $task->TransferID]) : null,
            'assignees' => $task->assigneeUsernames(),
            'subtasks' => $subtasks->map(fn (MyatTaskSubtask $s): array => ['id' => $s->ID, 'title' => $s->Title, 'done' => (bool) $s->Done])->all(),
            'subDone' => $subtasks->where('Done', 1)->count(),
            'comments' => $task->comments()->get()->map(fn (MyatTaskComment $c): array => [
                'id' => $c->ID, 'user' => $c->Username, 'name' => $people[$c->Username] ?? $c->Username, 'initials' => Tasks::initials($people[$c->Username] ?? $c->Username),
                'body' => $c->Body, 'html' => Tasks::comment($c->Body), 'when' => $c->CreatedAt?->format('d/m/Y H:i'), 'edited' => $c->EditedAt !== null,
                'mine' => $c->Username === $me, 'canDelete' => $c->Username === $me || $admin,
            ])->all(),
            'files' => $task->files()->get()->map(fn (MyatTaskFile $f): array => [
                'id' => $f->ID, 'name' => $f->Flnm, 'size' => $f->Size, 'url' => route('task-files.download', $f->ID),
                'image' => str_starts_with((string) $f->Mime, 'image/'), 'by' => $people[$f->Username] ?? $f->Username,
            ])->all(),
            'createdBy' => $people[$task->CreatedBy] ?? $task->CreatedBy, 'createdAt' => $task->CreatedAt?->format('d/m/Y H:i'),
            'completedAt' => $task->CompletedAt?->format('d/m/Y H:i'),
            'canDelete' => $admin || $task->CreatedBy === $me,
        ];
    }

    /** The description editor's preview: the same Markdown rendering as the saved text. */
    #[Renderless]
    public function previewMarkdown(string $text): string
    {
        return Tasks::markdown(mb_substr($text, 0, 20000))->toHtml();
    }

    /** One field of the open task, saved as soon as it changes. */
    public function saveField(string $field, mixed $value): void
    {
        $task = $this->task((int) $this->openTask);
        $value = is_string($value) ? trim($value) : $value;

        match ($field) {
            'Title' => $value !== '' ? $task->Title = mb_substr((string) $value, 0, 250) : null,
            'Description' => $task->Description = mb_substr((string) $value, 0, 20000),
            'Status' => isset(Tasks::STATUSES[$value]) && $value !== $task->Status
                ? [$task->Status = $value, $task->Sort = Tasks::nextSort($this->project, $value)] : null,
            'Priority' => isset(Tasks::PRIORITIES[$value]) ? $task->Priority = $value : null,
            'DueDate' => $task->DueDate = $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $value : null,
            'TransferID' => $task->TransferID = $this->transferId($value),
            default => abort(422),
        };
        $task->save();
    }

    private function transferId(mixed $value): ?int
    {
        $id = (int) preg_replace('/\D/', '', (string) $value);
        if ($id === 0) {
            return null;
        }
        if (! DB::table('transfers')->where('TransferID', $id)->exists()) {
            throw ValidationException::withMessages(['transfer' => "Δεν υπάρχει transfer με αριθμό {$id}."]);
        }

        return $id;
    }

    public function toggleAssignee(string $username): void
    {
        $task = $this->task((int) $this->openTask);
        abort_unless(isset(Tasks::people()[$username]), 422);

        $row = ['TaskID' => $task->ID, 'Username' => $username];
        DB::table('myat_task_assignees')->where($row)->exists()
            ? DB::table('myat_task_assignees')->where($row)->delete()
            : DB::table('myat_task_assignees')->insert($row);
    }

    public function addSubtask(string $title): void
    {
        $task = $this->task((int) $this->openTask);
        if (($title = trim($title)) !== '') {
            MyatTaskSubtask::query()->create(['TaskID' => $task->ID, 'Title' => mb_substr($title, 0, 250), 'Sort' => (int) $task->subtasks()->max('Sort') + 1]);
        }
    }

    public function toggleSubtask(int $id): void
    {
        $sub = $this->subtask($id);
        $sub->update(['Done' => $sub->Done ? 0 : 1]);
    }

    public function renameSubtask(int $id, string $title): void
    {
        if (($title = trim($title)) !== '') {
            $this->subtask($id)->update(['Title' => mb_substr($title, 0, 250)]);
        }
    }

    public function deleteSubtask(int $id): void
    {
        $this->subtask($id)->delete();
    }

    public function addComment(string $body): void
    {
        $task = $this->task((int) $this->openTask);
        if (($body = trim($body)) !== '') {
            MyatTaskComment::query()->create(['TaskID' => $task->ID, 'Username' => (string) auth()->user()->username, 'Body' => mb_substr($body, 0, 5000), 'CreatedAt' => now()]);
        }
    }

    public function updateComment(int $id, string $body): void
    {
        $comment = $this->comment($id);
        abort_unless($comment->Username === auth()->user()->username, 403);
        if (($body = trim($body)) !== '') {
            $comment->update(['Body' => mb_substr($body, 0, 5000), 'EditedAt' => now()]);
        }
    }

    public function deleteComment(int $id): void
    {
        $comment = $this->comment($id);
        abort_unless($comment->Username === auth()->user()->username || Tasks::canManageProjects(), 403);
        $comment->delete();
    }

    /** Files chosen or dropped on the panel go straight into the database. */
    public function updatedUploads(): void
    {
        $task = $this->task((int) $this->openTask);
        $this->validate(['uploads.*' => ['file', 'max:'.Tasks::MAX_FILE_KB]], [], ['uploads.*' => 'αρχείο']);

        foreach ($this->uploads as $file) {
            MyatTaskFile::query()->create([
                'TaskID' => $task->ID, 'Flnm' => mb_substr($file->getClientOriginalName(), 0, 250), 'Mime' => (string) $file->getMimeType(),
                'Size' => (int) $file->getSize(), 'Attachment' => file_get_contents($file->getRealPath()),
                'Username' => (string) auth()->user()->username, 'CreatedAt' => now(),
            ]);
        }
        $this->uploads = [];
    }

    public function deleteFile(int $id): void
    {
        $task = $this->task((int) $this->openTask);
        MyatTaskFile::query()->where('TaskID', $task->ID)->whereKey($id)->delete();
    }

    public function deleteTask(): void
    {
        $task = $this->task((int) $this->openTask);
        abort_unless(Tasks::canManageProjects() || $task->CreatedBy === auth()->user()->username, 403);
        $task->delete();
        $this->openTask = null;
        Notification::make()->title('Η εργασία διαγράφηκε')->success()->send();
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

    // ----- Guards ------------------------------------------------------------------------------

    /** A task of this project, or 404. */
    private function task(int $id, bool $fail = true): ?MyatTask
    {
        $task = MyatTask::query()->where('ProjectID', $this->project)->find($id);
        abort_if($fail && ! $task, 404);

        return $task;
    }

    private function subtask(int $id): MyatTaskSubtask
    {
        return MyatTaskSubtask::query()->where('TaskID', $this->task((int) $this->openTask)->ID)->findOrFail($id);
    }

    private function comment(int $id): MyatTaskComment
    {
        return MyatTaskComment::query()->where('TaskID', $this->task((int) $this->openTask)->ID)->findOrFail($id);
    }
}
