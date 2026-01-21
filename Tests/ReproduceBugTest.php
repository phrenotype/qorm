<?php

namespace Tests;

use Tests\Models\PeculiarUser;
use Q\Orm\Connection;

class ReproduceBugTest extends QormTestCase
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
        // Ensure APCu state is clean for test
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }

    public function testBatchPeculiarIdGenerationWithoutDelays()
    {
        $records = [];
        $count = 100;
        
        for ($i = 0; $i < $count; $i++) {
            $records[] = ['name' => "User $i"];
        }

        try {
            // This batch create should trigger the race condition if it exists
            PeculiarUser::items()->create(...$records);
            
            // Verify count
            $actualCount = PeculiarUser::items()->count();
            $this->assertEquals($count, $actualCount, "Expected $count records, found $actualCount");
            
            // Verify uniqueness
            $ids = [];
            foreach (PeculiarUser::items()->all() as $u) {
                $ids[] = $u->peculiar;
            }
            $uniqueCount = count(array_unique($ids));
            $this->assertEquals($count, $uniqueCount, "Duplicate IDs found! Expected $count unique IDs, found $uniqueCount");
            
        } catch (\PDOException $e) {
            $this->fail("PDOException caught: " . $e->getMessage());
        }
    }
}
