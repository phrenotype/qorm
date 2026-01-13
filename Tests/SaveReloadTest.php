<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

class SaveReloadTest extends QormTestCase
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
    }

    public function testReloadUpdatesCleanObject()
    {
        // 1. Create a user
        $user = new GroupingUser();
        $user->name = 'Original';
        $user->email = 'orig@test.com';
        $user = $user->save();

        $id = $user->id;

        // 2. Modify the record in the database "behind the back" of the object
        $pdo = Connection::getInstance();
        $pdo->exec("UPDATE grouping_user SET name = 'Changed' WHERE id = $id");

        // 3. Verify object still has old name
        $this->assertEquals('Original', $user->name);

        // 4. Reload the object
        // Current implementation checks isDirty. Since we haven't touched $user since save(), 
        // isDirty should be false (or prevState matches current).
        // If reload() relies on isDirty, it won't fetch the new name.
        $user->reload();

        // 5. Assert name is updated
        $this->assertEquals('Changed', $user->name, 'Reload should fetch latest data from DB even if object is clean');
    }

    public function testSaveUpdatesObjectInPlace()
    {
        $user = new GroupingUser();
        $user->name = 'InPlace';
        $user->email = 'inplace@test.com';

        // Calling save without capturing return value
        $user->save();

        // Expectation: The user object should be updated with the ID from the DB
        $this->assertNotNull($user->id, 'User object should have ID after save()');
    }
}
