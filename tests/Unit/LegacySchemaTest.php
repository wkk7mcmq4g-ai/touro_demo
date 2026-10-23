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
}
