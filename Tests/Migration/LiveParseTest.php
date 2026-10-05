<?php

namespace Tests\Migration;

use PHPUnit\Framework\TestCase;
use Q\Orm\Connection;
use Q\Orm\Engines\Mysql;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\SetUp;

class LiveParseTest extends TestCase
{
    private static $pdo = null;
    private static $dbName = 'qorm_test';
    private static $swapped = false;
    private static $savedEngine = null;
    private static $savedInstance = false;
    private static $savedParameters = false;

    private static function connectionProperty(string $name): \ReflectionProperty
    {
        $p = new \ReflectionProperty(Connection::class, $name);
        $p->setAccessible(true);
        return $p;
    }

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('Live MySQL parse battery requires the pdo_mysql extension.');
        }
        $dbName = (string) (getenv('QORM_TEST_MYSQL_DB') ?: 'qorm_test');
        $dsn = getenv('QORM_TEST_MYSQL_DSN') ?: ('mysql:host=127.0.0.1;dbname=' . $dbName . ';charset=utf8');
        $user = getenv('QORM_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('QORM_TEST_MYSQL_PASS') ?: '';
        try {
            self::$pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            self::$dbName = $dbName;
        } catch (\PDOException $e) {
            self::markTestSkipped('Live MySQL server unavailable: ' . $e->getMessage());
        }
        // Mysql::tableNameToTable reads the global connection, so point the
        // globals at MySQL for this class and restore them afterwards. This
        // keeps the battery safe inside full-suite (SQLite) runs.
        self::$savedEngine = SetUp::$engine;
        self::$savedInstance = self::connectionProperty('instance')->getValue(null);
        self::$savedParameters = self::connectionProperty('parameters')->getValue(null);
        SetUp::$engine = SetUp::MYSQL;
        self::connectionProperty('instance')->setValue(null, self::$pdo);
        self::connectionProperty('parameters')->setValue(null, ['host' => '127.0.0.1', 'name' => $dbName, 'user' => $user, 'pass' => $pass]);
        self::$swapped = true;
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS qorm_live_probe (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, e ENUM(\'a\',\'b\') NOT NULL, v VARCHAR(100) NULL, b TINYINT(1) NOT NULL DEFAULT 0)');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo) {
            self::$pdo->exec('DROP TABLE IF EXISTS qorm_live_child');
            self::$pdo->exec('DROP TABLE IF EXISTS qorm_live_probe');
            self::$pdo = null;
        }
        if (self::$swapped) {
            SetUp::$engine = self::$savedEngine;
            self::connectionProperty('instance')->setValue(null, self::$savedInstance);
            self::connectionProperty('parameters')->setValue(null, self::$savedParameters);
            self::$swapped = false;
        }
    }

    public function testDescribeColumnMapping()
    {
        $table = Mysql::tableNameToTable('qorm_live_probe', []);
        $byName = [];
        foreach ($table->fields as $c) {
            $byName[$c->name] = $c;
        }
        $this->assertSame('bigint', $byName['id']->type);
        $this->assertTrue($byName['id']->unsigned);
        $this->assertTrue($byName['id']->auto_increment);
        // Wart pinned, not endorsed: the DESCRIBE regex cannot capture enum
        // value lists, so live enum columns parse to an empty type.
        $this->assertSame('', $byName['e']->type);
        $this->assertSame('varchar', $byName['v']->type);
        $this->assertSame('100', $byName['v']->size);
        $this->assertTrue($byName['v']->null);
        $this->assertSame('tinyint', $byName['b']->type);
        $this->assertSame('1', $byName['b']->size);
        $this->assertSame('0', $byName['b']->default);
    }

    public function testPrimaryKeyMapping()
    {
        $table = Mysql::tableNameToTable('qorm_live_probe', []);
        $pk = TableModelFinder::findTableIndex($table, function ($t, $i) {
            return $i->type === Index::PRIMARY_KEY;
        });
        $this->assertNotNull($pk);
        $this->assertSame('id', $pk->field);
    }

    public function testForeignKeyDiscoveryUsesConventionalName()
    {
        $pdo = Connection::getInstance();
        $pdo->exec('DROP TABLE IF EXISTS qorm_live_child');
        $pdo->exec('CREATE TABLE qorm_live_child (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, probe BIGINT UNSIGNED NOT NULL, CONSTRAINT fk_qorm_live_child_probe FOREIGN KEY (probe) REFERENCES qorm_live_probe(id) ON DELETE CASCADE)');
        $fks = Mysql::findSchemaFks($pdo, 'qorm_live_child');
        $this->assertCount(1, $fks);
        $this->assertSame('probe', $fks[0]->field);
        $this->assertSame('qorm_live_probe', $fks[0]->refTable);
        $this->assertSame('id', $fks[0]->refField);
        $this->assertSame('CASCADE', $fks[0]->onDelete);
    }

    public function testSchemaToTablesDiscoversProbeTable()
    {
        $tables = Mysql::schemaToTables(Connection::getInstance(), self::$dbName);
        $names = array_map(function ($t) {
            return $t->name;
        }, $tables);
        $this->assertContains('qorm_live_probe', $names);
    }
}
