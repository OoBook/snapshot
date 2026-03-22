<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Oobook\Snapshot\Relations\Concerns\ResolvesSnapshotSource;

/**
 * MorphToMany relation that proxies through the snapshot's source model.
 * The pivot table links source (not snapshot) to related via morph keys.
 */
class SnapshotSyncedMorphToMany extends MorphToMany
{
    use ResolvesSnapshotSource;

    protected string $sourceClass;

    public function __construct(
        Builder $query,
        Model $parent,
        string $name,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null,
        bool $inverse = false,
        string $sourceClass = ''
    ) {
        $this->sourceClass = $sourceClass;

        parent::__construct(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse
        );
    }

    protected function addWhereConstraints(): self
    {
        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);

        $sourceId = $this->getSourceIdFromSnapshotModel($this->parent);
        if ($sourceId !== null) {
            $this->query->where($this->qualifyPivotColumn($this->foreignPivotKey), '=', $sourceId);
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
        $this->query->where($this->qualifyPivotColumn($this->morphType), $this->morphClass);
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);
        $this->whereInEager($whereIn, $this->qualifyPivotColumn($this->foreignPivotKey), $sourceIds);
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
        $this->addWhereConstraints();

        return $this->query->get();
    }
}
