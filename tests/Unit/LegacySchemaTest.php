<?php

namespace Tests\Unit;

use App\Support\LegacySchema;
use PHPUnit\Framework\TestCase;

class LegacySchemaTest extends TestCase
{
    public function test_split_handles_delimiter_blocks(): void
    {
        $sql = <<<'SQL'
            -- comment
            CREATE TABLE a (id int);
            DELIMITER $$
            CREATE PROCEDURE p() BEGIN
              SELECT 1;
              SELECT 2;
            END$$
            DELIMITER ;
            START TRANSACTION;
            CREATE TABLE b (id int);
            COMMIT;
            SQL;

        $statements = LegacySchema::split($sql);

        $this->assertCount(3, $statements);
        $this->assertStringContainsString('SELECT 2;', $statements[1]);
        $this->assertStringEndsWith('END', $statements[1]);
        $this->assertSame('CREATE TABLE b (id int)', $statements[2]);
    }

    public function test_the_schema_file_has_every_object_and_no_broken_export_artifacts(): void
    {
        $statements = LegacySchema::split(file_get_contents(__DIR__.'/../../database/legacy/schema.sql'));
        $count = fn (string $pattern): int => count(preg_grep($pattern, $statements));

        $this->assertSame(24, $count('/^CREATE\s+TRIGGER/i'));
        $this->assertSame(1, $count('/^CREATE\s+EVENT/i'));
        $this->assertSame(25, count(array_filter($statements, LegacySchema::isTriggerOrEvent(...))));

        // phpMyAdmin artifacts that MariaDB rejects: empty stand-ins and glued keywords.
        $this->assertSame(0, $count('/^CREATE TABLE `[^`]+` \(\s*\)$/'));
        $this->assertSame(0, $count('/\d(union|select|from|where)\b/i'));
    }

    public function test_older_mariadb_gets_utf8_instead_of_utf8mb3(): void
    {
        $sql = 'CREATE TABLE t (a varchar(5)) DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci';

        $this->assertSame(
            'CREATE TABLE t (a varchar(5)) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci',
            LegacySchema::adaptToServer($sql, '10.4.32-MariaDB'),
        );
        $this->assertSame($sql, LegacySchema::adaptToServer($sql, '11.4.13-MariaDB-cll-lve'));
        $this->assertSame($sql, LegacySchema::adaptToServer($sql, '10.11.6-MariaDB'));
    }

    public function test_case_only_duplicate_tables_are_dropped_for_windows(): void
    {
        $statements = [
            'CREATE TABLE `AppUgMembers` (a int)',
            'CREATE TABLE `appugmembers` (a int)',
            'CREATE TABLE `transfers` (a int)',
            'ALTER TABLE `AppUgMembers` ADD PRIMARY KEY (a)',
            'ALTER TABLE `appugmembers` ADD PRIMARY KEY (a)',
        ];

        $this->assertSame([
            'CREATE TABLE `AppUgMembers` (a int)',
            'CREATE TABLE `transfers` (a int)',
            'ALTER TABLE `AppUgMembers` ADD PRIMARY KEY (a)',
        ], LegacySchema::withoutCaseDuplicates($statements));

        $real = LegacySchema::split(file_get_contents(__DIR__.'/../../database/legacy/schema.sql'));
        $this->assertCount(count($real) - 4, LegacySchema::withoutCaseDuplicates($real));
    }
}
