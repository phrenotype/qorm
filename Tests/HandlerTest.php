<?php

namespace Tests;

use Tests\Models\User;
use Q\Orm\Connection;
use Q\Orm\Handler;

/**
 * Tests for Handler methods (chaining, aggregation, projection, paging).
 */
class HandlerTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS user");
        $pdo->exec("CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            salary INTEGER DEFAULT 0,
            sponsor_id INTEGER NULL,
            created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Connection::getInstance();
        $pdo->exec("DELETE FROM user");

        // Seed test data with varied salaries for aggregation tests
        User::items()->create(
            ['name' => 'Alice', 'email' => 'alice@test.com', 'salary' => 50000],
            ['name' => 'Bob', 'email' => 'bob@test.com', 'salary' => 60000],
            ['name' => 'Charlie', 'email' => 'charlie@test.com', 'salary' => 70000],
            ['name' => 'Diana', 'email' => 'diana@test.com', 'salary' => 80000],
            ['name' => 'Eve', 'email' => 'eve@test.com', 'salary' => 90000]
        );
    }

    // =========================================================================
    // CHAINING & BASICS
    // =========================================================================

    public function testHandlerChaining()
    {
        $handler = User::items();
        $this->assertInstanceOf(Handler::class, $handler);

        $chained = $handler->filter(['salary.gt' => 0])->order_by('name ASC')->limit(5);
        $this->assertInstanceOf(Handler::class, $chained);
        $this->assertSame($handler, $chained, "Handler methods should return the same instance (fluent interface)");
    }

    public function testExists()
    {
        $this->assertTrue(User::items()->filter(['name' => 'Alice'])->exists());
        $this->assertFalse(User::items()->filter(['name' => 'Zorro'])->exists());
    }

    // =========================================================================
    // AGGREGATION
    // =========================================================================

    public function testCount()
    {
        $this->assertEquals(5, User::items()->count());
        $this->assertEquals(2, User::items()->filter(['salary.gte' => 80000])->count());
    }

    public function testMax()
    {
        $max = User::items()->max('salary');
        $this->assertEquals(90000, $max);
    }

    public function testMin()
    {
        $min = User::items()->min('salary');
        $this->assertEquals(50000, $min);
    }

    public function testSum()
    {
        $sum = User::items()->sum('salary');
        // 50 + 60 + 70 + 80 + 90 = 350
        $this->assertEquals(350000, $sum);
    }

    public function testAvg()
    {
        $avg = User::items()->avg('salary');
        // 350000 / 5 = 70000
        $this->assertEquals(70000, $avg);
    }

    // =========================================================================
    // PAGINATION & LIMITS
    // =========================================================================

    public function testLimit()
    {
        $users = User::items()->limit(2)->order_by('salary ASC')->array();
        $this->assertCount(2, $users);
        $this->assertEquals('Alice', $users[0]->name);
        $this->assertEquals('Bob', $users[1]->name);
    }

    public function testLimitOffset()
    {
        // Skip 2, take 2 (Charlie, Diana)
        $users = User::items()->limit(2, 2)->order_by('salary ASC')->array();

        $this->assertCount(2, $users);
        $this->assertEquals('Charlie', $users[0]->name);
        $this->assertEquals('Diana', $users[1]->name);
    }

    public function testPage()
    {
        // Page 1, 2 items per page -> Alice, Bob
        $page1 = User::items()->order_by('salary ASC')->page(1, 2)->array();
        $this->assertCount(2, $page1);
        $this->assertEquals('Alice', $page1[0]->name);
        $this->assertEquals('Bob', $page1[1]->name);

        // Page 2, 2 items per page -> Charlie, Diana
        $page2 = User::items()->order_by('salary ASC')->page(2, 2)->array();
        $this->assertCount(2, $page2);
        $this->assertEquals('Charlie', $page2[0]->name);
        $this->assertEquals('Diana', $page2[1]->name);

        // Page 3, 2 items per page -> Eve
        $page3 = User::items()->order_by('salary ASC')->page(3, 2)->array();
        $this->assertCount(1, $page3);
        $this->assertEquals('Eve', $page3[0]->name);
    }

    // =========================================================================
    // SORTING
    // =========================================================================

    public function testOrderByAsc()
    {
        $users = User::items()->order_by('salary ASC')->array();
        $this->assertEquals('Alice', $users[0]->name);
        $this->assertEquals('Eve', $users[4]->name);
    }

    public function testOrderByDesc()
    {
        $users = User::items()->order_by('salary DESC')->array();
        $this->assertEquals('Eve', $users[0]->name);
        $this->assertEquals('Alice', $users[4]->name);
    }

    // =========================================================================
    // PROJECTION
    // =========================================================================

    public function testProject()
    {
        // Project only name and salary
        $user = User::items()->project('name', 'salary')->filter(['name' => 'Alice'])->one();



        $this->assertNotNull($user);
        $this->assertEquals('Alice', $user->name);
        $this->assertEquals(50000, $user->salary);

        // Email should NOT be populated (or default/null?)
        // In QORM, unprojected fields might be null or unset
        $this->assertNull($user->email ?? null);
    }
}
