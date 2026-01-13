<?php

namespace Tests;

use Tests\Models\User;
use Q\Orm\Connection;

/**
 * Tests for Filter operators and conjunctions.
 */
class FilterTest extends QormTestCase
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

        // Seed test data
        User::items()->create(
            ['name' => 'Alice', 'email' => 'alice@example.com', 'salary' => 50000],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'salary' => 60000],
            ['name' => 'Charlie', 'email' => 'charlie@test.org', 'salary' => 75000],
            ['name' => 'Diana', 'email' => 'diana@test.org', 'salary' => 45000]
        );
    }

    // =========================================================================
    // EQUALITY OPERATORS
    // =========================================================================

    public function testEqualityFilter()
    {
        $user = User::items()->filter(['name' => 'Alice'])->one();

        $this->assertNotNull($user);
        $this->assertEquals('Alice', $user->name);
    }

    public function testEqualityFilterWithEq()
    {
        $user = User::items()->filter(['name.eq' => 'Bob'])->one();

        $this->assertNotNull($user);
        $this->assertEquals('Bob', $user->name);
    }

    public function testNotEqualFilter()
    {
        $users = User::items()->filter(['name.neq' => 'Alice'])->array();

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertNotEquals('Alice', $user->name);
        }
    }

    // =========================================================================
    // COMPARISON OPERATORS
    // =========================================================================

    public function testLessThanFilter()
    {
        $users = User::items()->filter(['salary.lt' => 60000])->array();

        $this->assertCount(2, $users); // Alice (50000), Diana (45000)
    }

    public function testLessThanOrEqualFilter()
    {
        $users = User::items()->filter(['salary.lte' => 60000])->array();

        $this->assertCount(3, $users); // Alice, Bob, Diana
    }

    public function testGreaterThanFilter()
    {
        $users = User::items()->filter(['salary.gt' => 60000])->array();

        $this->assertCount(1, $users); // Charlie (75000)
        $this->assertEquals('Charlie', $users[0]->name);
    }

    public function testGreaterThanOrEqualFilter()
    {
        $users = User::items()->filter(['salary.gte' => 60000])->array();

        $this->assertCount(2, $users); // Bob, Charlie
    }

    // =========================================================================
    // STRING OPERATORS
    // =========================================================================

    public function testContainsFilter()
    {
        $users = User::items()->filter(['email.contains' => 'test'])->array();

        $this->assertCount(2, $users); // Charlie, Diana (test.org)
    }

    public function testStartsWithFilter()
    {
        $users = User::items()->filter(['name.startswith' => 'A'])->array();

        $this->assertCount(1, $users);
        $this->assertEquals('Alice', $users[0]->name);
    }

    public function testEndsWithFilter()
    {
        $users = User::items()->filter(['email.endswith' => '.org'])->array();

        $this->assertCount(2, $users); // Charlie, Diana
    }

    // =========================================================================
    // IN / NOT IN OPERATORS
    // =========================================================================

    public function testInFilter()
    {
        $users = User::items()->filter(['name.in' => ['Alice', 'Bob']])->array();

        $this->assertCount(2, $users);
    }

    public function testNotInFilter()
    {
        $users = User::items()->filter(['name.not_in' => ['Alice', 'Bob']])->array();

        $this->assertCount(2, $users); // Charlie, Diana
    }

    // =========================================================================
    // NULL OPERATORS
    // =========================================================================

    public function testIsNullFilter()
    {
        // Use sponsor field name (which maps to sponsor_id column)
        $users = User::items()->filter(['sponsor.is_null' => true])->array();

        $this->assertCount(4, $users);
    }

    public function testIsNotNullFilter()
    {
        $users = User::items()->filter(['sponsor.is_null' => false])->array();

        $this->assertCount(0, $users); // No users have sponsor set
    }

    // =========================================================================
    // CONJUNCTIONS
    // =========================================================================

    public function testAndConjunction()
    {
        $users = User::items()->filter([
            'salary.gte' => 50000,
            'and',
            'salary.lte' => 60000
        ])->array();

        $this->assertCount(2, $users); // Alice (50000), Bob (60000)
    }

    public function testOrConjunction()
    {
        // Use different fields to avoid array key collision in PHP
        $users = User::items()->filter([
            'name' => 'Alice',
            'or',
            'email' => 'charlie@test.org'
        ])->array();

        $this->assertCount(2, $users);
    }

    public function testChainedFiltersImplicitAnd()
    {
        // Chained filter() calls are the correct way to combine filters without explicit conjunction
        $users = User::items()
            ->filter(['salary.gte' => 50000])
            ->filter(['email.endswith' => '.com'])
            ->array();

        $this->assertCount(2, $users); // Alice, Bob (.com and >= 50000)
    }

    // =========================================================================
    // CHAINED FILTERS
    // =========================================================================

    public function testChainedFilters()
    {
        $users = User::items()
            ->filter(['salary.gte' => 50000])
            ->filter(['email.endswith' => '.com'])
            ->array();

        $this->assertCount(2, $users); // Alice, Bob
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testEmptyResultFilter()
    {
        $users = User::items()->filter(['name' => 'NonExistent'])->array();

        $this->assertCount(0, $users);
    }

    public function testFilterReturnsNullForNonExistent()
    {
        $user = User::items()->filter(['name' => 'NonExistent'])->one();

        $this->assertNull($user);
    }

    // =========================================================================
    // CONJUNCTION VALIDATION TESTS
    // =========================================================================

    public function testSingleOperatorValid()
    {
        // Should not throw
        $users = User::items()->filter(['name' => 'Alice'])->array();
        $this->assertCount(1, $users);
    }

    public function testTwoOperatorsWithoutConjunctionThrows()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Missing conjunction between filter operators');

        // Two operators without explicit conjunction should throw
        User::items()->filter([
            'salary.gte' => 50000,
            'email.endswith' => '.com'
        ])->array();
    }

    public function testTwoOperatorsExplicitAndValid()
    {
        $users = User::items()->filter([
            'salary.gte' => 50000,
            'and',
            'salary.lte' => 70000
        ])->array();
        $this->assertCount(2, $users);
    }

    public function testThreeOperatorsAllConjunctionsValid()
    {
        $users = User::items()->filter([
            'salary.gte' => 40000,
            'and',
            'salary.lte' => 70000,
            'and',
            'email.endswith' => '.com'
        ])->array();
        $this->assertCount(2, $users);
    }

    public function testThreeOperatorsMissingConjunctionThrows()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Missing conjunction between filter operators');

        // This should throw - 3 operators with only 1 conjunction
        User::items()->filter([
            'salary.gte' => 40000,
            'and',
            'salary.lte' => 70000,
            'email.endswith' => '.com'  // Missing conjunction before this
        ])->array();
    }

    public function testThreeOperatorsNoConjunctionsThrows()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Missing conjunction between filter operators');

        // This should throw - 3 operators with no conjunctions
        User::items()->filter([
            'name' => 'Alice',
            'salary.gte' => 40000,
            'email.endswith' => '.com'
        ])->array();
    }

    public function testFourOperatorsProperConjunctionsValid()
    {
        $users = User::items()->filter([
            'salary.gte' => 40000,
            'and',
            'salary.lte' => 80000,
            'and',
            'email.contains' => '@',
            'and',
            'name.startswith' => 'A'
        ])->array();
        $this->assertCount(1, $users); // Only Alice
    }

    public function testMixedAndOrConjunctionsValid()
    {
        // Use different fields to avoid PHP array key collision
        $users = User::items()->filter([
            'name' => 'Alice',
            'or',
            'email' => 'bob@example.com',
            'or',
            'salary.eq' => 75000
        ])->array();
        $this->assertCount(3, $users); // Alice, Bob (by email), Charlie (by salary)
    }
}
