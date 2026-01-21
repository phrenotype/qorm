<?php

namespace Tests;

use Tests\Models\PeculiarUser;
use Q\Orm\Connection;

class PeculiarTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS peculiar_user");
        \Tests\Helpers\TestUtil::createTableFromModel(PeculiarUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM peculiar_user");
    }

    public function testPeculiarIdGeneration()
    {
        // Create a user without specifying ID
        PeculiarUser::items()->create(['name' => 'First']);
        $user = PeculiarUser::items()->one();

        // Should have a generated ID
        $this->assertNotNull($user->peculiar);
        $this->assertIsInt($user->peculiar);
        // Peculiar IDs are large (64-bit), should be > 0
        $this->assertGreaterThan(0, $user->peculiar);
    }

    public function testPeculiarIdSorting()
    {
        // Create 3 users in sequence. Peculiar IDs are time-ordered.
        PeculiarUser::items()->create(['name' => 'A']);
        usleep(1000); // Ensure small time gap
        PeculiarUser::items()->create(['name' => 'B']);
        usleep(1000);
        PeculiarUser::items()->create(['name' => 'C']);

        $users = PeculiarUser::items()->order_by('peculiar ASC')->array();

        $this->assertCount(3, $users);
        $this->assertEquals('A', $users[0]->name);
        $this->assertEquals('B', $users[1]->name);
        $this->assertEquals('C', $users[2]->name);

        // Verify IDs are strictly increasing
        $this->assertTrue($users[1]->peculiar > $users[0]->peculiar);
        $this->assertTrue($users[2]->peculiar > $users[1]->peculiar);
    }

    public function testPeculiarIdUniqueness()
    {
        // Rapid creation loop
        for ($i = 0; $i < 10; $i++) {
            PeculiarUser::items()->create(['name' => "User $i"]);
        }

        $count = PeculiarUser::items()->count();
        $this->assertEquals(10, $count);

        // Fetch all IDs
        $users = PeculiarUser::items()->all();
        $ids = [];
        foreach ($users as $u) {
            $ids[] = $u->peculiar;
        }

        // Verify unique count matches total count
        $this->assertEquals(count($ids), count(array_unique($ids)));
    }

    public function testNextIdsBatchGeneration()
    {
        // 1. Simple batch fitting in one millisecond
        $ids = \Q\Orm\Peculiar\Peculiar::nextIds(10);
        $this->assertCount(10, $ids);
        $this->assertEquals(count($ids), count(array_unique($ids)));

        // Check sorting
        $first = (int) $ids[0];
        $last = (int) $ids[9];
        $this->assertLessThan($last, $first);
    }

    public function testNextIdsOverflow()
    {
        // 2. Large batch forcing millisecond rollover (MAX_SEQ is 4095)
        // Requesting 5000 IDs will force at least one rollover
        $ids = \Q\Orm\Peculiar\Peculiar::nextIds(5000);
        $this->assertCount(5000, $ids);
        $this->assertEquals(count($ids), count(array_unique($ids)));
    }

    public function testStrictEnforcement()
    {
        // 1. Test via Constructor
        $user = new PeculiarUser(['peculiar' => 12345, 'name' => 'Constructor Guard']);
        $this->assertNotEquals(12345, $user->peculiar);
        $user->save();
        $this->assertNotEquals(12345, $user->peculiar);

        // 2. Test via Bulk Create
        PeculiarUser::items()->create(
            ['peculiar' => 54321, 'name' => 'Bulk Guard 1'],
            ['peculiar' => 67890, 'name' => 'Bulk Guard 2']
        );

        $results = PeculiarUser::items()->filter(['name.startswith' => 'Bulk Guard'])->order_by('peculiar ASC')->array();
        foreach ($results as $r) {
            $this->assertNotEquals(54321, $r->peculiar);
            $this->assertNotEquals(67890, $r->peculiar);
        }

        // 3. Test via Single Create on Handler
        PeculiarUser::items()->create(['peculiar' => 111222, 'name' => 'Handler Guard']);
        $u = PeculiarUser::items()->filter(['name' => 'Handler Guard'])->one();
        $this->assertNotEquals(111222, $u->peculiar);
    }
}
