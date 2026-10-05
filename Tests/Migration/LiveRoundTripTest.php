<?php

namespace Tests\Migration;

use PHPUnit\Framework\TestCase;
use Q\Orm\Connection;
use Q\Orm\Engines\CrossEngine;
use Q\Orm\Migration\Introspector;
use Q\Orm\Migration\MigrationGenerator;
use Q\Orm\Migration\TableComparer;
use Q\Orm\Querier;
use Q\Orm\SetUp;

/**
 * Live round-trip: models -> DDL -> introspection -> compare() must be
 * silent on an in-sync database, on both the live-fallback and the
 * history-replay paths.
 *
 * Each test runs in its own PHP process (separate-process isolation
 * with global-state preservation disabled) whose only declared models
 * are the Rt* fixtures, require_once'd in setUp(). The main PHPUnit
 * process never declares them, so Helpers::getDeclaredModels()
 * consumers and HelpersTest's exact model count are unaffected.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LiveRoundTripTest extends TestCase
{
    private static $pdo = null;
    private static $admin = null;
    private static $dbName = 'qorm_roundtrip';
    private static $savedEngine = null;
    private static $savedInstance = null;
    private static $savedParameters = null;
    private static $savedCrossPdo = null;
    private static $savedQuerierPdo = null;
    private static $tmpDir = null;

    private static function staticProperty(string $class, string $name): \ReflectionProperty
    {
        $p = new \ReflectionProperty($class, $name);
        $p->setAccessible(true);
        return $p;
    }

    private static function connectionProperty(string $name): \ReflectionProperty
    {
        return self::staticProperty(Connection::class, $name);
    }

    private static function crossPdoProperty(): \ReflectionProperty
    {
        return self::staticProperty(CrossEngine::class, 'pdo');
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('Live round-trip battery requires the pdo_mysql extension.');
        }
        require_once __DIR__ . '/Fixtures/RtModels.php';
        $dbName = (string)(getenv('QORM_TEST_MYSQL_DB') ?: 'qorm_roundtrip');
        $dsn = getenv('QORM_TEST_MYSQL_DSN') ?: ('mysql:host=127.0.0.1;dbname=' . $dbName . ';charset=utf8');
        $user = getenv('QORM_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('QORM_TEST_MYSQL_PASS') ?: '';
        try {
            self::$admin = new \PDO('mysql:host=127.0.0.1;charset=utf8', $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            self::$admin->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
            self::$pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            self::$dbName = $dbName;
        } catch (\PDOException $e) {
            self::markTestSkipped('Live MySQL server unavailable: ' . $e->getMessage());
        }
        self::$savedEngine = SetUp::$engine;
        self::$savedInstance = self::connectionProperty('instance')->getValue(null);
        self::$savedParameters = self::connectionProperty('parameters')->getValue(null);
        self::$savedCrossPdo = self::crossPdoProperty()->getValue(null);
        self::$savedQuerierPdo = self::staticProperty(Querier::class, 'connection')->getValue(null);
        SetUp::$engine = SetUp::MYSQL;
        self::connectionProperty('instance')->setValue(null, self::$pdo);
        self::connectionProperty('parameters')->setValue(null, ['host' => '127.0.0.1', 'name' => $dbName, 'user' => $user, 'pass' => $pass]);
        CrossEngine::setPDO(self::$pdo);
        Querier::setConnection(self::$pdo);
        self::cleanScratch();
    }

    protected function tearDown(): void
    {
        try {
            self::cleanScratch();
        } finally {
            SetUp::$engine = self::$savedEngine;
            if (self::$pdo) {
                self::connectionProperty('instance')->setValue(null, self::$savedInstance);
                self::connectionProperty('parameters')->setValue(null, self::$savedParameters);
                self::crossPdoProperty()->setValue(null, self::$savedCrossPdo);
                self::staticProperty(Querier::class, 'connection')->setValue(null, self::$savedQuerierPdo);
            }
            self::$pdo = null;
            self::$admin = null;
        }
        parent::tearDown();
    }

    private static function cleanScratch(): void
    {
        if (!self::$pdo) {
            return;
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $tables = self::$pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                self::$pdo->exec("DROP TABLE IF EXISTS `$t`");
            }
        } finally {
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private static function declaredTableNames(): array
    {
        return array_map(function ($t) {
            return $t->name;
        }, Introspector::modelsToArrayOfTables());
    }

    private static function createLiveTables(): void
    {
        $tables = Introspector::modelsToArrayOfTables();
        foreach ($tables as $t) {
            self::$pdo->exec(CrossEngine::tableToSql($t));
        }
        foreach ($tables as $t) {
            $fkSql = CrossEngine::tableToFkSql($t);
            if (trim($fkSql) !== '') {
                self::$pdo->exec($fkSql);
            }
        }
    }

    private static function createMigrationTable(): void
    {
        self::$pdo->exec(CrossEngine::createMigrationsTableQuery(SetUp::MYSQL));
    }

    public function testLiveFallbackIsSilentOnInSyncDatabase()
    {
        try {
            $names = self::declaredTableNames();
            $this->assertContains('rt_host', $names);
            $this->assertContains('rt_child', $names);
            $this->assertContains('rt_kitchen', $names);
            self::createLiveTables();
            self::createMigrationTable();
            [$ups, $downs] = TableComparer::compare();
            $this->assertSame([], $ups);
            $this->assertSame([], $downs);
        } finally {
            self::cleanScratch();
        }
    }

    public function testHistoryReplayIsSilentOnInSyncDatabase()
    {
        self::$tmpDir = sys_get_temp_dir() . '/rt_hist_' . uniqid();
        mkdir(self::$tmpDir);
        try {
            self::createMigrationTable();
            [$ups, $downs] = TableComparer::compare();
            $this->assertNotEmpty($ups);
            $code = MigrationGenerator::generateMigration('0001', $ups, $downs);
            file_put_contents(self::$tmpDir . '/Migration0001.php', $code);
            require self::$tmpDir . '/Migration0001.php';
            self::createLiveTables();
            self::$pdo->exec("INSERT INTO q_migration (name, applied) VALUES ('Migration0001', '2026-01-01 00:00:00')");
            [$ups2, $downs2] = TableComparer::compare();
            $this->assertSame([], $ups2);
            $this->assertSame([], $downs2);
        } finally {
            @unlink(self::$tmpDir . '/Migration0001.php');
            @rmdir(self::$tmpDir);
            self::cleanScratch();
        }
    }

    public function testLiveFallbackDetectsRealChange()
    {
        try {
            self::createLiveTables();
            self::createMigrationTable();
            self::$pdo->exec('ALTER TABLE `rt_child` DROP COLUMN `title`');
            [$ups, $downs] = TableComparer::compare();
            $this->assertCount(1, $ups);
            $this->assertCount(1, $downs);
            $this->assertStringContainsString('addColumn', $ups[0]);
            $this->assertStringContainsString('title', $ups[0]);
        } finally {
            self::cleanScratch();
        }
    }
}
