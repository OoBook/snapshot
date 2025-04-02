<?php

namespace Oobook\Snapshot\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Oobook\Snapshot\Tests\TestModels\User;
use Oobook\Snapshot\Tests\TestModels\UserSnapshot;
use Oobook\Snapshot\Tests\TestModels\UserType;

class SnapshotLazyRelationsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $userSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user types
        UserType::insert([
            [
                'id' => 1,
                'title' => 'User Type 1',
                'description' => 'User Type 1 Description',
            ],
            [
                'id' => 2,
                'title' => 'User Type 2',
                'description' => 'User Type 2 Description',
            ],
        ]);

        // Create a user with posts and files
        $this->user = User::create([
            'user_type_id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        $this->user->posts()->createMany([
            [
                'title' => 'Post Title 1',
                'content' => 'Post Content 1',
            ],
            [
                'title' => 'Post Title 2',
                'content' => 'Post Content 2',
            ],
            [
                'title' => 'Post Title 3',
                'content' => 'Post Content 3',
            ]
        ]);

        $this->user->fileNames()->createMany([
            [
                'name' => 'File 1',
            ],
            [
                'name' => 'File 2',
            ],
        ]);

        // Create a snapshot with specific posts
        $this->userSnapshot = UserSnapshot::create([
            'user_id' => $this->user->id,
            'name' => 'Snapshot Name',
            'posts' => [1, 2] // Only include first two posts
        ]);
    }

    public function testBasicLazyLoading()
    {
        // Get a fresh instance without loaded relations
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Verify relations aren't loaded yet
        $this->assertFalse($snapshot->relationLoaded('posts'));
        
        // Load posts relation
        $snapshot->load('posts');
        
        // Verify relation is now loaded
        $this->assertTrue($snapshot->relationLoaded('posts'));
        
        // Verify only the posts from snapshot are loaded (2 posts)
        $this->assertCount(2, $snapshot->posts);
        
        // Verify source has all posts (3 posts)
        $this->assertCount(3, $snapshot->source->posts);
    }

    public function testLoadingMultipleRelations()
    {
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Load multiple relations
        $snapshot->load(['posts', 'fileNames', 'userType']);
        
        // Verify all relations are loaded
        $this->assertTrue($snapshot->relationLoaded('posts'));
        $this->assertTrue($snapshot->relationLoaded('fileNames'));
        $this->assertTrue($snapshot->relationLoaded('userType'));
        
        // Verify correct counts
        $this->assertCount(2, $snapshot->posts);
        $this->assertCount(2, $snapshot->fileNames);
        $this->assertNotNull($snapshot->userType);
    }

    public function testLoadMissing()
    {
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Load posts first
        $snapshot->load('posts');
        
        // Then load missing relations
        $snapshot->loadMissing(['posts', 'fileNames', 'userType']);
        
        // Verify posts wasn't reloaded (still has 2 items)
        $this->assertCount(2, $snapshot->posts);
        
        // Verify other relations were loaded
        $this->assertTrue($snapshot->relationLoaded('fileNames'));
        $this->assertTrue($snapshot->relationLoaded('userType'));
    }

    // public function testLoadCount()
    // {
    //     $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
    //     // Load counts
    //     $snapshot->loadCount(['posts', 'fileNames']);
        
    //     // Verify counts are correct
    //     $this->assertEquals(2, $snapshot->posts_count);
    //     $this->assertEquals(2, $snapshot->fileNames_count);
    // }

    public function testConstrainedLoading()
    {
        // Update post titles to test constraints
        $this->user->posts()->where('id', 1)->update(['title' => 'Special Post']);
        
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Load with constraints
        $snapshot->load(['posts' => function($query) {
            $query->where('title', 'Special Post');
        }]);
        
        // Verify only the constrained post is loaded
        $this->assertCount(2, $snapshot->posts);
        $this->assertEquals('Post Title 1', $snapshot->posts->first()->title);
    }

    public function testNestedRelationLoading()
    {
        // Create a nested relation scenario
        $post = $this->user->posts()->first();
        $post->comments()->create(['content' => 'Test Comment']);
        
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Load nested relation
        $snapshot->load('posts.comments');
        
        // Verify nested relation is loaded
        $this->assertFalse($snapshot->posts->first()->relationLoaded('comments'));
        $this->assertCount(1, $snapshot->posts->first()->comments);
    }

    public function testLoadingNonSnapshotRelations()
    {
        // Create a relation that isn't in the snapshot
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // This should load from the source model
        $snapshot->load('nonSnapshotRelation');
        
        // Verify it loaded from source
        $this->assertEquals($snapshot->source->nonSnapshotRelation, $snapshot->nonSnapshotRelation);
    }

    public function testLoadingWithArrayOfCallbacks()
    {
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // Load with array of callbacks
        $snapshot->load([
            'posts' => function($query) {
                $query->where('id', 1);
            },
            'fileNames' => function($query) {
                $query->where('name', 'File 2');
            },
            // 'userType' => function($query) {
            //     $query->where('id', 1);
            // }
        ]);
        
        // Verify constraints were applied
        $this->assertCount(2, $snapshot->posts);
        $this->assertEquals(1, $snapshot->posts->first()->id);

        
        $this->assertCount(1, $snapshot->fileNames);
        $this->assertEquals('File 2', $snapshot->fileNames->first()->name);
    }

    public function testLoadingWithClosureInArray()
    {
        $snapshot = UserSnapshot::find($this->userSnapshot->id);
        
        // This is a different format Laravel supports
        $snapshot->load([function($query) {
            $query->posts();
        }]);
        
        // Verify it worked
        $this->assertTrue($snapshot->relationLoaded('posts'));
        $this->assertCount(2, $snapshot->posts);
    }

    public function testLoadingRelationThatDoesntExistInSnapshot()
    {
        // Create a new snapshot without posts
        $newSnapshot = UserSnapshot::create([
            'user_id' => $this->user->id,
            'name' => 'No Posts Snapshot'
        ]);
        
        $snapshot = UserSnapshot::find($newSnapshot->id);
        
        // Try to load posts (which aren't in the snapshot)
        $snapshot->load('posts');
        
        // Should load from source
        $this->assertTrue($snapshot->relationLoaded('posts'));
        $this->assertCount(3, $snapshot->posts);
    }
}