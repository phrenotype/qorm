<?php

namespace Tests\Migration;

use PHPUnit\Framework\TestCase;
use Q\Orm\Connection;
use Q\Orm\Engines\CrossEngine;
use Q\Orm\Field;
use Q\Orm\Migration\Migration;
use Q\Orm\Migration\MigrationGenerator;
use Q\Orm\Migration\MigrationMaker;
use Q\Orm\Migration\Models\Q_Migration;
use Q\Orm\Migration\Schema;
use Q\Orm\Migration\SchemaBuilder;
use Q\Orm\Migration\StateBuilder;
use Q\Orm\Querier;
use Q\Orm\SetUp;

class SiWidgetMigration extends Migration
{
    public function __construct()
    {
        $this->operations = [
            function () {
                return Schema::create('si_widget', function (SchemaBuilder $tb) {
                    $tb->string('label', array(
                        'name' => 'label',
                        'type' => Field::CHAR,
                        'size' => 100,
                        'null' => false,
                    ));
                });
            },
        ];
        $this->reverse = [];
    }
}

/**
 * Integrity battery: history rows are never deleted, unregistered
 * files are inserted without touching existing rows, and replay
 * skips migrations whose class cannot be resolved.
 *
 * Runs in-process on purpose. The missing-file warning is written
 * directly to STDOUT, which would corrupt the result channel of an
 * isolated child process. Fixture classes here are Migration
 * subclasses, never Models, so the main-process model census used
 * by HelpersTest is unaffected. All mutated statics are saved and
 * restored around every test.
 */
