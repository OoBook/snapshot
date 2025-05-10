<?php

namespace Oobook\Snapshot\Concerns;

trait SnapshotFields
{

    /**
     * Gets the source model.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected static function getSnapshotSourceClass()
    {
        try {
            $value = static::$snapshotSourceModel;
            if (is_null($value)) {
                throw new \Exception(static::class . ' must have a $snapshotSourceModel public static property.');
            }

            return $value;
        } catch (\Error $e) {
            throw new \Exception(static::class . ' must have a $snapshotSourceModel public static property.');
        }
    }

    /**
     * Get the reserved attributes on own against snapshot.
     *
     * @return array
     */
    final public function getReservedAttributesAgainstSnapshot(): array
    {
        $this->originalFillableForSnapshot ??= $this->getFillable();

        $selfFillable = $this->originalFillableForSnapshot;
        $selfTimestampColumns = $this->getTimestampColumns();
        $selfMutatedAttributes = $this->getMutatedAttributes();
        $selfRelationships = $this->definedRelations();
        $selfPrimaryKey = $this->getKeyName();

        return array_values(array_unique(array_merge(
            [$selfPrimaryKey],
            $selfFillable,
            $selfTimestampColumns,
            $selfMutatedAttributes,
            $selfRelationships
        )));
    }

    /**
     * Gets the source model foreign key.
     *
     * @return string|null
     */
    public static function getSnapshotSourceForeignKey()
    {
        $class = static::getSnapshotSourceClass();

        if (!$class) {
            return null;
        }

        $instance = new $class();

        return $instance->getForeignKey();
    }

    /**
     * Gets the snapshotable source attributes.
     *
     * @return array
     */
    public static function getSnapshotableSourceAttributes(): array
    {
        $sourceClass = new (static::getSnapshotSourceClass());

        $sourceFillable = $sourceClass->getFillable();
        // $sourceColumns = $sourceClass->getTableColumns();
        $sourceMutatedAttributes = $sourceClass->getMutatedAttributes();
        $sourceTimestampColumns = $sourceClass->getTimestampColumns();

        return array_merge(
            $sourceFillable,
            $sourceMutatedAttributes,
            $sourceTimestampColumns
        );
    }

    /**
     * Gets the snapshotable source attributes.
     *
     * @return array
     */
    public static function getSnapshotableSourceRelationships(): array
    {
        $sourceClass = new (static::getSnapshotSourceClass());

        return class_uses_recursive($sourceClass)['Oobook\Database\Eloquent\Concerns\ManageEloquent']
            ? $sourceClass->definedRelations()
            : [];
    }

    /**
     * Checks if the source model has attributes to snapshot.
     *
     * @return bool
     */
    public function hasSourceAttributeToSnapshot(): bool
    {
        $sourceAttributesToSnapshot = $this->getSourceAttributesToSnapshot();

        return count($sourceAttributesToSnapshot) > 0;
    }

    /**
     * Gets the source attributes to snapshot.
     *
     * @return array
     */
    public function getSourceAttributesToSnapshot(): array
    {
        $snapshotConfig = $this->getSnapshotConfig();
        $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();
        $sourceAllAttributes = $this->getSnapshotableSourceAttributes();

        if(isset($snapshotConfig['snapshot_attributes']) && is_array($snapshotConfig['snapshot_attributes'])){
            $sourceAllAttributes = array_values(array_intersect($snapshotConfig['snapshot_attributes'], $sourceAllAttributes));
        }

        if(isset($snapshotConfig['synced_attributes']) && is_array($snapshotConfig['synced_attributes'])){
            $sourceAllAttributes = array_values(array_diff($sourceAllAttributes, $snapshotConfig['synced_attributes']));
        }

        return array_values(array_diff(
            $sourceAllAttributes,
            $reservedAttributes
        ));
    }

    /**
     * Checks if the source model has relationships to snapshot.
     *
     * @return bool
     */
    public function hasSourceRelationshipToSnapshot(): bool
    {
        $sourceRelationshipsToSnapshot = $this->getSourceRelationshipsToSnapshot();

        return count($sourceRelationshipsToSnapshot) > 0;
    }

    /**
     * Gets the source relationships to snapshot.
     *
     * @return array
     */
    public function getSourceRelationshipsToSnapshot(): array
    {
        $sourceClass = new ($this->getSnapshotSourceClass());
        $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();
        $snapshotConfig = $this->getSnapshotConfig();

        $relationships = class_uses_recursive($sourceClass)['Oobook\Database\Eloquent\Concerns\ManageEloquent']
            ? $sourceClass->definedRelations()
            : [];

        if(isset($snapshotConfig['snapshot_relationships']) && is_array($snapshotConfig['snapshot_relationships'])){
            $relationships = array_values(array_intersect($snapshotConfig['snapshot_relationships'], $relationships));
        }

        if(isset($snapshotConfig['synced_relationships']) && is_array($snapshotConfig['synced_relationships'])){
            $relationships = array_values(array_diff($relationships, $snapshotConfig['synced_relationships']));
        }

        return array_values(array_diff(
            $relationships,
            $reservedAttributes
        ));
    }

    /**
     * Checks if a field is snapshot synced.
     *
     * @param string $field
     * @return bool
     */
    public function fieldIsSnapshotSynced(string $field): bool
    {
        $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();

        if(in_array($field, $reservedAttributes) || !$this->hasSourceAttributeToSync()){
            return false;
        }

        return in_array($field, $this->getSourceAttributesToSync());
    }

    /**
     * Checks if a relationship is snapshot synced.
     *
     * @param string $relationship
     * @return bool
     */
    public function relationshipIsSnapshotSynced(string $relationship): bool
    {
        $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();

        if(in_array($relationship, $reservedAttributes) || !$this->hasSourceRelationshipToSync()){
            return false;
        }

        return in_array($relationship, $this->getSourceRelationshipsToSync());
    }

    /**
     * Checks if a field is snapshotted.
     *
     * @param string $field
     * @return bool
     */
    public function isSnapshotAttribute(string $field): bool
    {
        $sourceAttributesToSnapshot = $this->getSourceAttributesToSnapshot();

        return in_array($field, $sourceAttributesToSnapshot);
    }

    /**
     * Checks if a relationship is snapshotted.
     *
     * @param string $relationship
     * @return bool
     */
    public function isSnapshotRelationship(string $relationship): bool
    {
        $sourceRelationshipsToSnapshot = $this->getSourceRelationshipsToSnapshot();

        return in_array($relationship, $sourceRelationshipsToSnapshot);
    }

    /**
     * Gets the fillable for snapshot
     *
     * @return array
     */
    protected function getFillableForSnapshot() : array
    {
        $this->originalFillableForSnapshot ??= $this->getFillable();

        $sourceDemandedAttributes = $this->getSourceAttributesToSnapshot();
        $sourceDemandedRelationships = $this->getSourceRelationshipsToSnapshot();

        $fillableForSource = array_values(array_unique(array_merge(
            $sourceDemandedAttributes,
            $sourceDemandedRelationships,
        )));

        $fillableForSource[] = $this->getSnapshotSourceForeignKey();

        return $fillableForSource;
    }
}