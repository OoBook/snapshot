<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * HasOneThrough relation that proxies through the snapshot's source model.
 * The far parent is the source; we resolve source_id from the snapshot.
 */
class SnapshotSyncedHasOneThrough extends HasOneThrough
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    protected Model $snapshotParent;

    public function __construct(
        Builder $query,
        Model $snapshotParent,
        Model $farParent,
        Model $throughParent,
        string $firstKey,
        string $secondKey,
        string $localKey,
        string $secondLocalKey,
        string $sourceClass
    ) {
        $this->sourceClass = $sourceClass;
        $this->snapshotParent = $snapshotParent;

        parent::__construct($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    public function addConstraints(): void
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->snapshotParent);
        if ($sourceId === null) {
            $this->query->whereRaw('1 = 0');

            return;
        }

        $this->performJoin();
        if (static::$constraints) {
            $this->query->where($this->getQualifiedFirstKeyName(), '=', $sourceId);
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

        $this->performJoin();
        $whereIn = $this->whereInMethod($this->farParent, $this->localKey);
        $this->whereInEager($whereIn, $this->getQualifiedFirstKeyName(), $sourceIds);
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
        $sourceId = $this->getSourceIdFromSnapshotModel($this->snapshotParent);
        if ($sourceId === null) {
            return $this->getDefaultFor($this->snapshotParent);
        }

        $this->performJoin();
        $this->query->where($this->getQualifiedFirstKeyName(), '=', $sourceId);

        return $this->query->first() ?: $this->getDefaultFor($this->snapshotParent);
    }

    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            $dictionary[$result->laravel_through_key][] = $result;
        }

        return $dictionary;
    }
}
