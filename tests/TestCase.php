<?php

namespace Oobook\Snapshot\Tests;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Oobook\Database\Eloquent\ManageEloquentServiceProvider;
use Oobook\Snapshot\SnapshotServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Note: this also flushes the cache from within the migration
        $this->setUpDatabase($this->app);

    }

    protected function getPackageProviders($app)
    {
      return [
        SnapshotServiceProvider::class,
        ManageEloquentServiceProvider::class,
      ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testdb');
        $app['config']->set('database.connections.testdb', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.prefix', 'spatie_tests---');
        $app['config']->set('cache.default', getenv('CACHE_DRIVER') ?: 'array');

    }

    /**
     * Set up the database.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function setUpDatabase($app)
    {
        $schema = $app['db']->connection()->getSchemaBuilder();

        $schema->create('snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->uuidMorphs('snapshotable');
            $table->uuidMorphs('source');
            $table->json('data')->default(new Expression('(JSON_ARRAY())'));
            $table->timestamps();
        });

        $schema->create('user_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('user_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });


        $schema->create('user_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('user_email_snapshotteds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('content');
            $table->timestamps();
        });

        $schema->create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->uuidMorphs('fileable');
            $table->string('name');
        });
    }

    public static function callMethod($obj, $name, array $args) {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        // $method->setAccessible(true); // Use this if you are running PHP older than 8.1.0
        return $method->invokeArgs($obj, $args);
    }
}
