<?php

namespace Tests;

use Tests\Models\User;
use Tests\Models\SimplePost;
use Q\Orm\Connection;

class RelationshipReloadTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS user");
        $pdo->exec("DROP TABLE IF EXISTS simple_post");

        // Re-using simplified schemas for test
        // User (id, name, email)
        // SimplePost (id, title, user_id)

        \Tests\Helpers\TestUtil::createTableFromModel(User::class);
        \Tests\Helpers\TestUtil::createTableFromModel(SimplePost::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM simple_post");
        Connection::getInstance()->exec("DELETE FROM user");
    }

    public function testSavePreservesLoadedRelationships()
    {
        // 1. Create User
        $user = new User();
        $user->name = 'Rel Tester';
        $user->email = 'rel@test.com';
        $user = $user->save();

        // 2. Create Post belonging to user
        $post = new SimplePost();
        $post->title = 'My Post';
        $post->body = 'Content';
        $post->user = $user; // Assigning object
        $post = $post->save();

        // 3. Load the relationship explicitly
        // Accessing $post->user should load the User object
        $loadedUser = $post->user;
        $this->assertInstanceOf(User::class, $loadedUser, 'Pre-check: Post->user should be a User object');

        // 4. Modify Post and Save (In-Place Update)
        $post->title = 'Updated Post';
        $post->save();

        // 5. Verify $post->user is STILL a User object
        // If save() hydration overwrites 'user' with 'user_id' (int) from DB result, this will fail.
        $this->assertInstanceOf(User::class, $post->user, 'Post->user should remain a User object after save()');
        $this->assertEquals($user->id, $post->user->id);
    }

    public function testReloadPreservesLoadedRelationships()
    {
        // 1. Setup
        $user = new User();
        $user->name = 'Rel Reload';
        $user->email = 'reload@test.com';
        $user->save();

        $post = new SimplePost();
        $post->title = 'Reload Post';
        $post->body = 'Content';
        $post->user = $user;
        $post->save();

        // 2. Load relationship
        $loadedUser = $post->user;
        $this->assertInstanceOf(User::class, $loadedUser);

        // 3. External update to force reload to actually fetch
        Connection::getInstance()->exec("UPDATE simple_post SET title = 'External Change' WHERE id = {$post->id}");

        // 4. Reload
        $post->reload();

        // 5. Verify title updated AND user is still an object
        $this->assertEquals('External Change', $post->title);
        $this->assertInstanceOf(User::class, $post->user, 'Post->user should remain a User object after reload()');
    }
}
