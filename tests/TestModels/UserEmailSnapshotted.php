<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Snapshot\Concerns\HasSnapshot;

class UserEmailSnapshotted extends Model
{
    use HasSnapshot;

    protected $fillable = ['name'];

    /**
     * The source model for the snapshot.
     *
     * Required
     *
     * @var Model
     */
    public static $snapshotSourceModel = User::class;

    public static $snapshotConfig = [
        'snapshot_attributes' => [
            'email'
        ],
        'synced_attributes' => [

        ],
        'snapshot_relationships' => null,
        'synced_relationships' => null,
    ];

}
