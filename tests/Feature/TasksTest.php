<?php

namespace Tests\Feature;

use App\Filament\Pages\Tasks\MyTasks;
use App\Filament\Pages\Tasks\ProjectBoard;
use App\Filament\Pages\Tasks\Projects;
use App\Models\MyatProject;
use App\Models\MyatTask;
use App\Support\Tasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Εργασίες: projects, Kanban, the task panel and "Οι εργασίες μου". */
class TasksTest extends TestCase
{
    private const TABLES = ['myat_projects', 'myat_tasks', 'myat_task_assignees', 'myat_task_subtasks', 'myat_task_comments', 'myat_task_files'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(self::TABLES);
    }

    protected function tearDown(): void
    {
        DB::table('MyAthensTransfersAPP_settings')->where('TABLENAME', Tasks::STARRED)->delete();
        parent::tearDown();
    }

    public function test_admin_creates_a_project_and_works_its_tasks_on_the_board(): void
    {
        $admin = $this->makeLegacyUser('Admin', ['fullname' => 'Άρης Μπιλίκτσης']);
        $entry = $this->makeLegacyUser('Entry', ['fullname' => 'Maria Office']);
        $this->actingAs($admin);

        Livewire::test(Projects::class)->assertOk()->assertSee('Δεν υπάρχουν έργα')
            ->callAction('create', ['Name' => 'Στόλος', 'Emoji' => '🚐', 'Color' => '#10b981', 'Description' => 'ΚΤΕΟ'])
            ->assertRedirect();
        $project = MyatProject::query()->sole();
        $this->assertSame(['Στόλος', '🚐', '#10b981', $admin->username], [$project->Name, $project->Emoji, $project->Color, $project->CreatedBy]);

        $board = Livewire::test(ProjectBoard::class, ['project' => $project->ID])->assertOk()->assertSee('Στόλος')
            ->call('createTask', 'todo', 'ΚΤΕΟ Vito')
            ->call('createTask', 'todo', 'Ασφάλεια')
            ->call('createTask', 'doing', 'Service');
        [$ktep, $insurance, $service] = MyatTask::query()->orderBy('ID')->get()->all();
        $this->assertSame([1, 2, 1], [$ktep->Sort, $insurance->Sort, $service->Sort]);

        // dragged to "Σε εξέλιξη", above Service
        $board->call('moveTask', $insurance->ID, 'doing', [$insurance->ID, $service->ID]);
        $this->assertSame(['doing', 1], [$insurance->fresh()->Status, $insurance->fresh()->Sort]);
        $this->assertSame(2, $service->fresh()->Sort);

        // the panel
        $board->call('open', $ktep->ID)->assertSet('openTask', $ktep->ID)
            ->call('saveField', 'Priority', 'high')
            ->call('saveField', 'DueDate', '2026-10-20')
            ->call('saveField', 'Description', "**Ραντεβού**\n\n<script>x</script>")
            ->call('toggleAssignee', $entry->username)
            ->call('addSubtask', 'Ραντεβού')->call('addSubtask', 'Παράβολο')
            ->call('addComment', "Το αναλαμβάνεις @[Maria Office]({$entry->username});")
            ->set('uploads', [UploadedFile::fake()->createWithContent('ktep.txt', 'hello')])
            ->assertSee('ktep.txt')->assertSee('@Maria Office')
            ->assertSeeHtml('<strong>Ραντεβού</strong>')->assertDontSeeHtml('<script>x</script>');
        $ktep->refresh();
        $this->assertSame(['high', '2026-10-20'], [$ktep->Priority, $ktep->DueDate->toDateString()]);
        $this->assertSame([$entry->username], $ktep->assigneeUsernames());
        $this->assertSame('hello', DB::table('myat_task_files')->value('Attachment'));
        $sub = DB::table('myat_task_subtasks')->where('Title', 'Ραντεβού')->value('ID');
        $board->call('toggleSubtask', $sub);
        $this->assertSame(1, (int) DB::table('myat_task_subtasks')->where('ID', $sub)->value('Done'));

        // done → completion time; back → cleared
        $board->call('saveField', 'Status', 'done');
        $this->assertNotNull($ktep->fresh()->CompletedAt);
        $board->call('saveField', 'Status', 'todo');
        $this->assertNull($ktep->fresh()->CompletedAt);

        // a transfer number that doesn't exist is refused
        $board->call('saveField', 'TransferID', '999999')->assertHasErrors('transfer');

        // the card shows it all; search and filters
        $cards = collect($board->instance()->tasks())->keyBy('id');
        $this->assertSame(['subTotal' => 2, 'subDone' => 1, 'comments' => 1, 'files' => 1], array_intersect_key($cards[$ktep->ID], array_flip(['subTotal', 'subDone', 'comments', 'files'])));
        $this->assertSame('MO', $cards[$ktep->ID]['people'][0]['initials']);
        $this->assertSame([$ktep->ID], array_column($board->set('search', 'ktεo')->set('search', 'ΚΤΕΟ')->instance()->tasks(), 'id'));
        $this->assertSame([$ktep->ID], array_column($board->set('search', '')->set('who', $entry->username)->instance()->tasks(), 'id'));
        $this->assertCount(2, $board->set('who', 'none')->instance()->tasks());

        // Entry: sees "my tasks", works the task, but can't manage the project or delete others' tasks
        $this->actingAs($entry);
        $mine = Livewire::test(MyTasks::class)->assertOk()->assertSee('ΚΤΕΟ Vito')->assertDontSee('Ασφάλεια');
        $this->assertSame('high', array_key_first($mine->instance()->groups()));
        Carbon::setTestNow('2026-10-26 10:00');
        $this->assertSame('1', MyTasks::getNavigationBadge());
        Carbon::setTestNow();
        $entryBoard = Livewire::test(ProjectBoard::class, ['project' => $project->ID, 'openTask' => $ktep->ID])
            ->assertActionHidden('edit')
            ->call('addComment', 'ok');
        $this->assertSame(2, DB::table('myat_task_comments')->count());
        $entryBoard->call('deleteTask')->assertForbidden();
        Livewire::test(Projects::class)->assertActionHidden('create')->call('setArchived', $project->ID, true)->assertForbidden();

        // a task of another project can't be reached from this board
        $other = MyatProject::query()->create(['Name' => 'Άλλο']);
        $foreign = MyatTask::query()->create(['ProjectID' => $other->ID, 'Title' => 'x']);
        Livewire::test(ProjectBoard::class, ['project' => $project->ID])->call('open', $foreign->ID)->assertNotFound();

        // admin deletes the task with all its pieces
        $this->actingAs($admin);
        Livewire::test(ProjectBoard::class, ['project' => $project->ID])->set('openTask', $ktep->ID)->call('deleteTask');
        foreach (['myat_task_assignees', 'myat_task_subtasks', 'myat_task_comments', 'myat_task_files'] as $table) {
            $this->assertSame(0, DB::table($table)->where('TaskID', $ktep->ID)->count(), $table);
        }
    }

    public function test_starred_projects_and_files(): void
    {
        $user = $this->makeLegacyUser('Entry');
        $this->actingAs($user);
        $p = MyatProject::query()->create(['Name' => 'Οχήματα', 'Emoji' => '🚌']);
        $task = MyatTask::query()->create(['ProjectID' => $p->ID, 'Title' => 't']);
        $file = DB::table('myat_task_files')->insertGetId(['TaskID' => $task->ID, 'Flnm' => 'a.pdf', 'Attachment' => '%PDF-1.4 test', 'Mime' => 'application/pdf']);

        Livewire::test(Projects::class)->call('toggleStar', $p->ID);
        $this->assertSame([$p->ID], Tasks::starred());
        $this->assertContains('🚌 Οχήματα', array_map(fn ($i) => $i->getLabel(), Projects::getNavigationItems()));

        $this->get(route('task-files.download', $file))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        auth()->logout();
        $this->get(route('task-files.download', $file))->assertForbidden();
    }
}
