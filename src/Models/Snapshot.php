<?php

namespace Oobook\Snapshot\Models;

use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    protected $fillable = [
        'snapshotable_type',
        'snapshotable_id',
        'source_type',
        'source_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function snapshotable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function getTable()
    {
        return config('snapshot.table', parent::getTable());
    }
}
