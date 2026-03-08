<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

/**
 * Test to verify the aggregate caching bug is fixed.
 * The bug was: calling count() on a handler prevented subsequent
 * aggregate calls (sum, avg, min, max) from working on the same handler.
 */
class AggregateBugFixTest extends QormTestCase
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

    /**
     * The core bug: count() then sum() on same handler
     */
    public function testCountThenSumOnSameHandler()
    {
        $handler = GroupingUser::items();
        
        // This used to set $__count__ and break subsequent aggregates
        $count = $handler->count();
        $this->assertEquals(3, $count);
        
        // This would throw TypeError before the fix
        $sum = $handler->sum('salary');
        $this->assertEquals(60, $sum);
    }

    /**
     * Test all aggregate sequences work
     */
    public function testAllAggregateSequences()
    {
        $aggregates = ['count', 'sum', 'avg', 'min', 'max'];
        
        foreach ($aggregates as $first) {
            foreach ($aggregates as $second) {
                $handler = GroupingUser::items();
                
                // Call first aggregate
                $result1 = $first === 'count' ? $handler->count() : $handler->$first('salary');
                $this->assertNotNull($result1, "Failed on first call: $first");
                
                // Call second aggregate on same handler - should work
                $result2 = $second === 'count' ? $handler->count() : $handler->$second('salary');
                $this->assertNotNull($result2, "Failed on second call: $second after $first");
            }
        }
    }

    /**
     * Test that aggregates execute fresh each time (no caching)
     */
    public function testAggregatesExecuteFreshEachTime()
    {
        $handler = GroupingUser::items();
        
        // First count
        $count1 = $handler->count();
        $this->assertEquals(3, $count1);
        
        // Add a new record
        GroupingUser::items()->create(['name' => 'D', 'email' => 'd@test.com', 'salary' => 40]);
        
        // Second count should reflect new record (no caching)
        $count2 = $handler->count();
        $this->assertEquals(4, $count2, "Count should reflect new record (no caching)");
    }

    /**
     * Test that aggregate() method still works for subqueries
     */
    public function testAggregateMethodForSubquery()
    {
        $handler = GroupingUser::items();
        
        // aggregate() should still prime and return handler
        $primed = $handler->aggregate('SUM', 'salary');
        $this->assertInstanceOf(\Q\Orm\Handler::class, $primed);
        
        // buildAggregateQuery() should work
        $query = $handler->buildAggregateQuery();
        $this->assertIsArray($query);
        $this->assertCount(2, $query);
        $this->assertStringContainsString('SUM(', $query[0]);
    }
}
