<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;
use Q\Orm\Aggregate;

class SubqueryTest extends QormTestCase
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
        // Seed data
        GroupingUser::items()->create(
            ['name' => 'A', 'email' => 'a@t.com', 'salary' => 10], // Below avg
            ['name' => 'B', 'email' => 'b@t.com', 'salary' => 20], // Avg
            ['name' => 'C', 'email' => 'c@t.com', 'salary' => 30], // Above avg
            ['name' => 'D', 'email' => 'd@t.com', 'salary' => 40]  // Above avg
        );
    }

    public function testSubqueryScalarComparison()
    {
        // Find users with salary > AVG(salary)
        // Average is (10+20+30+40)/4 = 25
        
        $avg = GroupingUser::items()->aggregate(Aggregate::AVG, 'salary');
        
        $users = GroupingUser::items()
            ->filter(['salary.gt' => $avg])
            ->order_by('salary ASC')
            ->array();

        $this->assertCount(2, $users);
        $this->assertEquals('C', $users[0]->name);
        $this->assertEquals('D', $users[1]->name);
    }

    public function testSubqueryIn()
    {
        // Find users IN a subquery of names
        $namesSubquery = GroupingUser::items()
            ->project('name')
            ->filter(['salary.gt' => 25]); // Should be C, D

        $users = GroupingUser::items()
            ->filter(['name.in' => $namesSubquery])
            ->array();

        $this->assertCount(2, $users);
    }

    public function testSubqueryExists()
    {
        // Find users where an entry with salary > 100 exists (none)
        $sub = GroupingUser::items()->filter(['salary.gt' => 100]);
        $users = GroupingUser::items()->filter(['.exists' => $sub])->array();
        $this->assertCount(0, $users);

        // Find users where an entry with salary > 5 exists (all)
        // In SQL: SELECT * FROM user WHERE EXISTS (SELECT * FROM user WHERE salary > 5)
        // Since the subquery returns rows, the WHERE EXISTS is true for every row in the outer query
        $sub2 = GroupingUser::items()->filter(['salary.gt' => 5]);
        $users2 = GroupingUser::items()->filter(['.exists' => $sub2])->array();
        $this->assertCount(4, $users2);
    }
}
