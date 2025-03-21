<?php

namespace Oobook\Snapshot\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Oobook\Snapshot\Tests\TestModels\User;
use Oobook\Snapshot\Tests\TestModels\UserSnapshot;
use Oobook\Snapshot\Tests\TestModels\UserEmailSnapshotted;
use Oobook\Snapshot\Tests\TestModels\UserType;

class SnapshotMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected UserSnapshot $userSnapshot;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        UserType::insert([
            [
                'id' => 1,
                'title' => 'User Type 1',
                'description' => 'User Type 1 Description',
            ],
        ]);

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
            ]
        ]);

        $this->userSnapshot = UserSnapshot::create([
            'user_id' => $this->user->id,
            'name' => 'Snapshot Name',
        ]);
    }

    public function test_get_snapshot_config()
    {
        $config = UserSnapshot::getSnapshotConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('snapshot_attributes', $config);
        $this->assertArrayHasKey('synced_attributes', $config);
        $this->assertArrayHasKey('snapshot_relationships', $config);
        $this->assertArrayHasKey('synced_relationships', $config);
    }

    public function test_has_source_attribute_to_sync()
    {
        $hasAttributesToSync = UserSnapshot::hasSourceAttributeToSync();

        $this->assertTrue($hasAttributesToSync);
        
        $hasAttributesToSync = UserEmailSnapshotted::hasSourceAttributeToSync();
        $this->assertFalse($hasAttributesToSync);
    }

    public function test_get_source_attributes_to_sync()
    {
        $attributes = UserSnapshot::getSourceAttributesToSync();
        $this->assertIsArray($attributes);
        $this->assertContains('email', $attributes);
        
        $attributes = UserEmailSnapshotted::getSourceAttributesToSync();
        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }

    public function test_field_is_snapshot_synced()
    {
        $this->assertFalse($this->userSnapshot->fieldIsSnapshotSynced('name'));
        $this->assertTrue($this->userSnapshot->fieldIsSnapshotSynced('email'));
        
        $userEmailSnapshotted = UserEmailSnapshotted::create([
            'user_id' => $this->user->id,
            'name' => 'Email Snapshot',
        ]);
        
        $this->assertFalse($userEmailSnapshotted->fieldIsSnapshotSynced('name'));
        $this->assertFalse($userEmailSnapshotted->fieldIsSnapshotSynced('email'));
    }

    public function test_has_source_relationship_to_sync()
    {
        $hasRelationshipsToSync = UserSnapshot::hasSourceRelationshipToSync();
        $this->assertTrue($hasRelationshipsToSync);
        
        $hasRelationshipsToSync = UserEmailSnapshotted::hasSourceRelationshipToSync();
        $this->assertFalse($hasRelationshipsToSync);
    }

    public function test_get_source_relationships_to_sync()
    {
        $relationships = UserSnapshot::getSourceRelationshipsToSync();
        $this->assertIsArray($relationships);
        $this->assertNotContains('posts', $relationships);
        $this->assertContains('userType', $relationships);
        $this->assertContains('fileNames', $relationships);
        
        $relationships = UserEmailSnapshotted::getSourceRelationshipsToSync();
        $this->assertIsArray($relationships);
        $this->assertEmpty($relationships);
    }

    public function test_relationship_is_snapshot_synced()
    {
        $this->assertFalse($this->userSnapshot->relationshipIsSnapshotSynced('posts'));
        $this->assertTrue($this->userSnapshot->relationshipIsSnapshotSynced('userType'));
        
        $userEmailSnapshotted = UserEmailSnapshotted::create([
            'user_id' => $this->user->id,
            'name' => 'Email Snapshot',
        ]);
        
        $this->assertFalse($userEmailSnapshotted->relationshipIsSnapshotSynced('posts'));
        $this->assertFalse($userEmailSnapshotted->relationshipIsSnapshotSynced('userType'));
    }

    public function test_get_snapshot_source_class()
    {
        $sourceClass = UserSnapshot::$snapshotSourceModel;

        $this->assertEquals(User::class, $sourceClass);
    }

    public function test_get_reserved_attributes_against_snapshot()
    {
        $reservedAttributes = $this->userSnapshot->getReservedAttributesAgainstSnapshot();
        $this->assertIsArray($reservedAttributes);
        $this->assertContains('id', $reservedAttributes);
        $this->assertContains('name', $reservedAttributes);
        $this->assertContains('created_at', $reservedAttributes);
        $this->assertContains('updated_at', $reservedAttributes);
    }

    public function test_get_snapshot_source_foreign_key()
    {
        $foreignKey = UserSnapshot::getSnapshotSourceForeignKey();
        $this->assertEquals('user_id', $foreignKey);
    }

    public function test_get_snapshotable_source_attributes()
    {
        $attributes = UserSnapshot::getSnapshotableSourceAttributes();
        $this->assertIsArray($attributes);
        $this->assertContains('email', $attributes);
        $this->assertContains('name', $attributes);
        $this->assertContains('user_type_id', $attributes);
        $this->assertContains('created_at', $attributes);
        $this->assertContains('updated_at', $attributes);
    }

    public function test_get_snapshotable_source_relationships()
    {
        $relationships = UserSnapshot::getSnapshotableSourceRelationships();
        $this->assertIsArray($relationships);
        $this->assertContains('posts', $relationships);
        $this->assertContains('userType', $relationships);
        $this->assertContains('fileNames', $relationships);
    }

    public function test_get_source_attributes_to_snapshot()
    {
        $attributes = $this->userSnapshot->getSourceAttributesToSnapshot();
        $this->assertIsArray($attributes);
        $this->assertNotContains('email', $attributes);
        $this->assertNotContains('user_type_id', $attributes);
    }

    public function test_get_source_relationships_to_snapshot()
    {
        $relationships = $this->userSnapshot->getSourceRelationshipsToSnapshot();
        $this->assertIsArray($relationships);
        $this->assertContains('posts', $relationships);
        $this->assertNotContains('userType', $relationships);
        $this->assertNotContains('fileNames', $relationships);
    }

    public function test_get_fillable_for_snapshot()
    {
        $fillable = $this->callMethod($this->userSnapshot, 'getFillableForSnapshot', []);

        $this->assertIsArray($fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('posts', $fillable);
        $this->assertNotContains('email', $fillable);
        $this->assertNotContains('user_type_id', $fillable);
        $this->assertNotContains('userType', $fillable);
        $this->assertNotContains('fileNames', $fillable);
    }

    public function test_prepare_data_to_snapshot()
    {
        $data = $this->userSnapshot->prepareDataToSnapshot();
        
        $this->assertIsArray($data);
        $this->assertEquals($this->user->id, $data['id']);
        $this->assertEquals($this->user->email, $data['email']);
        $this->assertEquals($this->user->name, $data['name']);
        $this->assertEquals($this->user->user_type_id, $data['user_type_id']);
        $this->assertArrayHasKey('posts', $data);
        $this->assertCount(2, $data['posts']);
    }

    public function test_snapshot_relationship()
    {
        $snapshot = $this->userSnapshot->snapshot;
        
        $this->assertNotNull($snapshot);
        $this->assertEquals($this->user->id, $snapshot->source_id);
        $this->assertEquals(User::class, $snapshot->source_type);
        $this->assertEquals(UserSnapshot::class, $snapshot->snapshotable_type);
    }

    public function test_source_relationship()
    {
        $source = $this->userSnapshot->source;
        
        $this->assertNotNull($source);
        $this->assertEquals($this->user->id, $source->id);
        $this->assertEquals($this->user->email, $source->email);
        $this->assertEquals($this->user->name, $source->name);
    }
} 