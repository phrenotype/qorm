<?php

namespace Tests;

use Tests\Models\GroupingUser;
use Q\Orm\Connection;

class AtomicTest extends QormTestCase
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
        GroupingUser::items()->create([
            'name' => 'Atom', 
            'email' => 'atom@test.com', 
            'salary' => 100,
            'status' => 'active'
        ]);
    }

    public function testIncrement()
    {
        // Increment salary by 50
        GroupingUser::items()->filter(['name.eq' => 'Atom'])->increment(['salary' => 50]);
        
        $user = GroupingUser::items()->filter(['name.eq' => 'Atom'])->one();
        $this->assertEquals(150, $user->salary);
    }

    public function testDecrement()
    {
        // Decrement salary by 30
        GroupingUser::items()->filter(['name.eq' => 'Atom'])->decrement(['salary' => 30]);

        $user = GroupingUser::items()->filter(['name.eq' => 'Atom'])->one();
        $this->assertEquals(70, $user->salary);
    }

    public function testMultiply()
    {
        // Multiply salary by 2
        GroupingUser::items()->filter(['name.eq' => 'Atom'])->multiply(['salary' => 2]);

        $user = GroupingUser::items()->filter(['name.eq' => 'Atom'])->one();
        $this->assertEquals(200, $user->salary);
    }

    public function testDivide()
    {
        // Divide salary by 4
        GroupingUser::items()->filter(['name.eq' => 'Atom'])->divide(['salary' => 4]);

        $user = GroupingUser::items()->filter(['name.eq' => 'Atom'])->one();
        $this->assertEquals(25, $user->salary);
    }
}
