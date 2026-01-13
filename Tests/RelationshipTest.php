<?php

namespace Tests;

use Tests\Models\User;
use Tests\Models\SimplePost;
use Tests\Models\SimpleComment;
use Q\Orm\Connection;

/**
 * Tests for OneToOne and ManyToOne relationships.
 */
class RelationshipTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS simple_comment");
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

        $pdo->exec("CREATE TABLE simple_comment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            simple_post_id INTEGER NOT NULL
        )");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Connection::getInstance();
        $pdo->exec("DELETE FROM simple_comment");
        $pdo->exec("DELETE FROM simple_post");
        $pdo->exec("DELETE FROM user");
    }

    // =========================================================================
    // MANY-TO-ONE / ONE-TO-MANY (User <-> SimplePost)
    // =========================================================================

    public function testForwardManyToOneAccess()
    {
        // User (Author)
        User::items()->create([
            'name' => 'Author',
            'email' => 'author@example.com'
        ]);
        $author = User::items()->one();

        // Post belongs to User
        SimplePost::items()->create([
            'title' => 'My Post',
            'body' => 'Body',
            'user' => $author
        ]);
        $post = SimplePost::items()->one();

        // Forward access: $post->user() should return User object
        $fetchedAuthor = $post->user();
        
        $this->assertNotNull($fetchedAuthor);
        $this->assertEquals('Author', $fetchedAuthor->name);
        $this->assertEquals($author->id, $fetchedAuthor->id);
    }

    public function testReverseOneToManyAccess()
    {
        User::items()->create([
            'name' => 'Author', 
            'email' => 'author@example.com'
        ]);
        $author = User::items()->one();

        SimplePost::items()->create(
            ['title' => 'Post 1', 'body' => 'Body 1', 'user' => $author],
            ['title' => 'Post 2', 'body' => 'Body 2', 'user' => $author]
        );

        // Reverse access: $author->simple_post_set should be a Handler
        // Note: SimplePost is multi-word, so QORM defaults to snake_case 'simple_post' + '_set'
        $postsHandler = $author->simple_post_set;
        
        $this->assertNotNull($postsHandler);
        $this->assertInstanceOf('Q\Orm\Handler', $postsHandler);
        
        $posts = $postsHandler->all();
        $this->assertCount(2, iterator_to_array($posts));
        
        // Test filtering on reverse relation
        $post1 = $author->simple_post_set->filter(['title' => 'Post 1'])->one();
        $this->assertNotNull($post1);
        $this->assertEquals('Post 1', $post1->title);
    }
    
    public function testReverseCreation()
    {
        User::items()->create(['name' => 'Author', 'email' => 'author@example.com']);
        $author = User::items()->one();
        
        // Create post via author's relation handler
        // Should automatically set user_id
        $author->simple_post_set->create([
            'title' => 'Created via Reverse',
            'body' => 'Magic'
        ]);
        
        $post = SimplePost::items()->one();
        $this->assertNotNull($post);
        $this->assertEquals('Created via Reverse', $post->title);
        
        // Verify FK was set
        $postAuthor = $post->user();
        $this->assertNotNull($postAuthor);
        $this->assertEquals($author->id, $postAuthor->id);
    }

    // =========================================================================
    // ONE-TO-ONE (User <-> Sponsor)
    // =========================================================================

    public function testOneToOneForwardAccess()
    {
        // Sponsor
        User::items()->create(['name' => 'Sponsor', 'email' => 'sponsor@example.com']);
        $sponsor = User::items()->one();
        
        // Sponsee
        User::items()->create([
            'name' => 'Sponsee', 
            'email' => 'sponsee@example.com',
            'sponsor' => $sponsor
        ]);
        
        $sponsee = User::items()->filter(['email' => 'sponsee@example.com'])->one();
        
        $fetchedSponsor = $sponsee->sponsor();
        $this->assertNotNull($fetchedSponsor);
        $this->assertEquals('Sponsor', $fetchedSponsor->name);
    }
    
    // Note: Reverse OneToOne access check ($sponsor->user_set?) or specific attribute logic
    // described in docs (if field name != class name, it adds attribute).
    // In User model: 'sponsor' => OneToOne(User::class).
    // According to docs, since field name 'sponsor' != 'user', User should have 'sponsor' attribute?
    // But 'sponsor' is the Forward field.
    // Docs say: "If Address named the reference field `owner`, User would have attribute called owner."
    // Here User references User via 'sponsor'. So User should have 'sponsor' attribute referring to the ONEUser that calls this user 'sponsor'.
    // AKA "Who is this user a sponsor FOR?"
    // However, since it's same class, name collision might occur?
    // Let's stick to forward OneToOne which is most critical.
    
    // =========================================================================
    // MULTI-LEVEL RELATIONSHIPS (SimpleComment -> SimplePost -> User)
    // =========================================================================

    public function testMultiLevelAccess()
    {
        User::items()->create(['name' => 'Author', 'email' => 'author@example.com']);
        $author = User::items()->one();
        
        SimplePost::items()->create(['title' => 'Post', 'body' => 'Body', 'user' => $author]);
        $post = SimplePost::items()->one();
        
        SimpleComment::items()->create(['text' => 'Comment', 'user' => $author, 'simple_post' => $post]);
        $comment = SimpleComment::items()->one();
        
        // Traverse: Comment -> Post -> User
        $commentPost = $comment->simple_post();
        $this->assertNotNull($commentPost);
        $this->assertEquals('Post', $commentPost->title);
        
        $commentPostAuthor = $commentPost->user();
        $this->assertNotNull($commentPostAuthor);
        $this->assertEquals('Author', $commentPostAuthor->name);
    }
}
