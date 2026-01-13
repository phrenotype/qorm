<?php

namespace Tests;

use Tests\Models\User;
use Tests\Models\SimplePost;
use Tests\Models\Comment;
use Q\Orm\Connection;

/**
 * Tests for Create, Read, Update, Delete operations.
 */
class CrudTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create tables for testing
        $pdo = Connection::getInstance();

        $pdo->exec("DROP TABLE IF EXISTS comment");
        $pdo->exec("DROP TABLE IF EXISTS simple_post");
        $pdo->exec("DROP TABLE IF EXISTS user");

        $pdo->exec("CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            salary INTEGER DEFAULT 0,
            sponsor_id INTEGER NULL,
            created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE simple_post (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            user_id INTEGER NOT NULL
        )");

        $pdo->exec("CREATE TABLE comment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            post_id INTEGER NOT NULL
        )");
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Clean tables before each test
        $pdo = Connection::getInstance();
        $pdo->exec("DELETE FROM comment");
        $pdo->exec("DELETE FROM simple_post");
        $pdo->exec("DELETE FROM user");
    }

    // =========================================================================
    // CREATE TESTS
    // =========================================================================

    public function testCreateSingleRecord()
    {
        $handler = User::items()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $user = $handler->one();

        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertNotNull($user->id);
    }

    public function testCreateWithDefaults()
    {
        User::items()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        $user = User::items()->filter(['email' => 'jane@example.com'])->one();

        $this->assertNotNull($user);
        // salary should be default 0
        $this->assertEquals(0, $user->salary);
        // created should be auto-set
        $this->assertNotNull($user->created);
    }

    public function testCreateWithRelationship()
    {
        // Create a user first
        User::items()->create([
            'name' => 'Author',
            'email' => 'author@example.com'
        ]);
        $author = User::items()->filter(['email' => 'author@example.com'])->one();

        // Create a post with relationship (passing the author object as FK)
        SimplePost::items()->create([
            'title' => 'My First Post',
            'body' => 'This is the body',
            'user' => $author
        ]);

        $post = SimplePost::items()->one();

        $this->assertNotNull($post);
        $this->assertEquals('My First Post', $post->title);
        $this->assertEquals('This is the body', $post->body);
        // Relationship accessor tests belong in RelationshipTest
    }

    public function testBulkCreate()
    {
        User::items()->create(
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com'],
            ['name' => 'User 3', 'email' => 'user3@example.com']
        );

        $count = User::items()->count();
        $this->assertEquals(3, $count);
    }

    // =========================================================================
    // READ TESTS
    // =========================================================================

    public function testReadOne()
    {
        User::items()->create(['name' => 'Test', 'email' => 'test@example.com']);

        $user = User::items()->one();

        $this->assertNotNull($user);
        $this->assertInstanceOf(User::class, $user);
    }

    public function testReadAll()
    {
        User::items()->create(
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com']
        );

        $users = User::items()->all();

        $this->assertIsIterable($users);
        $count = 0;
        foreach ($users as $user) {
            $count++;
            $this->assertInstanceOf(User::class, $user);
        }
        $this->assertEquals(2, $count);
    }

    public function testReadWithFilter()
    {
        User::items()->create(
            ['name' => 'Alice', 'email' => 'alice@example.com', 'salary' => 50000],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'salary' => 60000]
        );

        $user = User::items()->filter(['name' => 'Alice'])->one();

        $this->assertNotNull($user);
        $this->assertEquals('Alice', $user->name);
        $this->assertEquals(50000, $user->salary);
    }

    public function testReadExists()
    {
        $this->assertFalse(User::items()->exists());

        User::items()->create(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue(User::items()->exists());
    }

    public function testReadCount()
    {
        $this->assertEquals(0, User::items()->count());

        User::items()->create(
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com']
        );

        $this->assertEquals(2, User::items()->count());
    }

    // =========================================================================
    // UPDATE TESTS
    // =========================================================================

    public function testUpdateSingleRecord()
    {
        User::items()->create(['name' => 'Original', 'email' => 'test@example.com']);
        $user = User::items()->one();

        User::items()->filter(['id' => $user->id])->update(['name' => 'Updated']);

        $updated = User::items()->filter(['id' => $user->id])->one();
        $this->assertEquals('Updated', $updated->name);
    }

    public function testUpdateWithFilter()
    {
        User::items()->create(
            ['name' => 'Alice', 'email' => 'alice@example.com', 'salary' => 50000],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'salary' => 50000]
        );

        // Update only Alice
        User::items()->filter(['name' => 'Alice'])->update(['salary' => 60000]);

        $alice = User::items()->filter(['name' => 'Alice'])->one();
        $bob = User::items()->filter(['name' => 'Bob'])->one();

        $this->assertEquals(60000, $alice->salary);
        $this->assertEquals(50000, $bob->salary); // Bob unchanged
    }

    public function testUpdateMultipleFields()
    {
        User::items()->create(['name' => 'Test', 'email' => 'test@example.com', 'salary' => 0]);

        User::items()->filter(['email' => 'test@example.com'])->update([
            'name' => 'New Name',
            'salary' => 75000
        ]);

        $user = User::items()->one();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals(75000, $user->salary);
    }

    // =========================================================================
    // DELETE TESTS
    // =========================================================================

    public function testDeleteSingleRecord()
    {
        User::items()->create(['name' => 'ToDelete', 'email' => 'delete@example.com']);
        $this->assertEquals(1, User::items()->count());

        User::items()->filter(['email' => 'delete@example.com'])->delete();

        $this->assertEquals(0, User::items()->count());
    }

    public function testDeleteWithFilter()
    {
        User::items()->create(
            ['name' => 'Keep', 'email' => 'keep@example.com'],
            ['name' => 'Delete', 'email' => 'delete@example.com']
        );

        User::items()->filter(['name' => 'Delete'])->delete();

        $this->assertEquals(1, User::items()->count());
        $remaining = User::items()->one();
        $this->assertEquals('Keep', $remaining->name);
    }

    public function testDeleteAll()
    {
        User::items()->create(
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com'],
            ['name' => 'User 3', 'email' => 'user3@example.com']
        );

        $this->assertEquals(3, User::items()->count());

        // Delete all (no filter)
        User::items()->delete();

        $this->assertEquals(0, User::items()->count());
    }

    // =========================================================================
    // MODEL SAVE TESTS
    // =========================================================================

    public function testModelSaveCreate()
    {
        $user = new User();
        $user->name = 'SaveTest';
        $user->email = 'save@example.com';

        $saved = $user->save();

        $this->assertNotNull($saved);
        $this->assertNotNull($saved->id);
        $this->assertEquals('SaveTest', $saved->name);
    }

    public function testModelSaveUpdate()
    {
        User::items()->create(['name' => 'Original', 'email' => 'update@example.com']);
        $user = User::items()->one();

        $user->name = 'Modified';

        // Suppress expected E_USER_NOTICE for sponsor property (nullable FK field)
        // The sponsor field is defined in schema but has null value, which triggers warning
        set_error_handler(function ($errno, $errstr) {
            // Suppress expected warnings about sponsor field
            if ($errno === E_USER_NOTICE && strpos($errstr, 'sponsor') !== false) {
                return true;
            }
            return false;
        });

        try {
            $updated = $user->save();
        } finally {
            restore_error_handler();
        }

        $this->assertEquals('Modified', $updated->name);

        // Verify in database
        $reloaded = User::items()->filter(['id' => $user->id])->one();
        $this->assertEquals('Modified', $reloaded->name);
    }
}
