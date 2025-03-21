<?php

namespace Oobook\Snapshot\Traits;

/**
 * Trait for creating snapshot functionality for Eloquent models.
 *
 * This trait provides methods to snapshot models, set up event listeners for model creation, updating, retrieval, and deletion, and handle relationships between source and snapshot models.
 */
trait HasSnapshot
{
    use \Oobook\Snapshot\Concerns\HasSnapshot;
}
