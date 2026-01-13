<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

class SetOperationsTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS grouping_user");
        \Tests\Helpers\TestUtil::createTableFromModel(GroupingUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM grouping_user");
        GroupingUser::items()->create(
            ['name' => 'A', 'salary' => 10, 'email' => 'a@t.com'],
            ['name' => 'B', 'salary' => 20, 'email' => 'b@t.com'],
            ['name' => 'C', 'salary' => 30, 'email' => 'c@t.com']
        );
    }

    public function testUnion()
    {
        // Union of salary > 10 and salary < 30 should be A, B, C (all unique rows from both sets)
        // Set 1: > 10 => B, C
        // Set 2: < 30 => A, B
        // Union: A, B, C

        $h1 = GroupingUser::items()->as('u1')->project('name')->filter(['salary.gt' => 10]);
        $h2 = GroupingUser::items()->as('u2')->project('name')->filter(['salary.lt' => 30]);

        // QORM's union returns a Generator or array?
        // Method signature usually returns Handler which then needs ->all() or ->array()
        // Or based on previous viewing, union/except/intersect might execute immediately or return a result set wrapper.
        // Assuming standard Handler behavior: $h1->union($h2)->all()

        $results = iterator_to_array($h1->union($h2)->all());

        // Sorting by ID to assert content, though UNION ordering isn't guaranteed without ORDER BY
        usort($results, function ($a, $b) {
            return strcmp($a->name, $b->name);
        });

        $this->assertCount(3, $results);
        $this->assertEquals('A', $results[0]->name);
        $this->assertEquals('B', $results[1]->name);
        $this->assertEquals('C', $results[2]->name);
    }

    public function testIntersect()
    {
        // Intersect of > 10 and < 30 => Only B fits both (20)

        $h1 = GroupingUser::items()->as('u1')->project('name')->filter(['salary.gt' => 10]);
        $h2 = GroupingUser::items()->as('u2')->project('name')->filter(['salary.lt' => 30]);

        $results = iterator_to_array($h1->intersect($h2)->all());

        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]->name);
    }

    public function testExcept()
    {
        // Except (Minus): > 10 (B, C) EXCEPT < 30 (A, B)
        // Result: C (Since B is in the second set, it's removed. A is not in first set.)

        $h1 = GroupingUser::items()->as('u1')->project('name')->filter(['salary.gt' => 10]);
        $h2 = GroupingUser::items()->as('u2')->project('name')->filter(['salary.lt' => 30]);

        $results = iterator_to_array($h1->except($h2)->all());

        $this->assertCount(1, $results);
        $this->assertEquals('C', $results[0]->name);
    }
}
