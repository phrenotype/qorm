<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

/**
 * Tests for Grouping and Having clauses.
 */
class GroupingTest extends QormTestCase
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
        $pdo = Connection::getInstance();
        $pdo->exec("DELETE FROM grouping_user");

        // Seed data for grouping
        // active: Alice(50k), Bob(60k) -> count=2, avg=55k
        // inactive: Charlie(70k) -> count=1, avg=70k
        // banned: Diana(80k), Eve(90k), Frank(100k) -> count=3, avg=90k
        GroupingUser::items()->create(
            ['name' => 'Alice', 'email' => 'a@t.com', 'salary' => 50000, 'status' => 'active'],
            ['name' => 'Bob', 'email' => 'b@t.com', 'salary' => 60000, 'status' => 'active'],
            ['name' => 'Charlie', 'email' => 'c@t.com', 'salary' => 70000, 'status' => 'inactive'],
            ['name' => 'Diana', 'email' => 'd@t.com', 'salary' => 80000, 'status' => 'banned'],
            ['name' => 'Eve', 'email' => 'e@t.com', 'salary' => 90000, 'status' => 'banned'],
            ['name' => 'Frank', 'email' => 'f@t.com', 'salary' => 100000, 'status' => 'banned']
        );
    }

    public function testGroupBy()
    {
        // Group by status, count(*), avg(salary)
        // Must project fields to use group_by
        $rows = GroupingUser::items()
            ->project('status', 'count(id) AS count', 'avg(salary) AS avg_salary')
            ->group_by('status')
            ->order_by('status ASC')
            ->array();

        $this->assertCount(3, $rows);

        // Active
        $this->assertEquals('active', $rows[0]->status);
        $this->assertEquals(2, $rows[0]->count);
        $this->assertEquals(55000, $rows[0]->avg_salary);

        // Banned
        $this->assertEquals('banned', $rows[1]->status);
        $this->assertEquals(3, $rows[1]->count);
        $this->assertEquals(90000, $rows[1]->avg_salary);

        // Inactive
        $this->assertEquals('inactive', $rows[2]->status);
        $this->assertEquals(1, $rows[2]->count);
        $this->assertEquals(70000, $rows[2]->avg_salary);
    }

    public function testHaving()
    {
        // Group by status, keep only where count > 1 (active, banned)
        $rows = GroupingUser::items()
            ->project('status', 'count(id) AS user_count')
            ->group_by('status')
            ->having(['user_count.gt' => 1])
            ->order_by('status ASC')
            ->array();

        $this->assertCount(2, $rows);
        $this->assertEquals('active', $rows[0]->status);
        $this->assertEquals('banned', $rows[1]->status);
    }

    public function testHavingWithAggregateFilter()
    {
        // Group by status, keep where avg(salary) >= 90000 (banned)
        $rows = GroupingUser::items()
            ->project('status', 'avg(salary) AS avg_salary')
            ->group_by('status')
            ->having(['avg_salary.gte' => 90000])
            ->array();

        $this->assertCount(1, $rows);
        $this->assertEquals('banned', $rows[0]->status);
    }

    public function testHavingStatus()
    {
        // Control test: Filter on GROUP BY column
        $rows = GroupingUser::items()
            ->project('status', 'count(id) AS user_count')
            ->group_by('status')
            ->having(['status.eq' => 'active'])
            ->array();

        $this->assertCount(1, $rows);
        $this->assertEquals('active', $rows[0]->status);
    }

    public function testGroupByWithoutProjectThrowsError()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Cannot call 'group by' without projecting fields");

        GroupingUser::items()->group_by('status');
    }

    public function testHavingWithoutGroupByThrowsError()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("'group_by' must be called before 'having'");

        GroupingUser::items()->having(['count(id).gt' => 1]);
    }
}
