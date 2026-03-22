<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * MorphOne relation that proxies through the snapshot's source model.
 * The related model has morphType and foreignKey pointing to the source.
 */
class SnapshotSyncedMorphOne extends MorphOne
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    public function __construct(
        Builder $query,
        Model $parent,
        string $type,
        string $id,
        string $localKey,
        string $sourceClass
    ) {
        $this->sourceClass = $sourceClass;

        parent::__construct($query, $parent, $type, $id, $localKey);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
            if ($sourceId !== null) {
                $this->query->where($this->morphType, $this->morphClass)
                    ->where($this->foreignKey, '=', $sourceId)
                    ->whereNotNull($this->foreignKey);
            } else {
                $this->query->whereRaw('1 = 0');
            }
        }
    }

    public function addEagerConstraints(array $models): void
    {
        $this->ensureSnapshotLoaded($models);

        $sourceIds = $this->getSourceIdsFromSnapshotModels($models);
        if (empty($sourceIds)) {
            $this->eagerKeysWereEmpty = true;

            return;
        }

        $this->query->where($this->morphType, $this->morphClass);
        $whereIn = $this->whereInMethod($this->parent, $this->localKey);
        $this->whereInEager($whereIn, $this->foreignKey, $sourceIds, $this->getRelationQuery());
    }

    public function match(array $models, Collection $results, $relation): array
    {
        $this->ensureSnapshotLoaded($models);

        $dictionary = $this->buildDictionary($results);
        $snapshotKeyToSourceId = $this->getSnapshotKeyToSourceIdMap($models);

        foreach ($models as $model) {
            $sourceId = $snapshotKeyToSourceId[$model->getKey()] ?? null;
            if ($sourceId !== null) {
                $key = $this->getDictionaryKey($sourceId);
                if (isset($dictionary[$key])) {
                    $model->setRelation($relation, reset($dictionary[$key]));
                }
            }
        }

        return $models;
    }

    public function getResults(): mixed
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId === null) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->where($this->morphType, $this->morphClass)
            ->where($this->foreignKey, $sourceId)
            ->first() ?: $this->getDefaultFor($this->parent);
    }

    public function getParentKey(): mixed
    {
        return $this->getSourceIdFromSnapshotModel($this->parent);
    }

    protected function buildDictionary(Collection $results): array
    {
        $foreign = $this->getForeignKeyName();

        return $results->mapToDictionary(function ($result) use ($foreign) {
            return [$this->getDictionaryKey($result->{$foreign}) => $result];
        })->all();
    }
}
