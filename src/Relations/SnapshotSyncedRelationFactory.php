<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Factory for creating SnapshotSynced relation instances from source relations.
 */
class SnapshotSyncedRelationFactory
{
    public static function make(
        Relation $sourceRelation,
        Model $snapshotParent,
        string $sourceClass,
        string $relationName
    ): Relation {
        $related = $sourceRelation->getRelated();
        $query = $related->newQuery();

        return match (get_class($sourceRelation)) {
            BelongsTo::class => new SnapshotSyncedBelongsTo(
                $query,
                $snapshotParent,
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getOwnerKeyName(),
                $relationName,
                $sourceClass
            ),
            MorphTo::class => new SnapshotSyncedMorphTo(
                $query,
                $snapshotParent,
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getOwnerKeyName() ?? $related->getKeyName(),
                $sourceRelation->getMorphType(),
                $relationName,
                $sourceClass
            ),
            HasOne::class => new SnapshotSyncedHasOne(
                $query,
                $snapshotParent,
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getLocalKeyName(),
                $sourceClass
            ),
            HasMany::class => new SnapshotSyncedHasMany(
                $query,
                $snapshotParent,
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getLocalKeyName(),
                $sourceClass
            ),
            HasOneThrough::class => new SnapshotSyncedHasOneThrough(
                $query,
                $snapshotParent,
                self::getRelationProperty($sourceRelation, 'farParent'),
                self::getRelationProperty($sourceRelation, 'throughParent'),
                $sourceRelation->getFirstKeyName(),
                self::getRelationProperty($sourceRelation, 'secondKey'),
                $sourceRelation->getLocalKeyName(),
                $sourceRelation->getSecondLocalKeyName(),
                $sourceClass
            ),
            HasManyThrough::class => new SnapshotSyncedHasManyThrough(
                $query,
                $snapshotParent,
                self::getRelationProperty($sourceRelation, 'farParent'),
                self::getRelationProperty($sourceRelation, 'throughParent'),
                $sourceRelation->getFirstKeyName(),
                self::getRelationProperty($sourceRelation, 'secondKey'),
                $sourceRelation->getLocalKeyName(),
                $sourceRelation->getSecondLocalKeyName(),
                $sourceClass
            ),
            BelongsToMany::class => new SnapshotSyncedBelongsToMany(
                $query,
                $snapshotParent,
                $sourceRelation->getTable(),
                $sourceRelation->getForeignPivotKeyName(),
                $sourceRelation->getRelatedPivotKeyName(),
                $sourceRelation->getParentKeyName(),
                $sourceRelation->getRelatedKeyName(),
                $relationName,
                $sourceClass
            ),
            MorphOne::class => new SnapshotSyncedMorphOne(
                $query,
                $snapshotParent,
                $sourceRelation->getMorphType(),
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getLocalKeyName(),
                $sourceClass
            ),
            MorphMany::class => new SnapshotSyncedMorphMany(
                $query,
                $snapshotParent,
                $sourceRelation->getMorphType(),
                $sourceRelation->getForeignKeyName(),
                $sourceRelation->getLocalKeyName(),
                $sourceClass
            ),
            MorphToMany::class => new SnapshotSyncedMorphToMany(
                $query,
                $snapshotParent,
                preg_replace('/_type$/', '', $sourceRelation->getMorphType()),
                $sourceRelation->getTable(),
                $sourceRelation->getForeignPivotKeyName(),
                $sourceRelation->getRelatedPivotKeyName(),
                $sourceRelation->getParentKeyName(),
                $sourceRelation->getRelatedKeyName(),
                $relationName,
                $sourceRelation->getInverse(),
                $sourceClass
            ),
            default => throw new \InvalidArgumentException(
                'Unsupported relation type for snapshot sync: ' . get_class($sourceRelation)
            ),
        };
    }

    /**
     * Check if the source relation type is supported.
     */
    public static function supports(Relation $sourceRelation): bool
    {
        return isset(SnapshotSyncedRelations::MAP[get_class($sourceRelation)]);
    }

    /**
     * Get a protected/private property from a relation via reflection.
     */
    private static function getRelationProperty(Relation $relation, string $property): mixed
    {
        $ref = new \ReflectionClass($relation);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($relation);
    }
}
