<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * BelongsToMany relation that proxies through the snapshot's source model.
 * The pivot table links source (not snapshot) to related.
 */
class SnapshotSyncedBelongsToMany extends BelongsToMany
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    public function __construct(
        Builder $query,
        Model $parent,
        $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null,
        string $sourceClass = ''
    ) {
        $this->sourceClass = $sourceClass;

        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    protected function addWhereConstraints(): self
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId !== null) {
            $this->query->where($this->getQualifiedForeignPivotKeyName(), '=', $sourceId);
        } else {
            $this->query->whereRaw('1 = 0');
        }

        return $this;
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
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);
        $this->whereInEager($whereIn, $this->getQualifiedForeignPivotKeyName(), $sourceIds);
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
                    $model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
                }
            }
        }

        return $models;
    }

    public function getResults(): mixed
    {
        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId === null) {
            return $this->related->newCollection();
        }

        $this->performJoin();
        $this->query->where($this->getQualifiedForeignPivotKeyName(), '=', $sourceId);

        return $this->query->get();
    }
}
