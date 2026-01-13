<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

class AggregateTest extends QormTestCase
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
            ['name' => 'A', 'email' => 'a@test.com', 'salary' => 10],
            ['name' => 'B', 'email' => 'b@test.com', 'salary' => 20],
            ['name' => 'C', 'email' => 'c@test.com', 'salary' => 30]
        );
    }

    public function testAggregateMethod()
    {
        // Explicitly use aggregate() method which primes the handler
        $handler = GroupingUser::items()->aggregate('SUM', 'salary');
        $query = $handler->buildAggregateQuery();

        // buildAggregateQuery returns [query, placeholders]
        $this->assertStringContainsString('SUM(', $query[0]);
        $this->assertStringContainsString('"salary")', $query[0]);
        $this->assertEmpty($query[1]);

        // Execute via count() or one() logic? 
        // Typically aggregate() is used by count(), max(), etc internally.
        // But we can inspect the primed state.

        $result = $handler->one(); // Should return the singular result?
        // No, Handler::one() executes normal SELECT. aggregate() sets primed variables.
        // Handler::one() calls queryOne(). queryOne uses map/makeRelations.

        // The aggregate values are usually fetched via Handler logic that detects primed state?
        // Let's check Handler::count() implementation.
        // It calls buildAndExecuteAggregateQuery.
    }

    public function testAggregateHelpers()
    {
        $sum = GroupingUser::items()->sum('salary');
        $this->assertEquals(60, $sum);

        $avg = GroupingUser::items()->avg('salary');
        $this->assertEquals(20, $avg);

        $max = GroupingUser::items()->max('salary');
        $this->assertEquals(30, $max);

        $min = GroupingUser::items()->min('salary');
        $this->assertEquals(10, $min);

        $count = GroupingUser::items()->count('id');
        $this->assertEquals(3, $count);
    }

    public function testAggregateWithFilter()
    {
        $sum = GroupingUser::items()->filter(['salary.gt' => 15])->sum('salary');
        $this->assertEquals(50, $sum); // 20 + 30
    }
}
