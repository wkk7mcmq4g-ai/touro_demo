<?php

namespace App\Support;

use App\Models\MyatActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ιστορικό ενεργειών: one row in `myat_activity_log` per login, logout, insert, update and delete
 * made through the new app, with who, when, from where and which app version.
 *
 * - Saves of a model (the forms, inline edits, the Coach window…): the model events give the
 *   fields that changed, old → new; an insert or delete keeps the row's values.
 * - Every other write (query-builder updates, mass updates, inserts into link tables, stored
 *   procedures called by the app): caught from the SQL itself, kept as the statement + values.
 * - Writes that happen *because of* a model save (its own saved/deleting hooks, e.g. the price
 *   recalculation procedure) are part of that save and not logged again; neither are the database's
 *   own triggers and events, nor the old PHPRunner app (it keeps its trail in myat_audit_audit).
 * - Not logged: the per-user screen settings (table layouts, menu; written on every page) and the
 *   log itself. Passwords are never written; file contents are replaced by their size.
 *
 * Nothing here may break the app: if the table is missing (SQL not run yet) or a write fails,
 * the action goes on unlogged.
 */
class ActivityLog
{
    public const TABLE = 'myat_activity_log';

    /** Tables never logged: the log itself and per-user screen settings saved on every request. */
    public const SKIP_TABLES = [self::TABLE, 'MyAthensTransfersAPP_settings'];

    public const ACTIONS = ['login', 'logout', 'insert', 'update', 'delete', 'call'];

    private const MAX_TEXT = 2000;

    private static int $quiet = 0;

    private static ?bool $exists = null;

    /**
     * The log is written over its own connection to the same database: a write on the app's
     * connection would change its last insert id between an INSERT and the `insertGetId()` that
     * reads it right after (the new record would get the log row's number).
     */
    public const CONNECTION = 'activity_log';

