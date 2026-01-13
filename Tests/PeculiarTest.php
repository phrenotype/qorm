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
}
