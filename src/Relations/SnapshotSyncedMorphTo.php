<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * MorphTo relation that proxies through the snapshot's source model.
 * Used when the snapshot model syncs a MorphTo from its source (e.g. Package->packageable).
 * Batch loads by grouping source records by morph type.
 *
 * Filters eager loads to only include relations that exist on each morph type,
 * since morph targets (e.g. PackageCountry, PackageRegion) may not share the same relations.
 */
class SnapshotSyncedMorphTo extends MorphTo
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    protected string $sourceTable;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey,
        string $type,
        string $relation,
        string $sourceClass
    ) {
        $this->sourceClass = $sourceClass;
        $this->sourceTable = (new $sourceClass)->getTable();

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * Get all of the relation results for a type.
     * Does NOT merge parent eager loads - morph targets have different schemas.
     * Only morphableEagerLoads (type-specific) are used. Keeps first-level relation only.
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $ownerKey = $this->ownerKey ?? $instance->getKeyName();

        $eagerLoads = (array) ($this->morphableEagerLoads[get_class($instance)] ?? []);

        $query = $this->replayMacros($instance->newQuery())
            ->mergeConstraintsFrom($this->getQuery())
            ->with($eagerLoads)
            ->withCount(
                (array) ($this->morphableEagerLoadCounts[get_class($instance)] ?? [])
            );

        if ($callback = ($this->morphableConstraints[get_class($instance)] ?? null)) {
            $callback($query);
        }

        $whereIn = $this->whereInMethod($instance, $ownerKey);

        return $query->{$whereIn}(
            $instance->getTable().'.'.$ownerKey,
            $this->gatherKeysByType($type, $instance->getKeyType())
        )->get();
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->ensureSnapshotLoaded($models);

        $sourceIds = $this->getSourceIdsFromSnapshotModels($models);
        if (empty($sourceIds)) {
            $this->eagerKeysWereEmpty = true;
            $this->models = Collection::make($models);
            $this->dictionary = [];

            return;
        }

        $sourceRecords = $this->sourceClass::query()
            ->whereIn((new $this->sourceClass)->getKeyName(), $sourceIds)
            ->whereNotNull($this->foreignKey)
            ->whereNotNull($this->morphType)
            ->get([(new $this->sourceClass)->getKeyName(), $this->foreignKey, $this->morphType]);

        $this->models = Collection::make($models);
        $this->dictionary = [];

        $snapshotKeyToSourceId = $this->getSnapshotKeyToSourceIdMap($models);

        foreach ($models as $model) {
            $sourceId = $snapshotKeyToSourceId[$model->getKey()] ?? null;
            if ($sourceId === null) {
                continue;
            }

            $sourceRecord = $sourceRecords->firstWhere((new $this->sourceClass)->getKeyName(), $sourceId);
            if ($sourceRecord) {
                $morphTypeKey = $this->getDictionaryKey($sourceRecord->{$this->morphType});
                $foreignKeyKey = $this->getDictionaryKey($sourceRecord->{$this->foreignKey});
                $this->dictionary[$morphTypeKey][$foreignKeyKey][] = $model;
            }
        }
    }

    /**
     * Get the results of the relationship (lazy load).
     */
    public function getResults(): mixed
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId === null) {
            return null;
        }

        $source = $this->sourceClass::find($sourceId);
        if ($source === null || $source->{$this->morphType} === null || $source->{$this->foreignKey} === null) {
            return null;
        }

        $type = $source->{$this->morphType};
        $id = $source->{$this->foreignKey};

        $instance = $this->createModelByType($type);

        return $instance->newQuery()->find($id);
    }
}
