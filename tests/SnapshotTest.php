<?php

namespace Oobook\Snapshot\Tests;

use BadMethodCallException;
use Illuminate\Support\Facades\Event;
use Oobook\Snapshot\Tests\TestModels\User;
use Oobook\Snapshot\Tests\TestModels\UserException;
use Oobook\Snapshot\Tests\TestModels\UserSnapshot;

class SnapshotTest extends TestCase
{

    public $model;
    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new UserSnapshot();
    }

    public function test_manage_eloquent_trait()
    {
        $model = new UserSnapshot([
            'name' => 'Lorem',
        ]);

        $this->assertTrue(method_exists($model, 'getColumns'), 'ManageEloquent Trait does not have "getColumns" method');
        $this->assertTrue(method_exists($model, 'definedRelations'), 'ManageEloquent Trait does not have "definedRelations" method');
    }

    public function test_source_model_exception()
    {
        $this->expectExceptionMessage("Oobook\Snapshot\Tests\TestModels\UserException must have a \$snapshotSourceModel public property.");

        new UserException([
            'name' => 'Lorem',
        ]);
    }

    public function test_throw_exceptions()
    {
        $model = new UserSnapshot([
            'name' => 'Lorem',
        ]);

        $this->assertNull($model->_snapshotSourceFields);
        $this->assertNull($model->_originalFillable);

        $this->expectException(BadMethodCallException::class);

        $model->getSnapshotSourceExcepts();
    }

    public function test_snapshot_properties()
    {
        $model = new UserSnapshot([
            'name' => 'Lorem',
        ]);

        $this->assertEquals($model->snapshotSourceExcepts, ['name', 'files']);
        $this->assertEquals($model->snapshotSourceRelationships, ['files', 'posts']);
    }

    public function test_model_saving_result()
    {
        // Event::fake();

        $user = User::create([
            'email' => 'lorems@noreply.com',
            'name' => 'User Lorem'
        ]);

        $user->posts()->createMany([
            [
                'title' => 'Post Title 1',
                'content' => 'Post Content 1',
            ],
            [
                'title' => 'Post Title 2',
                'content' => 'Post Content 2',
            ]
        ]);

        $model = new UserSnapshot([
            'user_id' => $user->id,
            'name' => 'Lorem',
            'posts' => [1]
        ]);

        $model->save();

        $model = UserSnapshot::find(1);

        $this->assertEquals($model->snapshot->data['id'], $user->id);
        $this->assertEquals($model->snapshot->data['email'], $user->email);
        $this->assertEquals($model->email, $user->email);
        $this->assertEquals($model->name, 'Lorem');
        $this->assertCount(2, $model->source->posts );
        $this->assertCount(1, $model->posts);

        // Event::assertDispatched('eloquent.creating: ' . get_class($model));
    }

    public function test_model_creation_result()
    {
        $user = User::create([
            'email' => 'lorems@noreply.com',
            'name' => 'lorems'
        ]);

        $model = UserSnapshot::create([
            'user_id' => $user->id,
            'name' => 'Lorem'
        ]);

        $model = UserSnapshot::find($model->id);

        $snapshot = $model->snapshot;

        $this->assertEquals($model->email, $user->email);
        $this->assertEquals($model->name, 'Lorem');

        $this->assertEquals($snapshot->data['id'], $user->id);
        $this->assertEquals($snapshot->data['email'], $user->email);

        $this->assertArrayNotHasKey('name', $snapshot->data);
        $this->assertArrayNotHasKey('files', $snapshot->data);
        $this->assertArrayNotHasKey('files', $model);

        $this->assertCount(0, $model->posts);
        $this->assertCount(0, $snapshot->data['posts']);

    }

    public function test_model_updating_result()
    {
        $user = User::create([
            'email' => 'lorems@noreply.com',
            'name' => 'lorems'
        ]);

        $model = UserSnapshot::create([
            'user_id' => $user->id,
            'name' => 'Lorem'
        ]);

        $model = UserSnapshot::find($model->id);

        $model->update([
            'name' => 'Lorem 2',
            'email' => 'override@noreply.com'
        ]);

        $model = UserSnapshot::find($model->id);

        $this->assertEquals($model->snapshot->data['id'], $user->id);
        $this->assertEquals($model->snapshot->data['email'], 'override@noreply.com');
        $this->assertEquals($model->email, 'override@noreply.com');
        $this->assertEquals($model->name, 'Lorem 2');
    }
}
