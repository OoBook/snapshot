<?php

namespace Oobook\Snapshot\Tests;

use BadMethodCallException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Oobook\Snapshot\Tests\TestModels\User;
use Oobook\Snapshot\Tests\TestModels\UserEmailSnapshotted;
use Oobook\Snapshot\Tests\TestModels\UserException;
use Oobook\Snapshot\Tests\TestModels\UserSnapshot;
use Oobook\Snapshot\Tests\TestModels\UserType;

class SnapshotTest extends TestCase
{
    use RefreshDatabase;

    public $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new UserSnapshot();

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
    }

    public function test_manage_eloquent_trait()
    {
        $model = new UserSnapshot([
            'name' => 'Lorem',
        ]);

        $this->assertTrue(method_exists($model, 'getTableColumns'), 'ManageEloquent Trait does not have "getTableColumns" method');
        $this->assertTrue(method_exists($model, 'definedRelations'), 'ManageEloquent Trait does not have "definedRelations" method');
    }

    public function test_source_model_exception()
    {
        $this->expectExceptionMessage(
            UserException::class . " must have a \$snapshotSourceModel public static property."
        );

        new UserException([
            'name' => 'Lorem',
        ]);
    }

    public function test_throw_exceptions()
    {
        $model = new UserSnapshot([
            'name' => 'Lorem',
        ]);

        $this->assertNull($model->snapshotSavingData);
        $this->assertNull($model->originalFillableForSnapshot);

        // $this->expectException(BadMethodCallException::class);

        // $model->getSnapshotSourceExcepts();
    }

    public function test_model_saving_result()
    {
        // Event::fake();

        $user = User::create([
            'user_type_id' => 1,
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

        $user->fileNames()->createMany([
            [
                'name' => 'File 1',
            ],
            [
                'name' => 'File 2',
            ],
        ]);

        $userSnapshot = new UserSnapshot([
            'user_id' => $user->id,
            'name' => 'Lorem',
            'posts' => [1]
        ]);
        $userSnapshot->save();

        $userEmailSnapshotted = new UserEmailSnapshotted([
            'user_id' => $user->id,
            'name' => 'Lorem',
        ]);
        $userEmailSnapshotted->save();


        $user = User::find(1);
        $user->update([
            'email' => 'override@noreply.com',
        ]);

        $userSnapshot = UserSnapshot::find(1);
        $userEmailSnapshotted = UserEmailSnapshotted::find(1);

        $this->assertEquals($userSnapshot->snapshot->data['id'], $user->id);
        $this->assertEquals($userSnapshot->user_id, $user->id);

        $this->assertEquals($userSnapshot->snapshot->data['email'], 'lorems@noreply.com');
        $this->assertEquals($userSnapshot->email, $user->email);
        $this->assertEquals($userSnapshot->name, 'Lorem');
        
        $this->assertCount(2, $userSnapshot->source->posts );
        $this->assertCount(1, $userSnapshot->posts);

        $this->assertEquals($userEmailSnapshotted->email, 'lorems@noreply.com');

        UserType::where('id', 1)->update([
            'title' => 'User Type 1 Updated',
        ]);
        $userSnapshot->refresh();
        $userEmailSnapshotted->refresh();

        $userSnapshot->update([
            'userType' => 2
        ]);
        $userEmailSnapshotted->update([
            'userType' => 2
        ]);

        $userSnapshot->refresh();
        $userEmailSnapshotted->refresh();

        $this->assertEquals($userSnapshot->userType->title, 'User Type 1 Updated');
        $this->assertEquals($userEmailSnapshotted->userType->title, 'User Type 2');

        // Event::assertDispatched('eloquent.creating: ' . get_class($model));
    }

    public function test_model_creation_result()
    {
        $user = User::create([
            'user_type_id' => 1,
            'email' => 'lorems@noreply.com',
            'name' => 'lorems'
        ]);

        $model = UserSnapshot::create([
            'user_id' => $user->id,
            'name' => 'Lorem Creation'
        ]);

        $model = UserSnapshot::find($model->id);

        $snapshot = $model->snapshot;

        $this->assertEquals($model->email, $user->email);
        $this->assertEquals($model->name, 'Lorem Creation');

        $this->assertEquals($snapshot->data['id'], $user->id);
        $this->assertEquals($snapshot->data['email'], $user->email);

        $this->assertCount(0, $model->posts);
        $this->assertCount(0, $snapshot->data['posts']);

    }

    public function test_model_updating_result()
    {
        $user = User::create([
            'user_type_id' => 1,
            'email' => '1.lorems@noreply.com',
            'name' => 'lorems'
        ]);

        $user2 = User::create([
            'user_type_id' => 2,
            'email' => '2.lorems@noreply.com',
            'name' => 'lorems 2'
        ]);

        $model = UserSnapshot::create([
            'user_id' => $user->id,
            'name' => 'Lorem'
        ]);

        $model = UserSnapshot::find($model->id);

        $model->update([
            'email' => 'override@noreply.com',
            'name' => 'Lorem Custom',
        ]);

        $model = UserSnapshot::find($model->id);

        $this->assertEquals($model->snapshot->data['id'], $user->id);
        $this->assertEquals($model->snapshot->data['email'], '1.lorems@noreply.com');
        $this->assertEquals($model->email, '1.lorems@noreply.com');
        $this->assertEquals($model->name, 'Lorem Custom');

        $model->update([
            'user_id' => $user2->id,
        ]);

        $model->refresh();

        $this->assertEquals($model->snapshot->data['id'], $user2->id);
        $this->assertEquals($model->snapshot->data['email'], '2.lorems@noreply.com');
        $this->assertEquals($model->email, '2.lorems@noreply.com');
        $this->assertEquals($model->name, 'Lorem Custom');
        
    }
}
