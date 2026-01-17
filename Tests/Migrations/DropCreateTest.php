<?php

namespace Tests\Migrations;

use Tests\QormTestCase;
use Q\Orm\Engines\Mysql;
use Q\Orm\Engines\Sqlite;
use Q\Orm\Migration\Table;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;
use Q\Orm\Helpers;

class DropCreateTest extends QormTestCase
{
    /**
     * Test that MySQL table SQL starts with DROP TABLE IF EXISTS and disables FK checks.
     */
    public function testMysqlTableSqlIncludesDrop()
    {
        $oldEngine = \Q\Orm\SetUp::$engine;
        \Q\Orm\SetUp::$engine = \Q\Orm\SetUp::MYSQL;

        try {
            $table = new Table('test_table', [
                new Column('name', 'varchar', ['size' => 255])
            ]);

            $sql = Mysql::tableToSql($table);

            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
            $this->assertStringContainsString('DROP TABLE IF EXISTS `test_table`;', $sql);
            $this->assertStringContainsString('CREATE TABLE `test_table`', $sql);
            $this->assertStringNotContainsString('CREATE TABLE IF NOT EXISTS', $sql);
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
        } finally {
            \Q\Orm\SetUp::$engine = $oldEngine;
        }
    }

    /**
     * Test that SQLite table SQL starts with DROP TABLE IF EXISTS.
     */
    public function testSqliteTableSqlIncludesDrop()
    {
        $oldEngine = \Q\Orm\SetUp::$engine;
        \Q\Orm\SetUp::$engine = \Q\Orm\SetUp::SQLITE;

        try {
            $table = new Table('test_table', [
                new Column('name', 'varchar', ['size' => 255])
            ]);

            $sql = Sqlite::tableToSql($table);

            $this->assertStringContainsString('DROP TABLE IF EXISTS "test_table";', $sql);
            $this->assertStringContainsString('CREATE TABLE "test_table"', $sql);
            $this->assertStringNotContainsString('CREATE TABLE IF NOT EXISTS', $sql);
        } finally {
            \Q\Orm\SetUp::$engine = $oldEngine;
        }
    }

    /**
     * Test that MySQL index SQL includes prepended DROP INDEX.
     */
    public function testMysqlIndexSqlIncludesDrop()
    {
        $oldEngine = \Q\Orm\SetUp::$engine;
        \Q\Orm\SetUp::$engine = \Q\Orm\SetUp::MYSQL;

        try {
            $sqlUnique = Mysql::addUniqueIndexQuery('test_table', 'email', 'idx_email');
            $this->assertStringContainsString('DROP INDEX `idx_email` ON `test_table`;', $sqlUnique);
            $this->assertStringContainsString('CREATE UNIQUE INDEX `idx_email` ON `test_table`(`email`);', $sqlUnique);

            $sqlIndex = Mysql::addIndexQuery('test_table', 'name', 'idx_name');
            $this->assertStringContainsString('DROP INDEX `idx_name` ON `test_table`;', $sqlIndex);
            $this->assertStringContainsString('CREATE INDEX `idx_name` ON `test_table`(`name`);', $sqlIndex);
        } finally {
            \Q\Orm\SetUp::$engine = $oldEngine;
        }
    }

    /**
     * Test that Helpers::runAsTransaction can handle multi-statement queries and suppress empty ones.
     */
    public function testRunAsTransactionSplitQueries()
    {
        // We can test this in SQLite. Even if suppression of 1091 is MySQL specific,
        // the query splitting logic should work for all.
        $this->ensureTable('dummy', 'id INTEGER PRIMARY KEY');

        // This should run as two statements.
        // Even if SQLite doesn't need splitting for exec(), our logic splits it.
        Helpers::runAsTransaction("INSERT INTO \"dummy\" (id) VALUES (1); INSERT INTO \"dummy\" (id) VALUES (2);");

        $count = self::$pdo->query("SELECT COUNT(*) FROM \"dummy\"")->fetchColumn();
        $this->assertEquals(2, $count);

        self::$pdo->exec("DROP TABLE \"dummy\"");
    }

    /**
     * Test SQL parser edge cases: semicolons in strings, comments, escaping.
     */
    public function testSqlParserEdgeCases()
    {
        $this->ensureTable('parser_test', 'id INTEGER PRIMARY KEY, val TEXT');

        // Test 1: Semicolon inside single-quoted string
        Helpers::runAsTransaction("INSERT INTO \"parser_test\" (id, val) VALUES (1, 'semi;colon');");
        $val = self::$pdo->query("SELECT val FROM \"parser_test\" WHERE id = 1")->fetchColumn();
        $this->assertEquals('semi;colon', $val);

        // Test 2: Double-quote escape (SQL standard '')
        Helpers::runAsTransaction("INSERT INTO \"parser_test\" (id, val) VALUES (2, 'it''s');");
        $val = self::$pdo->query("SELECT val FROM \"parser_test\" WHERE id = 2")->fetchColumn();
        $this->assertEquals("it's", $val);

        // Test 3: Semicolon in line comment (should not split incorrectly)
        Helpers::runAsTransaction("INSERT INTO \"parser_test\" (id, val) VALUES (3, 'before'); -- comment; ignored\nINSERT INTO \"parser_test\" (id, val) VALUES (4, 'after');");
        $count = self::$pdo->query("SELECT COUNT(*) FROM \"parser_test\" WHERE id IN (3, 4)")->fetchColumn();
        $this->assertEquals(2, $count);

        // Test 4: Semicolon in block comment
        Helpers::runAsTransaction("INSERT INTO \"parser_test\" (id, val) VALUES (5, 'block'); /* comment; here */ INSERT INTO \"parser_test\" (id, val) VALUES (6, 'end');");
        $count = self::$pdo->query("SELECT COUNT(*) FROM \"parser_test\" WHERE id IN (5, 6)")->fetchColumn();
        $this->assertEquals(2, $count);

        // Test 5: Double-minus inside string (not a comment)
        Helpers::runAsTransaction("INSERT INTO \"parser_test\" (id, val) VALUES (7, 'test--value');");
        $val = self::$pdo->query("SELECT val FROM \"parser_test\" WHERE id = 7")->fetchColumn();
        $this->assertEquals('test--value', $val);

        self::$pdo->exec("DROP TABLE \"parser_test\"");
    }
}
