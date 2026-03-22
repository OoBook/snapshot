<?php

declare(strict_types=1);

namespace Oobook\Snapshot\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Oobook\Snapshot\Models\Snapshot;

/**
 * Trait for SnapshotSynced relations to resolve source model IDs from snapshot models.
 */
trait ResolvesSnapshotSource
{
    /**
     * Get source IDs from a batch of snapshot models.
     * Ensures snapshot relation is loaded if needed.
     *
     * @param  array<Model>  $models  Snapshot models (e.g. PressReleasePackage)
     * @return array<int|string>  Source model IDs
     */
    protected function getSourceIdsFromSnapshotModels(array $models): array
    {
        $sourceIds = [];

        foreach ($models as $model) {
            $sourceId = $this->getSourceIdFromSnapshotModel($model);
            if ($sourceId !== null) {
                $sourceIds[] = $sourceId;
            }
        }

        return array_values(array_unique($sourceIds));
    }

    /**
     * Get source ID from a single snapshot model.
     *
     * @return int|string|null
     */
    protected function getSourceIdFromSnapshotModel(Model $model)
    {
        $snapshot = $model->relationLoaded('snapshot')
            ? $model->getRelation('snapshot')
            : $model->snapshot;

        if ($snapshot instanceof Snapshot) {
            return $snapshot->source_id;
        }

        $class = get_class($model);
        if (method_exists($class, 'getSnapshotSourceForeignKey')) {
            $foreignKey = $class::getSnapshotSourceForeignKey();

            return $model->getAttribute($foreignKey);
        }

        return null;
    }

    /**
     * Build a map of snapshot model key => source_id for matching.
     *
     * @param  array<Model>  $models
     * @return array<string, int|string>
     */
    protected function getSnapshotKeyToSourceIdMap(array $models): array
    {
        $map = [];

        foreach ($models as $model) {
            $sourceId = $this->getSourceIdFromSnapshotModel($model);
            if ($sourceId !== null) {
                $map[$model->getKey()] = $sourceId;
            }
        }

        return $map;
    }

    /**
     * Ensure snapshot relation is loaded on models that don't have it.
     *
     * @param  array<Model>  $models
     */
    protected function ensureSnapshotLoaded(array $models): void
    {
        if (empty($models)) {
            return;
        }

        $needsLoad = [];
        foreach ($models as $model) {
            if (! $model->relationLoaded('snapshot')) {
                $needsLoad[] = $model;
            }
        }

        if (empty($needsLoad)) {
            return;
        }

        $modelClass = get_class($models[0]);
        $ids = array_map(fn ($m) => $m->getKey(), $needsLoad);

        $snapshots = Snapshot::query()
            ->where('snapshotable_type', $modelClass)
            ->whereIn('snapshotable_id', $ids)
            ->get()
            ->keyBy('snapshotable_id');

        foreach ($needsLoad as $model) {
            $snapshot = $snapshots->get($model->getKey());
            if ($snapshot) {
                $model->setRelation('snapshot', $snapshot);
            }
        }
    }
}
