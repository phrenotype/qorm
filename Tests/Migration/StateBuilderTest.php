<?php

namespace Tests\Migration;

use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\Migration;
use Q\Orm\Migration\Schema;
use Q\Orm\Migration\StateBuilder;
use Q\Orm\Migration\Table;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Migration\Models\Q_Migration;
use Tests\QormTestCase;

class FixtureChangeColumnMigration extends Migration
{
    public function __construct()
    {
        $this->operations = [
            function () {
                return Schema::changeColumn('t', 'oldcol', function (Column $c) {
                    $c->name = 'newcol';
                    $c->type = Field::CHAR;
                    $c->size = 100;
                    $c->null = false;
                });
            },
        ];
        $this->reverse = [];
    }
}

class FixtureModifyColumnMigration extends Migration
{
    public function __construct()
    {
        $this->operations = [
            function () {
                return Schema::modifyColumn('t', function (Column $c) {
                    $c->name = 'c';
                    $c->type = Field::TEXT;
                    $c->null = true;
                });
            },
        ];
        $this->reverse = [];
    }
}

class FixtureAddDropColumnMigration extends Migration
{
    public function __construct()
    {
        $this->operations = [
            function () {
                return Schema::addColumn('t', function (Column $c) {
                    $c->name = 'added';
                    $c->type = Field::CHAR;
                    $c->size = 50;
                    $c->null = true;
                });
            },
            function () {
                return Schema::dropColumn('t', 'gone');
            },
        ];
        $this->reverse = [];
    }
}

class StateBuilderTest extends QormTestCase
{
    private static function replay(string $migration, array $prevState): array
    {
        $m = new \ReflectionMethod(StateBuilder::class, 'operationsToTables');
        $m->setAccessible(true);
        return $m->invokeArgs(null, [$migration, $prevState]);
    }

    private static function fieldNames(Table $table): array
    {
        return array_values(array_map(function (Column $c) {
            return $c->name;
        }, $table->fields));
    }

    public function testDropTablesRemovesTableObjects()
    {
        $out = StateBuilder::dropTables([new Table('a'), new Table('b')], [new Table('b')]);
        $names = array_values(array_map(function (Table $t) {
            return $t->name;
        }, $out));
        $this->assertSame(['a'], $names);
    }

    public function testDropTablesAcceptsBareNames()
    {
        $out = StateBuilder::dropTables([new Table('a'), new Table('b')], ['b']);
        $names = array_values(array_map(function (Table $t) {
            return $t->name;
        }, $out));
        $this->assertSame(['a'], $names);
    }

    public function testDropColumnsRemovesColumnObjects()
    {
        $table = new Table('t', [new Column('x', 'varchar'), new Column('y', 'varchar')]);
        $out = StateBuilder::dropColumns([$table], [['table' => 't', 'column' => new Column('y', 'varchar')]]);
        $this->assertSame(['x'], self::fieldNames($out[0]));
    }

    public function testDropColumnsAcceptsBareNames()
    {
        $table = new Table('t', [new Column('x', 'varchar'), new Column('y', 'varchar')]);
        $out = StateBuilder::dropColumns([$table], [['table' => 't', 'column' => 'y']]);
        $this->assertSame(['x'], self::fieldNames($out[0]));
    }

    public function testChangeColumnReplayAppliesRename()
    {
        $table = new Table('t', [new Column('oldcol', 'varchar'), new Column('keep', 'varchar')]);
        $out = self::replay(FixtureChangeColumnMigration::class, [$table]);
        $this->assertSame(['newcol', 'keep'], self::fieldNames($out[0]));
        $new = TableModelFinder::findTableColumn($out[0], function ($t, $c) {
            return $c->name === 'newcol';
        });
        $this->assertNotNull($new);
        $this->assertSame('varchar', $new->type);
        $this->assertFalse($new->null);
    }

    public function testModifyColumnReplayReplacesShapeButPreservesName()
    {
        $table = new Table('t', [new Column('c', 'varchar', ['size' => 50, 'null' => false])]);
        $out = self::replay(FixtureModifyColumnMigration::class, [$table]);
        $cols = array_values($out[0]->fields);
        $this->assertCount(1, $cols);
        $this->assertSame('c', $cols[0]->name);
        $this->assertSame('text', $cols[0]->type);
        $this->assertTrue($cols[0]->null);
    }

    public function testAddAndDropColumnReplay()
    {
        $table = new Table('t', [new Column('gone', 'varchar')]);
        $out = self::replay(FixtureAddDropColumnMigration::class, [$table]);
        $this->assertSame(['added'], self::fieldNames($out[0]));
    }

    public function testBuildReplaysRegisteredMigrations()
    {
        $state = StateBuilder::build();
        $names = array_map(function (Table $t) {
            return $t->name;
        }, $state);
        sort($names);
        $this->assertSame(['address', 'comment', 'post', 'user'], $names);

        $user = null;
        foreach ($state as $t) {
            if ($t->name === 'user') {
                $user = $t;
            }
        }
        $this->assertNotNull($user);
        $this->assertContains('email', self::fieldNames($user));
        $emailIdx = TableModelFinder::findTableIndex($user, function ($t, $i) {
            return $i->field === 'email';
        });
        $this->assertNotNull($emailIdx);
        $this->assertSame(Index::UNIQUE, $emailIdx->type);
        $sponsorFk = TableModelFinder::findTableFk($user, function ($t, $fk) {
            return $fk->field === 'sponsor';
        });
        $this->assertNotNull($sponsorFk);
        $this->assertSame('user', $sponsorFk->refTable);
        $this->assertSame('id', $sponsorFk->refField);
    }

    public function testBuildIgnoresAppliedState()
    {
        Q_Migration::items()->update(['applied' => null]);
        $state = StateBuilder::build();
        $names = array_map(function (Table $t) {
            return $t->name;
        }, $state);
        sort($names);
        $this->assertSame(['address', 'comment', 'post', 'user'], $names);
    }
}
