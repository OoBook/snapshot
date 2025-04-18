<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Snapshot\Concerns\HasSnapshot;

class UserSnapshot extends Model
{
    use HasSnapshot;

    protected $fillable = ['name'];

    protected $with = ['userType', 'fileNames'];

    /**
     * The source model for the snapshot.
     *
     * Required
     *
     * @var Model
     */
    public static $snapshotSourceModel = User::class;

    /**
     * The configuration for the snapshot.
     *
     * @var array
     */
    public static $snapshotConfig = [
        'snapshot_attributes' => [
            'email'
        ],
        'synced_attributes' => [
            'email',
            'name',
            'user_type_id',
        ],
        'snapshot_relationships' => [
            'posts'
        ],
        'synced_relationships' => [
            'userType',
            'fileNames'
        ],
    ];
}