    public static function register(): void
    {
        $default = config('database.default');
        if (! config('database.connections.'.self::CONNECTION)) {
            config(['database.connections.'.self::CONNECTION => config('database.connections.'.$default)]);
        }

        foreach (['created' => 'insert', 'updated' => 'update', 'deleted' => 'delete'] as $event => $action) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $data) use ($action): void {
                if (($model = $data[0] ?? null) instanceof Model) {
                    self::model($action, $model);
                }
            });
        }

        DB::listen(fn (QueryExecuted $query) => self::query($query));
    }

    public static function enabled(): bool
    {
        if (self::$exists === null) {
            try {
                self::$exists = Schema::connection(self::CONNECTION)->hasTable(self::TABLE);
            } catch (Throwable) {
                self::$exists = false;
            }
        }

        return self::$exists;
    }

    /** For tests: forget whether the table exists. */
    public static function reset(): void
    {
        self::$exists = null;
        self::$quiet = 0;
    }

    /** Run something without logging its writes (e.g. the login bookkeeping, which logs itself as "login"). */
    public static function quietly(callable $callback): mixed
    {
        self::$quiet++;
        try {
            return $callback();
        } finally {
            self::$quiet--;
        }
    }

    public static function login(?string $username): void
    {
        self::write('login', '', '', null, $username);
    }

    public static function logout(?string $username): void
    {
        self::write('logout', '', '', null, $username);
    }

    /** The version written with each row: "134 (3f57501)". */
    public static function version(): string
    {
        ['number' => $number, 'commit' => $commit] = AppVersion::get();

        return trim(($number ?? '').($commit ? ' ('.$commit.')' : ''));
    }

    private static function model(string $action, Model $model): void
    {
        $table = $model->getTable();
        if (self::$quiet > 0 || $model instanceof MyatActivityLog || in_array($table, self::SKIP_TABLES, true)) {
            return;
        }

        $changes = match ($action) {
            'update' => collect($model->getChanges())
                ->reject(fn ($new, string $field): bool => self::same($model->getRawOriginal($field), $new))
                ->mapWithKeys(fn ($new, string $field): array => [$field => [self::clean($field, $model->getRawOriginal($field)), self::clean($field, $new)]])
                ->all(),
            default => collect($model->getAttributes())
                ->reject(fn ($value): bool => $value === null || $value === '')
                ->mapWithKeys(fn ($value, string $field): array => [$field => self::clean($field, $value)])
                ->all(),
        };

        if ($action === 'update' && $changes === []) {
            return;
        }

        self::write($action, $table, (string) $model->getKey(), $changes);
    }

    private static function query(QueryExecuted $query): void
    {
        if (self::$quiet > 0 || ! preg_match('/^\s*(insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from|call)\s+`?([\w.]+)`?/i', $query->sql, $m)) {
            return;
        }

        $verb = strtolower(strtok($m[1], ' '));
        $action = ['replace' => 'insert'][$verb] ?? $verb;
        $table = str_contains($m[2], '.') ? Str::afterLast($m[2], '.') : $m[2];
        if ($query->connectionName === self::CONNECTION || in_array($table, self::SKIP_TABLES, true) || self::fromModel()) {
            return;
        }

        $record = '';
        if ($action === 'insert') {
            try {
                $record = (string) ($query->connection->getPdo()->lastInsertId() ?: '');
            } catch (Throwable) {
            }
        } elseif (preg_match('/\bwhere\s+`?(?:\w+`?\.`?)?(\w*id)`?\s*=\s*\?/i', $query->sql, $w, PREG_OFFSET_CAPTURE)) {
            $index = substr_count(substr($query->sql, 0, $w[0][1]), '?');
            $record = (string) ($query->bindings[$index] ?? '');
        }

        $sensitive = (bool) preg_match('/password/i', $query->sql);
        $bindings = array_map(fn ($value) => $sensitive ? '***' : self::clean('', $value), $query->bindings);
        $prefix = $query->connectionName !== config('database.default') ? $query->connectionName.':' : '';

        self::write($action, $prefix.$table, $record, ['sql' => Str::limit($query->sql, self::MAX_TEXT), 'bindings' => $bindings]);
    }

    /**
     * Is this statement part of a model save or delete (already logged by the model events), or of
     * the model's own hooks around it (the consequences of that save)?
     */
    private static function fromModel(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            if (is_a($class, Model::class, true) && in_array($function, ['performInsert', 'performUpdate', 'performDeleteOnModel', 'fireModelEvent'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function write(string $action, string $table, string $record, ?array $changes, ?string $username = null): void
    {
        if (self::$quiet > 0 || ! self::enabled()) {
            return;
        }

        self::$quiet++;
        try {
            [$url, $panel] = self::where();
            DB::connection(self::CONNECTION)->table(self::TABLE)->insert([
                'CreatedAt' => now(),
                'Username' => Str::limit((string) ($username ?? auth()->user()?->username ?? ''), 250, ''),
                'IP' => app()->runningInConsole() ? '' : (string) request()->ip(),
                'Action' => $action,
                'TableName' => Str::limit($table, 100, ''),
                'RecordID' => Str::limit($record, 100, ''),
                'Changes' => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                'Panel' => $panel,
                'Url' => Str::limit($url, 500, ''),
                'AppVersion' => Str::limit(self::version(), 40, ''),
            ]);
        } catch (Throwable $e) {
            report($e);
        } finally {
            self::$quiet--;
        }
    }

    /** @return array{0: string, 1: string} the screen the user was on (for Livewire calls: the page behind it) and its panel */
    private static function where(): array
    {
        if (app()->runningInConsole()) {
            return ['console: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 4)), ''];
        }

        $request = request();
        $path = '/'.ltrim($request->path(), '/');
        if (str_starts_with($path, '/livewire') && ($referer = $request->headers->get('referer'))) {
            $path = (string) (parse_url($referer, PHP_URL_PATH) ?: $path).(($q = parse_url($referer, PHP_URL_QUERY)) ? '?'.$q : '');
        }
        $panel = preg_match('#^/(admin|portal)(/|$)#', $path, $m) ? $m[1] : '';

        return [$path, $panel];
    }

    private static function same(mixed $old, mixed $new): bool
    {
        return self::scalar($old) === self::scalar($new);
    }

    private static function scalar(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => json_encode($value),
        };
    }

    /** A value as it goes into the log: no passwords, no file contents, long text shortened. */
    private static function clean(string $field, mixed $value): mixed
    {
        if ($field !== '' && preg_match('/password|passwd|^pass$|token|secret/i', $field)) {
            return '***';
        }

        $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        if (! is_string($value)) {
            return is_scalar($value) || $value === null ? $value : json_decode((string) json_encode($value), true);
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '(αρχείο, '.number_format(strlen($value), 0, ',', '.').' bytes)';
        }

        return mb_strlen($value) > self::MAX_TEXT ? mb_substr($value, 0, self::MAX_TEXT).'…' : $value;
    }
}
