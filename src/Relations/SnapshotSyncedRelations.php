<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

/**
 * Registry of SnapshotSynced relation classes mapped to Laravel relation types.
 */
final class SnapshotSyncedRelations
{
    public const MAP = [
        \Illuminate\Database\Eloquent\Relations\BelongsTo::class => SnapshotSyncedBelongsTo::class,
        \Illuminate\Database\Eloquent\Relations\HasOne::class => SnapshotSyncedHasOne::class,
        \Illuminate\Database\Eloquent\Relations\HasMany::class => SnapshotSyncedHasMany::class,
        \Illuminate\Database\Eloquent\Relations\HasOneThrough::class => SnapshotSyncedHasOneThrough::class,
        \Illuminate\Database\Eloquent\Relations\HasManyThrough::class => SnapshotSyncedHasManyThrough::class,
        \Illuminate\Database\Eloquent\Relations\BelongsToMany::class => SnapshotSyncedBelongsToMany::class,
        \Illuminate\Database\Eloquent\Relations\MorphTo::class => SnapshotSyncedMorphTo::class,
        \Illuminate\Database\Eloquent\Relations\MorphOne::class => SnapshotSyncedMorphOne::class,
        \Illuminate\Database\Eloquent\Relations\MorphMany::class => SnapshotSyncedMorphMany::class,
        \Illuminate\Database\Eloquent\Relations\MorphToMany::class => SnapshotSyncedMorphToMany::class,
    ];
}
