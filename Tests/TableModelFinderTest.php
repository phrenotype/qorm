<?php

use PHPUnit\Framework\TestCase;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\TableModelFinder;
use Tests\Models\Post;
use Tests\Models\User;

class TableModelFinderTest extends TestCase
{

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function testFindPk()
    {
        $r = TableModelFinder::findModelPk(User::class);
        $this->assertEquals($r, 'id');
    }

    public function testFindModelColumnName()
    {
        $r1 = TableModelFinder::findModelColumnName(Post::class, 'title');
        $r2 = TableModelFinder::findModelColumnName(Post::class, 'realtitle');

        $this->assertEquals($r1, $r2);
    }

    public function testFindModelColumn()
    {
        $c1 = TableModelFinder::findModelColumn(User::class, function ($fieldName, $fieldObject) {
            return $fieldName === 'id';
        });

        $c2 = TableModelFinder::findModelColumn(User::class, function ($fieldName, $fieldObject) {
            return $fieldName === 'email';
        });


        $this->assertIsNotObject($c1);
        $this->assertIsObject($c2);
    }
}
