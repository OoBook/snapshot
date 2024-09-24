<?php

namespace Oobook\Snapshot\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Oobook\Snapshot\Traits\HasSnapshot;

class UserSnapshot extends Model
{
    use HasSnapshot;

    /**
     * The source model for the snapshot.
     *
     * Required
     *
     * @var Model
     */
    public $snapshotSourceModel = User::class;

    /**
     * Fields to be excluded from cloning for both fillable and relationships.
     *
     * Optional
     *
     * @var array
     */
    public $snapshotSourceExcepts = [
        'name',
        'files'
    ];

    public $snapshotSourceRelationships = ['files', 'posts'];

    protected $fillable = ['name'];

    protected $table = 'user_snapshots';

}
