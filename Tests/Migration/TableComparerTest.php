<?php

namespace Tests\Migration;

use PHPUnit\Framework\TestCase;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\StateBuilder;
use Q\Orm\Migration\Table;
use Q\Orm\Migration\TableComparer;
use Q\Orm\SetUp;

class TableComparerTest extends TestCase
{
    private $previousEngine;

    protected function setUp(): void
    {
        $this->previousEngine = SetUp::$engine;
        SetUp::$engine = SetUp::SQLITE;
    }

    protected function tearDown(): void
    {
        SetUp::$engine = $this->previousEngine;
    }

    private static function callPrivate(string $method, array $args)
    {
        $m = new \ReflectionMethod(TableComparer::class, $method);
        $m->setAccessible(true);
        return $m->invokeArgs(null, $args);
    }

    private static function col(string $name, string $type = 'varchar', array $def = []): Column
    {
        return new Column($name, $type, $def);
    }

    public function testIdenticalEnumIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('e', 'enum', ['size' => ['a', 'b'], 'null' => false])]);
        $schema = new Table('t', [self::col('e', 'enum', ['size' => ['a', 'b'], 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testNarrowedEnumIsDetected()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('e', 'enum', ['size' => ['a', 'b'], 'null' => false])]);
        $schema = new Table('t', [self::col('e', 'enum', ['size' => ['a', 'b', 'c'], 'null' => false])]);
        $out = self::callPrivate('columnsToModify', [[$schema], [$model]]);
        $this->assertCount(1, $out);
        $this->assertSame('t', $out[0]['table']);
        $this->assertSame(['a', 'b'], $out[0]['column']->size);
        $this->assertSame(['a', 'b', 'c'], $out[0]['previouscolumn']->size);
    }

    public function testIdDifferencesAreIgnored()
    {
        // The id guard skips genuine differences: the predicate flags this
        // pair (bigint vs varchar) and only the name guard silences it.
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('id', 'bigint', ['size' => 20]), self::col('n', 'varchar')]);
        $schema = new Table('t', [self::col('id', 'varchar', ['size' => 99]), self::col('n', 'varchar')]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testBooleanFalseMatchesStringZero()
    {
        $model = new Table('t', [self::col('a', 'boolean', ['default' => false, 'null' => false])]);
        $schema = new Table('t', [self::col('a', 'boolean', ['default' => '0', 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlComparesSizes()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('v', 'varchar', ['size' => 255])]);
        $schema = new Table('t', [self::col('v', 'varchar', ['size' => 100])]);
        $out = self::callPrivate('columnsToModify', [[$schema], [$model]]);
        $this->assertCount(1, $out);
    }

    public function testIsColumnScheduledForAdd()
    {
        $add = [
            ['table' => 'a', 'column' => self::col('x')],
        ];
        $this->assertTrue(self::callPrivate('isColumnScheduledForAdd', [$add, 'a', 'x']));
        $this->assertFalse(self::callPrivate('isColumnScheduledForAdd', [$add, 'b', 'x']));
        $this->assertFalse(self::callPrivate('isColumnScheduledForAdd', [$add, 'a', 'y']));
        $this->assertFalse(self::callPrivate('isColumnScheduledForAdd', [[], 'a', 'x']));
    }

    public function testIndexTypeChangeChurnsForeignKey()
    {
        $schema = new Table(
            't',
            [self::col('user', 'bigint')],
            [new Index('user', Index::INDEX)],
            [new ForeignKey('user', 'user', 'id', ForeignKey::RESTRICT)]
        );
        $model = new Table(
            't',
            [self::col('user', 'bigint')],
            [new Index('user', Index::UNIQUE)],
            [new ForeignKey('user', 'user', 'id', ForeignKey::RESTRICT)]
        );
        $drops = self::callPrivate('fksToDrop', [[$schema], [$model]]);
        $this->assertCount(1, $drops);
        $this->assertSame('user', $drops[0]['foreignKey']->field);
        $state = StateBuilder::dropForeignKeys([$schema], $drops);
        $idxDrops = self::callPrivate('indexesToDrop', [$state, [$model]]);
        $state = StateBuilder::dropIndexes($state, $idxDrops);
        $adds = self::callPrivate('fksToAdd', [$state, [$model]]);
        $this->assertCount(1, $adds);
        $this->assertSame('user', $adds[0]['foreignKey']->field);
    }

    public function testMysqlWidthOmissionIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'bigint', ['null' => false])]);
        $schema = new Table('t', [self::col('c', 'bigint', ['size' => '20', 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlIntVsOmittedWidthIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'bigint', ['size' => 20, 'null' => false])]);
        $schema = new Table('t', [self::col('c', 'bigint', ['size' => '', 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlWidthPresenceIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'bigint', ['size' => 20, 'null' => false])]);
        $schema = new Table('t', [self::col('c', 'bigint', ['size' => '20', 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlDecimalPairIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'decimal', ['size' => [12, 3], 'null' => false])]);
        $schema = new Table('t', [self::col('c', 'decimal', ['size' => '12,3', 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
        $model2 = new Table('t', [self::col('c', 'decimal', ['size' => '12,3', 'null' => false])]);
        $schema2 = new Table('t', [self::col('c', 'decimal', ['size' => [12, 3], 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema2], [$model2]]));
    }

    public function testMysqlDecimalPrecisionChangeFires()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'decimal', ['size' => [12, 3], 'null' => false])]);
        $schema = new Table('t', [self::col('c', 'decimal', ['size' => '12,2', 'null' => false])]);
        $this->assertCount(1, self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlBooleanParsedShapeIsSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'boolean', ['null' => false])]);
        $schema = new Table('t', [self::col('c', 'boolean', ['null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlUnsignedNullMatchesFalse()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'bigint', ['null' => false])]);
        $schema = new Table('t', [self::col('c', 'bigint', ['unsigned' => false, 'null' => false])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlDefaultRoundTripsAreSilent()
    {
        SetUp::$engine = SetUp::MYSQL;
        $pairs = [
            [0, '0', 'bigint'],
            [0.0, '0', 'float'],
            ['t', 't', 'varchar'],
        ];
        foreach ($pairs as [$modelDefault, $liveDefault, $type]) {
            $model = new Table('t', [self::col('c', $type, ['default' => $modelDefault, 'null' => false])]);
            $schema = new Table('t', [self::col('c', $type, ['default' => $liveDefault, 'null' => false])]);
            $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
        }
    }

    public function testMysqlNullSizeMatchesEmpty()
    {
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('c', 'text', ['null' => true])]);
        $schema = new Table('t', [self::col('c', 'text', ['size' => '', 'null' => true])]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
    }

    public function testMysqlRealDifferencesStillFire()
    {
        SetUp::$engine = SetUp::MYSQL;
        $cases = [
            [self::col('c', 'varchar', ['null' => false]), self::col('c', 'text', ['null' => false])],
            [self::col('c', 'varchar', ['null' => false]), self::col('c', 'varchar', ['null' => true])],
            [self::col('c', 'varchar', ['default' => 'a', 'null' => false]), self::col('c', 'varchar', ['default' => 'b', 'null' => false])],
            [self::col('c', 'bigint', ['unsigned' => true, 'null' => false]), self::col('c', 'bigint', ['unsigned' => false, 'null' => false])],
            [self::col('c', 'bigint', ['auto_increment' => false, 'null' => false]), self::col('c', 'bigint', ['auto_increment' => true, 'null' => false])],
        ];
        foreach ($cases as [$modelCol, $schemaCol]) {
            $out = self::callPrivate('columnsToModify', [[new Table('t', [$schemaCol])], [new Table('t', [$modelCol])]]);
            $this->assertCount(1, $out);
        }
    }

    public function testSqliteIgnoresSizesWithoutMutating()
    {
        // The non-matching first column forces a second inner iteration,
        // which is where the old save/restore captured nulled values.
        SetUp::$engine = SetUp::SQLITE;
        $my = self::col('y', 'varchar', ['size' => 255, 'null' => false]);
        $sy = self::col('y', 'varchar', ['size' => 100, 'null' => false]);
        $model = new Table('t', [self::col('x', 'varchar', ['null' => false]), $my]);
        $schema = new Table('t', [self::col('x', 'varchar', ['null' => false]), $sy]);
        $this->assertSame([], self::callPrivate('columnsToModify', [[$schema], [$model]]));
        $this->assertSame(255, $my->size);
        $this->assertSame(100, $sy->size);
    }

    public function testSqliteEmittedColumnsKeepSizes()
    {
        SetUp::$engine = SetUp::SQLITE;
        $my = self::col('y', 'varchar', ['size' => 255, 'null' => false]);
        $sy = self::col('y', 'text', ['size' => 100, 'null' => false]);
        $model = new Table('t', [self::col('x', 'varchar', ['null' => false]), $my]);
        $schema = new Table('t', [self::col('x', 'varchar', ['null' => false]), $sy]);
        $out = self::callPrivate('columnsToModify', [[$schema], [$model]]);
        $this->assertCount(1, $out);
        $this->assertSame(255, $out[0]['column']->size);
        $this->assertSame(100, $out[0]['previouscolumn']->size);
        $this->assertSame(255, $my->size);
        $this->assertSame(100, $sy->size);
    }
}
