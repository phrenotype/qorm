<?php

namespace Tests;

use Q\Orm\Helpers;
use Q\Orm\SetUp;
use Tests\Models\Comment;
use Tests\Models\User;

class HelpersTest extends QormTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
    }

    public function testIsModelEmpty()
    {
        $m1 = new User();
        $m1->id = 5;

        $m2 = new User();

        $m3 = new User();
        $m3->name = "qorm";

        $r1 = Helpers::isModelEmpty($m1);
        $r2 = Helpers::isModelEmpty($m2);
        $r3 = Helpers::isModelEmpty($m3);

        $this->assertEquals($r1, false);
        $this->assertEquals($r2, true);
        $this->assertEquals($r3, false);
    }

    public function testGetModelRefFields()
    {
        $r1 = Helpers::getModelRefFields(User::class);
        $r2 = Helpers::getModelRefFields(Comment::class);

        $this->assertEquals(count($r1), 1);
        $this->assertEquals(count($r2), 2);
    }

    public function testIsRefField()
    {
        $r1 = Helpers::isRefField('user_id', Comment::class);
        $r2 = Helpers::isRefField('user', Comment::class);
        $r3 = Helpers::isRefField('text', Comment::class);

        $this->assertEquals($r1, true);
        $this->assertEquals($r2, true);
        $this->assertEquals($r3, false);
    }

    public function testRemove()
    {
        $r1 = Helpers::remove('a', ['a', 'b', 'c']);
        $r2 = Helpers::remove('d', ['a', 'b', 'c']);

        $this->assertEquals($r1, ['b', 'c']);
        $this->assertEquals($r2, ['a', 'b', 'c']);
    }

    public function testTicks()
    {
        $r1 = Helpers::ticks("example");
        $r2 = Helpers::ticks('`example`');
        $r3 = Helpers::ticks('"example"');

        $bool = (bool) preg_match('/(`|")example\1/', $r1);

        $this->assertEquals($bool, true);
        $this->assertEquals($r2, "`example`");
        $this->assertEquals($r3, '"example"');
    }

    public function testGetModelProperties()
    {
        $r1 = Helpers::getModelProperties(User::class);
        $r2 = Helpers::getModelProperties(Comment::class);

        $this->assertEquals(count($r1), 5);
        $this->assertEquals(count($r2), 3);
    }

    public function testGetModelColumns()
    {
        $r1 = Helpers::getModelProperties(User::class);
        $r2 = Helpers::getModelColumns(User::class);

        $this->assertEquals(count($r1), count($r2));
    }

    public function testGetDeclaredModels()
    {
        $models = Helpers::getDeclaredModels();
        $this->assertEquals(10, count($models));
    }

    public function testModelNameToTableName()
    {
        $r1 = Helpers::modelNameToTableName('ThriftUser');
        $r2 = Helpers::modelNameToTableName('_Thrift_99');
        $r3 = Helpers::modelNameToTableName('Thrift_99_');
        $r4 = Helpers::modelNameToTableName('Q_Migration');

        $this->assertEquals($r1, 'thrift_user');
        $this->assertEquals($r2, 'thrift_99');
        $this->assertEquals($r3, 'thrift_99');
        $this->assertEquals($r4, 'q_migration');
    }

    public function testTableNameToModelName()
    {
        $r1 = Helpers::tableNameToModelName('thrift_user');
        $r2 = Helpers::tableNameToModelName('_thrift_user_');
        $r3 = Helpers::tableNameToModelName('user_home_address');

        $this->assertEquals($r1, 'ThriftUser');
        $this->assertEquals($r2, 'ThriftUser');
        $this->assertEquals($r3, 'UserHomeAddress');
    }
}
