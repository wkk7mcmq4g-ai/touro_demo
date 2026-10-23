<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates one Eloquent model per table/view of the existing MyAT database.
 *
 *   php artisan app:generate-models            # write missing models
 *   php artisan app:generate-models --force    # regenerate all (overwrites!)
 *
 * Generated models only describe the table (name, key, casts). Put
 * relationships and logic in the model after generation and don't use --force
 * on models you've edited.
 */
class GenerateLegacyModels extends Command
{
    protected $signature = 'app:generate-models {--force : Overwrite existing models} {--table=* : Only these tables}';

    protected $description = 'Generate Eloquent models from the existing MyAT database schema';

    /** Tables that are leftovers, copies, PHPRunner internals or handled elsewhere. */
    private const SKIP = [
        'MyAthensTransfersAPP_users', // App\Models\User
        'MyAthensTransfersAPP6uggroups', 'MyAthensTransfersAPP6ugmembers', 'MyAthensTransfersAPP6ugrights',
        'myathenstransfersapp6ugmembers', 'myathenstransfersapp6ugrights',
        'MyAthensTransfersAPP_settings_copy', 'MyAthensTransfersAPP_users_copy',
        'price_list_old', 'price_list_transfers_copy', 'trans_copy',
        'a_', 'test', 'testapi', 'v_test',
    ];

    private const ID_GUESSES = ['ID', 'id', 'Id', 'TransferID', 'CID', 'OptionID'];

    public function handle(): int
    {
        $db = DB::connection()->getDatabaseName();
        $only = $this->option('table');

        $objects = DB::select(
            'select TABLE_NAME as name, TABLE_TYPE as type from information_schema.TABLES where TABLE_SCHEMA = ? order by TABLE_NAME',
            [$db]
        );

        $written = 0;

        foreach ($objects as $object) {
            if (in_array($object->name, self::SKIP, true) || ($only && ! in_array($object->name, $only, true))) {
                continue;
            }

            $class = $this->className($object->name);
            $path = app_path("Models/{$class}.php");

            if (file_exists($path) && ! $this->option('force')) {
                continue;
            }

            $columns = DB::select(
                'select COLUMN_NAME as name, DATA_TYPE as type, COLUMN_TYPE as full_type, NUMERIC_SCALE as scale, COLUMN_KEY as col_key, EXTRA as extra
                 from information_schema.COLUMNS where TABLE_SCHEMA = ? and TABLE_NAME = ? order by ORDINAL_POSITION',
                [$db, $object->name]
            );

            if (! $columns) {
                $this->warn("Skipped {$object->name}: no readable columns (broken view?).");

                continue;
            }

            file_put_contents($path, $this->render($object->name, $class, $object->type === 'VIEW', $columns));
            $written++;
        }

        $this->info("Wrote {$written} model(s).");

        return self::SUCCESS;
    }

    public function className(string $table): string
    {
        $overrides = [
            'v_fuel_analysis' => 'VFuelAnalysis',
            'optionvalues' => 'OptionValue',
            'MyAthensTransfersAPP_settings' => 'AppSetting',
            'PaymentsReceived' => 'PaymentReceived',
            'paymentlinks' => 'PaymentLink',
        ];

        $base = $overrides[$table] ?? Str::singular(Str::studly($table));

        return match (true) {
            is_numeric($base[0] ?? '') => 'T'.$base,
            in_array($base, ['User', 'Model'], true) => $base.'Record',
            default => $base,
        };
    }

    private function render(string $table, string $class, bool $isView, array $columns): string
    {
        $pk = array_values(array_filter($columns, fn ($c) => $c->col_key === 'PRI'));
        $names = array_column($columns, 'name');

        if (count($pk) === 1) {
            $key = $pk[0]->name;
            $incrementing = str_contains($pk[0]->extra, 'auto_increment');
            $intKey = in_array($pk[0]->type, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true);
        } else {
            $key = collect(self::ID_GUESSES)->first(fn ($g) => in_array($g, $names, true)) ?? $names[0];
            $incrementing = false;
            $intKey = false;
        }

        $casts = [];
        $props = [];

        foreach ($columns as $c) {
            [$cast, $phpType] = $this->castFor($c);
            if ($cast) {
                $casts[] = "            '{$c->name}' => '{$cast}',";
            }
            $props[] = " * @property {$phpType}|null \${$c->name}";
        }

        $castsBlock = $casts ? "\n    protected function casts(): array\n    {\n        return [\n".implode("\n", $casts)."\n        ];\n    }\n" : '';
        $readOnly = $isView ? "    use ReadOnlyModel;\n\n" : '';
        $readOnlyImport = $isView ? "use App\\Models\\Concerns\\ReadOnlyModel;\n" : '';
        $kind = $isView ? 'database view (read-only)' : 'table';
        $incr = $incrementing ? 'true' : 'false';
        $keyType = $intKey ? 'int' : 'string';
        $note = count($pk) > 1 ? "\n *\n * Note: composite primary key in the database; Eloquent uses `{$key}`." : '';
        $propsBlock = implode("\n", $props);

        return <<<PHP
<?php

namespace App\\Models;

{$readOnlyImport}use Illuminate\\Database\\Eloquent\\Model;

/**
 * `{$table}` {$kind} of the MyAT database.
 * Generated by `php artisan app:generate-models`; add relationships below.{$note}
 *
{$propsBlock}
 */
class {$class} extends Model
{
{$readOnly}    protected \$table = '{$table}';

    protected \$primaryKey = '{$key}';

    public \$incrementing = {$incr};

    protected \$keyType = '{$keyType}';

    public \$timestamps = false;

    protected \$guarded = [];
{$castsBlock}}

PHP;
    }

    /** @return array{0: ?string, 1: string} */
    private function castFor(object $c): array
    {
        return match ($c->type) {
            'int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'year' => ['integer', 'int'],
            'decimal' => ['decimal:'.max(0, (int) $c->scale), 'string'],
            'double', 'float' => ['float', 'float'],
            'date' => ['date', '\\Illuminate\\Support\\Carbon'],
            'datetime', 'timestamp' => ['datetime', '\\Illuminate\\Support\\Carbon'],
            'json' => ['array', 'array'],
            default => [null, 'string'],
        };
    }
}
