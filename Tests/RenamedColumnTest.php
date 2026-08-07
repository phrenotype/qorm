<?php

namespace Tests;

use Tests\Models\Post;
use Tests\Models\User;
use Q\Orm\Connection;
use Q\Orm\QueryStack;

class RenamedColumnTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS post");
        $pdo->exec("DROP TABLE IF EXISTS user");
        \Tests\Helpers\TestUtil::createTableFromModel(User::class);
        \Tests\Helpers\TestUtil::createTableFromModel(Post::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Connection::getInstance();
        $pdo->exec("DELETE FROM post");
        $pdo->exec("DELETE FROM user");
        User::items()->create(['name' => 'u1', 'email' => 'u1@x.com']);
        $user = User::items()->one();
        Post::items()->create(['title' => 'Hello', 'body' => 'Body', 'user' => $user]);
    }

    public function testLoadReadsRenamedColumnThroughProp(): void
    {
        $queriesBefore = QueryStack::get();
        $post = Post::items()->filter(['title.eq' => 'Hello'])->one();
        $queriesAfter = QueryStack::get();

        // The load must hit the real renamed column, not a prop-named one
        $newQueries = array_slice($queriesAfter, count($queriesBefore));
        $this->assertNotEmpty($newQueries);
        $this->assertStringContainsString('realtitle', strtolower($newQueries[0]['query']));

        $this->assertSame('Hello', $post->title);
        $this->assertSame('Hello', $post->prevState()['title']);
    }

    public function testSavePreservesConcurrentWriteToRenamedColumn(): void
    {
        $post = Post::items()->filter(['title.eq' => 'Hello'])->one();

        Post::items()->filter(['title.eq' => 'Hello'])->update(['title' => 'Concurrent']);

        $post->body = 'Changed';
        $post->save();

        $fresh = Post::items()->filter(['title.eq' => 'Concurrent'])->one();
        $this->assertSame('Concurrent', $fresh->title);
        $this->assertSame('Changed', $fresh->body);
    }

    public function testUnchangedRenamedColumnSaveNoRewrite(): void
    {
        $post = Post::items()->filter(['title.eq' => 'Hello'])->one();

        $queriesBefore = QueryStack::get();
        $post->save();
        $queriesAfter = QueryStack::get();

        $newQueries = array_slice($queriesAfter, count($queriesBefore));
        foreach ($newQueries as $entry) {
            $this->assertStringNotContainsString('UPDATE', strtoupper($entry['query']));
        }
    }

    public function testUpdateRenamedColumnPersists(): void
    {
        $post = Post::items()->filter(['title.eq' => 'Hello'])->one();

        $post->title = 'World';
        $post->save();

        $fresh = Post::items()->filter(['title.eq' => 'World'])->one();
        $this->assertSame('World', $fresh->title);
    }
}
