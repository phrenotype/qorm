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
        // MYSQL engine: sizes are compared, so this genuinely covers the id
        // guard (under SQLITE the sizes would be nulled before compare).
        SetUp::$engine = SetUp::MYSQL;
        $model = new Table('t', [self::col('id', 'bigint', ['size' => 20]), self::col('n', 'varchar')]);
        $schema = new Table('t', [self::col('id', 'bigint', ['size' => 99]), self::col('n', 'varchar')]);
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
}
