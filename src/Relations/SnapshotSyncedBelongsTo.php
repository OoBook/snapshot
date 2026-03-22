<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * BelongsTo relation that proxies through the snapshot's source model.
 * Used when the snapshot model (e.g. PressReleasePackage) syncs a BelongsTo
 * from its source (e.g. Package->packageType).
 */
class SnapshotSyncedBelongsTo extends BelongsTo
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    protected string $sourceTable;

    public function __construct(
        Builder $query,
        Model $parent,
        string $foreignKey,
        string $ownerKey,
        string $relationName,
        string $sourceClass
    ) {
        $this->sourceClass = $sourceClass;
        $this->sourceTable = (new $sourceClass)->getTable();

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $relationName);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
            if ($sourceId !== null) {
                $source = $this->sourceClass::find($sourceId);
                $fkValue = $source?->getAttribute($this->foreignKey);
                if ($fkValue !== null) {
                    $this->query->where(
                        $this->related->qualifyColumn($this->ownerKey),
                        '=',
                        $fkValue
                    );
                } else {
                    $this->query->whereRaw('1 = 0');
                }
            } else {
                $this->query->whereRaw('1 = 0');
            }
        }
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

            return;
        }

        $sourceInstance = new $this->sourceClass;
        $sourceFkQualified = $this->sourceTable . '.' . $this->foreignKey;

        $relatedIds = $sourceInstance::query()
            ->whereIn($sourceInstance->getKeyName(), $sourceIds)
            ->whereNotNull($this->foreignKey)
            ->pluck($this->foreignKey)
            ->unique()
            ->values()
            ->all();

        if (empty($relatedIds)) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);
        $this->whereInEager(
            $whereIn,
            $this->related->qualifyColumn($this->ownerKey),
            $relatedIds
        );
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, $relation): array
    {
        $this->ensureSnapshotLoaded($models);

        $sourceIds = $this->getSourceIdsFromSnapshotModels($models);
        if (empty($sourceIds)) {
            return $models;
        }

        $sourceInstance = new $this->sourceClass;
        $sourceToFk = $sourceInstance::query()
            ->whereIn($sourceInstance->getKeyName(), $sourceIds)
            ->pluck($this->foreignKey, $sourceInstance->getKeyName())
            ->all();

        $dictionary = [];
        foreach ($results as $result) {
            $ownerKey = $this->getDictionaryKey($result->getAttribute($this->ownerKey));
            $dictionary[$ownerKey] = $result;
        }

        $snapshotKeyToSourceId = $this->getSnapshotKeyToSourceIdMap($models);

        foreach ($models as $model) {
            $sourceId = $snapshotKeyToSourceId[$model->getKey()] ?? null;
            if ($sourceId === null) {
                continue;
            }

            $fkValue = $sourceToFk[$sourceId] ?? null;
            if ($fkValue !== null) {
                $key = $this->getDictionaryKey($fkValue);
                if (isset($dictionary[$key])) {
                    $model->setRelation($relation, $dictionary[$key]);
                }
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): mixed
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId === null) {
            return $this->getDefaultFor($this->parent);
        }

        $source = $this->sourceClass::find($sourceId);
        if ($source === null) {
            return $this->getDefaultFor($this->parent);
        }

        $fkValue = $source->getAttribute($this->foreignKey);
        if ($fkValue === null) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->where($this->related->qualifyColumn($this->ownerKey), $fkValue)->first()
            ?: $this->getDefaultFor($this->parent);
    }
}
