<?php

namespace Oobook\Snapshot\Concerns;

use Oobook\Snapshot\Models\Snapshot;

trait Relationships
{
    use SnapshotFields;

    /**
     * Defines the relationship with the snapshot model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function snapshot(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Snapshot::class, 'snapshotable');
    }

    /**
     * Defines the relationship with the source model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     * @deprecated Use snapshotSource() instead
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            static::getSnapshotSourceClass(),
            Snapshot::class,
            firstKey: 'snapshotable_id',
            secondKey: 'id',
            localKey: 'id',
            secondLocalKey: 'source_id',
        )->where('source_type', static::getSnapshotSourceClass());
    }

    /**
     * Defines the relationship with the source model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function snapshotSource(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            $this->getSnapshotSourceClass(),
            Snapshot::class,
            firstKey: 'snapshotable_id',
            secondKey: 'id',
            localKey: 'id',
            secondLocalKey: 'source_id',
        )->where('source_type', static::getSnapshotSourceClass());
    }
}