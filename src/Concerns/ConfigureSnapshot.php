<?php

namespace Oobook\Snapshot\Concerns;

trait ConfigureSnapshot
{

    /**
     * Configuration for snapshot behavior
     */
    protected static array $defaultSnapshotConfig = [
        // Fields to snapshot (point-in-time copy)
        'snapshot_attributes' => null,

        // Attributes to keep in sync
        'synced_attributes' => null,

        // Relationships to snapshot (point-in-time copy)
        'snapshot_relationships' => null,

        // Relationships to keep in sync
        'synced_relationships' => null,
    ];

    /**
     * Get the snapshot configuration.
     *
     * @return array
     */
    final public static function getSnapshotConfig(): array
    {
        return array_merge(
            static::$defaultSnapshotConfig,
            static::$snapshotConfig ?? []
        );
    }

    public static function hasSourceAttributeToSync(): bool
    {
        $snapshotConfig = static::getSnapshotConfig();

        return $snapshotConfig['synced_attributes'] ? count($snapshotConfig['synced_attributes']) > 0 : false;
    }

    public static function getSourceAttributesToSync(): array
    {
        $snapshotConfig = static::getSnapshotConfig();

        return $snapshotConfig['synced_attributes'] ?? [];
    }

    public static function hasSourceRelationshipToSync(): bool
    {
        $snapshotConfig = static::getSnapshotConfig();

        return $snapshotConfig['synced_relationships'] ? count($snapshotConfig['synced_relationships']) > 0 : false;
    }

    public static function getSourceRelationshipsToSync(): array
    {
        $snapshotConfig = static::getSnapshotConfig();

        return $snapshotConfig['synced_relationships'] ?? [];
    }
}