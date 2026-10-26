<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\MyatTask;
use App\Models\MyatTaskComment;
use App\Models\MyatTaskFile;
use App\Models\MyatTaskSubtask;
use App\Support\Tasks;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\WithFileUploads;

/**
 * The task panel (resources/views/filament/tasks/panel.blade.php) and everything it saves, for the
 * office's project board and the drivers' "Οι εργασίες μου" in the portal. The page says which tasks
 * it may open (taskScope) and whether the user only works the task (limited: drivers move it along,
 * tick subtasks, comment and add files; the office changes the rest).
 *
 * The page needs a public ?int $openTask (the open task, usually #[Url(as: 'task')]).
 */
trait WorksOnTasks
{
    use WithFileUploads;

    /** Files dropped on the open task. */
    public array $uploads = [];

    abstract protected function taskScope(): Builder;

    protected function limited(): bool
    {
        return false;
    }

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
        $limited = $this->limited();
        $subtasks = $task->subtasks()->get();

        return [
            'id' => $task->ID, 'title' => $task->Title, 'description' => (string) $task->Description,
            'html' => Tasks::markdown($task->Description), 'status' => $task->Status, 'priority' => $task->Priority,
            'due' => $task->DueDate?->toDateString(), 'transfer' => $task->TransferID,
            'transferUrl' => $task->TransferID && ! $limited ? TransferResource::getUrl('edit', ['record' => $task->TransferID], panel: 'admin') : null,
            'limited' => $limited, 'project' => $task->project?->only(['Name', 'Emoji', 'Color']),
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
                'canDelete' => ! $limited || $f->Username === $me,
            ])->all(),
            'createdBy' => $people[$task->CreatedBy] ?? $task->CreatedBy, 'createdAt' => $task->CreatedAt?->format('d/m/Y H:i'),
            'completedAt' => $task->CompletedAt?->format('d/m/Y H:i'),
            'canDelete' => ! $limited && ($admin || $task->CreatedBy === $me),
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
        // Drivers move their task along; the rest is the office's.
        abort_if($this->limited() && $field !== 'Status', 403);

        match ($field) {
            'Title' => $value !== '' ? $task->Title = mb_substr((string) $value, 0, 250) : null,
            'Description' => $task->Description = mb_substr((string) $value, 0, 20000),
            'Status' => isset(Tasks::STATUSES[$value]) && $value !== $task->Status
                ? [$task->Status = $value, $task->Sort = Tasks::nextSort($task->ProjectID, $value)] : null,
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
        abort_if($this->limited(), 403);
        $task = $this->task((int) $this->openTask);
        abort_unless(isset(Tasks::people()[$username]), 422);

        $row = ['TaskID' => $task->ID, 'Username' => $username];
        DB::table('myat_task_assignees')->where($row)->exists()
            ? DB::table('myat_task_assignees')->where($row)->delete()
            : DB::table('myat_task_assignees')->insert($row);
    }

    public function addSubtask(string $title): void
    {
        abort_if($this->limited(), 403);
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
        abort_if($this->limited(), 403);
        if (($title = trim($title)) !== '') {
            $this->subtask($id)->update(['Title' => mb_substr($title, 0, 250)]);
        }
    }

    public function deleteSubtask(int $id): void
    {
        abort_if($this->limited(), 403);
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
        abort_unless($comment->Username === auth()->user()->username || (! $this->limited() && Tasks::canManageProjects()), 403);
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
        MyatTaskFile::query()->where('TaskID', $task->ID)->whereKey($id)
            ->when($this->limited(), fn ($q) => $q->where('Username', (string) auth()->user()->username))->delete();
    }

    public function deleteTask(): void
    {
        $task = $this->task((int) $this->openTask);
        abort_unless(! $this->limited() && (Tasks::canManageProjects() || $task->CreatedBy === auth()->user()->username), 403);
        $task->delete();
        $this->openTask = null;
        Notification::make()->title('Η εργασία διαγράφηκε')->success()->send();
    }

    /** A task this page may open, or 404. */
    private function task(int $id, bool $fail = true): ?MyatTask
    {
        $task = $this->taskScope()->find($id);
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