class CheckIntegrityTest extends TestCase
{
    private static $pdo = null;
    private static $admin = null;
    private static $dbName = 'qorm_integrity';
    private static $savedEngine = null;
    private static $savedFolder = null;
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

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('Integrity battery requires the pdo_mysql extension.');
        }
        $dbName = (string)(getenv('QORM_TEST_MYSQL_DB') ?: 'qorm_integrity');
        if ($dbName === 'qorm_roundtrip') {
            $dbName = 'qorm_integrity';
        }
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
        self::$savedFolder = SetUp::$migrationsFolder;
        self::$savedInstance = self::staticProperty(Connection::class, 'instance')->getValue(null);
        self::$savedParameters = self::staticProperty(Connection::class, 'parameters')->getValue(null);
        self::$savedCrossPdo = self::staticProperty(CrossEngine::class, 'pdo')->getValue(null);
        self::$savedQuerierPdo = self::staticProperty(Querier::class, 'connection')->getValue(null);
        SetUp::$engine = SetUp::MYSQL;
        self::connectionPropertyFix();
        self::$tmpDir = sys_get_temp_dir() . '/si_mig_' . uniqid();
        mkdir(self::$tmpDir);
        SetUp::$migrationsFolder = self::$tmpDir;
        self::$pdo->exec(CrossEngine::createMigrationsTableQuery(SetUp::MYSQL));
        self::cleanRows();
    }

    private static function connectionPropertyFix(): void
    {
        self::staticProperty(Connection::class, 'instance')->setValue(null, self::$pdo);
        self::staticProperty(Connection::class, 'parameters')->setValue(null, [
            'host' => '127.0.0.1',
            'name' => self::$dbName,
            'user' => getenv('QORM_TEST_MYSQL_USER') ?: 'root',
            'pass' => getenv('QORM_TEST_MYSQL_PASS') ?: '',
        ]);
        CrossEngine::setPDO(self::$pdo);
        Querier::setConnection(self::$pdo);
    }

    protected function tearDown(): void
    {
        try {
            if (self::$pdo) {
                self::cleanRows();
                self::$pdo->exec('DROP TABLE IF EXISTS `q_migration`');
            }
            if (self::$tmpDir && is_dir(self::$tmpDir)) {
                foreach (glob(self::$tmpDir . '/*.php') as $f) {
                    @unlink($f);
                }
                @rmdir(self::$tmpDir);
            }
        } finally {
            SetUp::$engine = self::$savedEngine;
            SetUp::$migrationsFolder = self::$savedFolder;
            if (self::$pdo) {
                self::staticProperty(Connection::class, 'instance')->setValue(null, self::$savedInstance);
                self::staticProperty(Connection::class, 'parameters')->setValue(null, self::$savedParameters);
                self::staticProperty(CrossEngine::class, 'pdo')->setValue(null, self::$savedCrossPdo);
                self::staticProperty(Querier::class, 'connection')->setValue(null, self::$savedQuerierPdo);
            }
            self::$pdo = null;
            self::$admin = null;
            self::$tmpDir = null;
        }
        parent::tearDown();
    }

    private static function cleanRows(): void
    {
        self::$pdo->exec('DELETE FROM `q_migration`');
    }

    private static function snapshot(): array
    {
        $rows = [];
        foreach (Q_Migration::items()->order_by('id ASC')->all() as $m) {
            $rows[] = [(string)$m->id, (string)$m->name, $m->applied === null ? null : (string)$m->applied];
        }
        return $rows;
    }

    private static function invokeCheckIntergrity(): void
    {
        $m = new \ReflectionMethod(MigrationMaker::class, 'checkIntergrity');
        $m->setAccessible(true);
        $m->invoke(null);
    }

    private static function writeMigrationFile(string $name): void
    {
        file_put_contents(
            self::$tmpDir . '/' . $name . '.php',
            '<?php class ' . $name . ' extends \\Q\\Orm\\Migration\\Migration {}' . "\n"
        );
    }

    public function testAppliedRowWithMissingFileIsPreserved()
    {
        Q_Migration::items()->create(['name' => 'Migration0091', 'applied' => '2026-01-02 03:04:05']);
        $before = self::snapshot();
        $this->assertCount(1, $before);
        self::invokeCheckIntergrity();
        $after = self::snapshot();
        $this->assertSame($before, $after);
        $this->assertSame('2026-01-02 03:04:05', $after[0][2]);
    }

    public function testUnappliedRowWithMissingFileIsPreserved()
    {
        Q_Migration::items()->create(['name' => 'Migration0091', 'applied' => null]);
        $before = self::snapshot();
        self::invokeCheckIntergrity();
        $this->assertSame($before, self::snapshot());
    }

    public function testUnregisteredFileIsInsertedWithoutTouchingExistingRows()
    {
        Q_Migration::items()->create(['name' => 'Migration0090', 'applied' => '2026-01-02 03:04:05']);
        self::writeMigrationFile('Migration0090');
        self::writeMigrationFile('Migration0092');
        $before = self::snapshot();
        self::invokeCheckIntergrity();
        $after = self::snapshot();
        $this->assertCount(2, $after);
        $this->assertSame($before[0], $after[0]);
        $this->assertSame('Migration0092', $after[1][1]);
        $this->assertNull($after[1][2]);
    }

    public function testInSyncFolderIsNoop()
    {
        Q_Migration::items()->create(['name' => 'Migration0094', 'applied' => '2026-01-02 03:04:05']);
        self::writeMigrationFile('Migration0094');
        $before = self::snapshot();
        self::invokeCheckIntergrity();
        $this->assertSame($before, self::snapshot());
    }

    public function testStateBuilderSkipsMissingMigrationClass()
    {
        Q_Migration::items()->create(['name' => SiWidgetMigration::class, 'applied' => '2026-01-02 03:04:05']);
        Q_Migration::items()->create(['name' => 'SiGhostMissing', 'applied' => '2026-01-03 03:04:05']);
        $state = StateBuilder::build();
        $names = array_map(function ($t) {
            return $t->name;
        }, $state);
        $this->assertContains('si_widget', $names);
        $this->assertNotContains('SiGhostMissing', $names);
        $widgets = array_values(array_filter($state, function ($t) {
            return $t->name === 'si_widget';
        }));
        $this->assertCount(1, $widgets);
        $cols = array_map(function ($c) {
            return $c->name;
        }, $widgets[0]->fields);
        $this->assertContains('label', $cols);
    }

    public function testStateBuilderWithOnlyMissingMigrationsReturnsEmpty()
    {
        Q_Migration::items()->create(['name' => 'SiGhostMissing', 'applied' => '2026-01-03 03:04:05']);
        $this->assertSame([], StateBuilder::build());
    }

    public function testNextMigrationIdReservesRegisteredNumbers()
    {
        Q_Migration::items()->create(['name' => 'Migration0007', 'applied' => '2026-01-02 03:04:05']);
        $this->assertSame(8, MigrationGenerator::nextMigrationId());
    }

    public function testNextMigrationIdTakesMaxOfFilesAndRows()
    {
        Q_Migration::items()->create(['name' => 'Migration0003', 'applied' => '2026-01-02 03:04:05']);
        self::writeMigrationFile('Migration0005');
        $this->assertSame(6, MigrationGenerator::nextMigrationId());
    }

    public function testNextMigrationIdUnchangedWhenInSync()
    {
        Q_Migration::items()->create(['name' => 'Migration0001', 'applied' => '2026-01-02 03:04:05']);
        self::writeMigrationFile('Migration0001');
        $this->assertSame(2, MigrationGenerator::nextMigrationId());
    }
}
