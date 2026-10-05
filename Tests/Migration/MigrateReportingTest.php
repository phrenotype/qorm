<?php

namespace Tests\Migration;

use Q\Orm\Connection;
use Q\Orm\Helpers;
use Q\Orm\Migration\Migration;
use Q\Orm\Migration\Schema;
use Tests\QormTestCase;

class MigrateReportingTest extends QormTestCase
{
    private static function dropScratchTables(): void
    {
        $pdo = Connection::getInstance();
        $pdo->exec('DROP TABLE IF EXISTS qorm_rep_a');
        $pdo->exec('DROP TABLE IF EXISTS qorm_rep_b');
        $pdo->exec('DROP TABLE IF EXISTS qorm_rep_c');
    }

    public function testFailingStatementReportsIndexAndAppliedCount()
    {
        try {
            Helpers::runAsTransaction(
                'CREATE TABLE qorm_rep_a (x INTEGER); ' .
                'INSERT INTO qorm_nope_missing VALUES (1); ' .
                'CREATE TABLE qorm_rep_b (y INTEGER);'
            );
            $this->fail('Expected PDOException was not thrown');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('statement 2 of 3', $e->getMessage());
            $this->assertStringContainsString('1 executed before the failure', $e->getMessage());
        } finally {
            self::dropScratchTables();
        }
    }

    public function testSuccessPathRunsAllStatements()
    {
        try {
            Helpers::runAsTransaction(
                'CREATE TABLE qorm_rep_a (x INTEGER); CREATE TABLE qorm_rep_b (y INTEGER);'
            );
            $pdo = Connection::getInstance();
            $a = $pdo->query("SELECT name FROM sqlite_master WHERE name='qorm_rep_a'")->fetchColumn();
            $b = $pdo->query("SELECT name FROM sqlite_master WHERE name='qorm_rep_b'")->fetchColumn();
            $this->assertSame('qorm_rep_a', $a);
            $this->assertSame('qorm_rep_b', $b);
        } finally {
            self::dropScratchTables();
        }
    }

    public function testMigrationUpSurfacesStatementFailure()
    {
        $migration = new class extends Migration {
            public function __construct()
            {
                $this->operations = [
                    function () {
                        return Schema::query('CREATE TABLE qorm_rep_c (x INTEGER);');
                    },
                    function () {
                        return Schema::query('INSERT INTO qorm_nope_missing VALUES (1);');
                    },
                ];
                $this->reverse = [];
            }
        };
        try {
            $migration->up();
            $this->fail('Expected PDOException was not thrown');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('statement 2 of 2', $e->getMessage());
        } finally {
            self::dropScratchTables();
        }
    }
}
