<?php

namespace Tests;

use App\Models\User;
use App\Support\Lookups;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    private static bool $schemaReady = false;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Lookups::flush();

        // phpunit.xml points at `myat_testing`; rebuild it from the schema once per run,
        // so a leftover database can never hide a broken schema file.
        if (! self::$schemaReady) {
            $exitCode = Artisan::call('app:load-schema', [
                '--database' => config('database.connections.mariadb.database'),
                '--fresh' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('Could not build the test database: '.Artisan::output());
            }

            self::$schemaReady = true;
        }
    }

    protected function tearDown(): void
    {
        // The users table is MyISAM (no transactions), so clean up by hand.
        if ($this->createdUserIds !== []) {
            DB::table('MyAthensTransfersAPP_users')->whereIn('ID', $this->createdUserIds)->delete();
            DB::table('access_log')->where('userid', 'like', 'test\_%')->delete();
        }

        parent::tearDown();
    }

    /**
     * Empties tables of the test database. Refuses to run on anything not named *_testing.
     *
     * @param  list<string>  $tables
     */
    protected function emptyTables(array $tables): void
    {
        $database = DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_testing')) {
            throw new \RuntimeException("Refusing to empty tables in [{$database}].");
        }

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeLegacyUser(string $group = 'Admin', array $attributes = []): User
    {
        $id = DB::table('MyAthensTransfersAPP_users')->max('ID') + 1;

        DB::table('MyAthensTransfersAPP_users')->insert(array_merge([
            'ID' => $id,
            'username' => 'test_'.strtolower($group).'_'.$id,
            'password' => password_hash('secret-pass', PASSWORD_DEFAULT),
            'fullname' => 'Test '.$group,
            'groupid' => $group,
            'active' => 1,
        ], $attributes));

        $this->createdUserIds[] = $id;

        return User::findOrFail($id);
    }
}
