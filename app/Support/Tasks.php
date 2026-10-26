<?php

namespace App\Support;

use App\Enums\UserGroup;
use App\Models\MyatProject;
use App\Models\MyatTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Εργασίες: projects with tasks on a Kanban board (tables myat_projects, myat_tasks, ...).
 * Everyone in the back office works the tasks; Admins create, change and delete projects.
 */
class Tasks
{
    /** Kanban columns, in order: key => [label, colour]. */
    public const STATUSES = [
        'todo' => ['Να γίνει', '#64748b'],
        'doing' => ['Σε εξέλιξη', '#0096ff'],
        'review' => ['Έλεγχος', '#f59e0b'],
        'done' => ['Ολοκληρώθηκε', '#10b981'],
    ];

    public const PRIORITIES = [
        'critical' => ['Κρίσιμη', '#dc2626'],
        'high' => ['Υψηλή', '#f97316'],
        'medium' => ['Μεσαία', '#0ea5e9'],
        'low' => ['Χαμηλή', '#94a3b8'],
    ];

    public const COLORS = ['#0096ff', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#eab308', '#10b981', '#14b8a6', '#64748b', '#08345a'];

    public const STARRED = 'myat.tasks.starred';

    /** Files up to this size (kept in the database like the transfer files). */
    public const MAX_FILE_KB = 10240;

    public static function tablesExist(): bool
    {
        static $exists = null;

        return $exists ??= Schema::hasTable('myat_tasks');
    }

    public static function canManageProjects(): bool
    {
        return auth()->user()?->group() === UserGroup::Admin;
    }

    /** The people tasks can be given to: active back-office logins, username => name. */
    public static function people(): array
    {
        return once(fn (): array => User::query()->where('active', 1)->orderBy('fullname')->get(['ID', 'username', 'fullname', 'groupid'])
            ->filter(fn (User $u): bool => (bool) $u->group()?->usesBackOffice() && filled($u->username))
            ->mapWithKeys(fn (User $u): array => [(string) $u->username => $u->fullname ?: (string) $u->username])
            ->all());
    }

    public static function initials(string $name): string
    {
        // First letter of the first two words, skipping brackets and the like: "George (demo)" → GD.
        preg_match_all('/\p{L}[\p{L}\p{M}\'.-]*/u', $name, $m);

        $initials = mb_strtoupper(collect($m[0])->take(2)->map(fn (string $w): string => mb_substr($w, 0, 1))->implode(''));
        // Without accents (Ά → Α), as Greek capitals are written.
        $initials = class_exists(\Normalizer::class) ? preg_replace('/\p{Mn}/u', '', (string) \Normalizer::normalize($initials, \Normalizer::FORM_D)) : $initials;

        return $initials ?: '?';
    }

    /** @return list<int> the user's starred projects */
    public static function starred(): array
    {
        return array_values(array_map('intval', (array) UserSetting::get(self::STARRED, [])));
    }

    public static function toggleStar(int $project): void
    {
        $list = self::starred();
        UserSetting::put(self::STARRED, in_array($project, $list, true) ? array_values(array_diff($list, [$project])) : [...$list, $project]);
    }

    /** @return array<int, array{total: int, done: int}> per project */
    public static function progress(): array
    {
        return DB::table('myat_tasks')->selectRaw("ProjectID, COUNT(*) AS total, SUM(Status = 'done') AS done")->groupBy('ProjectID')->get()
            ->mapWithKeys(fn ($r): array => [(int) $r->ProjectID => ['total' => (int) $r->total, 'done' => (int) $r->done]])->all();
    }

    /** @return array<int, list<string>> task => assignee usernames */
    public static function assignees(array $taskIds): array
    {
        return $taskIds === [] ? [] : DB::table('myat_task_assignees')->whereIn('TaskID', $taskIds)->orderBy('Username')->get()
            ->groupBy('TaskID')->map(fn ($rows): array => $rows->pluck('Username')->map(fn ($u): string => (string) $u)->all())->all();
    }

    public static function markdown(?string $text): HtmlString
    {
        return new HtmlString(Str::markdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }

    /** A comment: text escaped, line breaks kept, @[Name](username) shown as a highlighted name. */
    public static function comment(string $body): HtmlString
    {
        $html = nl2br(e($body));
        $html = preg_replace_callback('/@\[([^\]]{1,80})\]\(([^)\s]{1,255})\)/u', fn (array $m): string => '<span class="myat-mention" title="'.$m[2].'">@'.$m[1].'</span>', $html);

        return new HtmlString((string) $html);
    }

    /** Usernames @mentioned in a comment. */
    public static function mentions(string $body): array
    {
        preg_match_all('/@\[[^\]]{1,80}\]\(([^)\s]{1,255})\)/u', $body, $m);

        return array_values(array_unique($m[1]));
    }

    public static function project(int $id): MyatProject
    {
        return MyatProject::query()->findOrFail($id);
    }

    public static function nextSort(int $project, string $status): int
    {
        return (int) MyatTask::query()->where('ProjectID', $project)->where('Status', $status)->max('Sort') + 1;
    }
}
