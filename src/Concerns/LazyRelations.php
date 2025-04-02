<?php

namespace Oobook\Snapshot\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Closure;

trait LazyRelations
{
    use ConfigureSnapshot, SnapshotFields;

    /**
     * Override the load method to handle snapshot relationships
     *
     * @param mixed $relations
     * @return $this
     */
    public function load($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        // Parse relations to handle nested and constrained relations
        $parsedRelations = $this->parseRelationsForLoad($relations);

        $selfRelationships = $this->definedRelations();
        $snapshotableRelationships = $this->getSnapshotableSourceRelationships();

        // Separate snapshot relations from regular relations
        $snapshotRelations = [];
        $regularRelations = [];

        foreach ($parsedRelations as $name => $constraints) {
            $baseRelation = $this->getBaseRelationName($name);

            if (in_array($baseRelation, $selfRelationships) || $baseRelation === 'snapshot') {
                $regularRelations[$name] = $constraints;
            } else if (in_array($baseRelation, $snapshotableRelationships)) {
                $snapshotRelations[$name] = $constraints;
            }
        }

        // Always load snapshot
        if (!isset($regularRelations['snapshot'])) {
            $regularRelations['snapshot'] = function() {};
        }

        // Load regular relations using parent method
        if (!empty($regularRelations)) {
            parent::load($regularRelations);
        }

        // Handle snapshot relations
        if (!empty($snapshotRelations) && $this->snapshot) {
            $this->loadSnapshotRelations($snapshotRelations);
        }

        return $this;
    }

    /**
     * Parse relations for load method to handle nested and constrained relations
     *
     * @param array $relations
     * @return array
     */
    protected function parseRelationsForLoad(array $relations)
    {
        $parsed = [];

        foreach ($relations as $name => $constraints) {
            // If the key is numeric, the relation was passed without constraints
            if (is_numeric($name)) {
                if ($constraints instanceof Closure) {
                    // This is a constrained relation in the format [$relation => function() {}]
                    foreach ($this->extractConstrainedRelations($constraints) as $constrainedName => $constrainedConstraints) {
                        $parsed[$constrainedName] = $constrainedConstraints;
                    }
                } else {
                    // This is a simple relation name
                    $parsed[$constraints] = function() {};
                }
            } else {
                // This is a constrained relation in the format ['relation' => function() {}]
                $parsed[$name] = $constraints;
            }
        }

        return $parsed;
    }

    /**
     * Extract constrained relations from a closure
     *
     * @param Closure $closure
     * @return array
     */
    protected function extractConstrainedRelations(Closure $closure)
    {
        $relations = [];

        // Create a mock query builder to capture the relation name
        $mock = new class {
            public $relationName;
            
            public function __call($method, $args)
            {
                $this->relationName = $method;
                return $this;
            }
            
            // Add this to handle method chaining
            public function where() { return $this; }
            public function whereIn() { return $this; }
            public function whereHas() { return $this; }
            public function orWhere() { return $this; }
            public function orderBy() { return $this; }
            public function with() { return $this; }
        };

        $result = $closure($mock);

        if ($mock->relationName) {
            $relations[$mock->relationName] = $closure;
        }

        return $relations;
    }

    /**
     * Get the base relation name without nested parts
     *
     * @param string $relation
     * @return string
     */
    protected function getBaseRelationName(string $relation)
    {
        return explode('.', $relation)[0];
    }

    /**
     * Load snapshot relations from snapshot data
     *
     * @param array $relations
     * @return void
     */
    protected function loadSnapshotRelations(array $relations)
    {
        $snapshot = $this->snapshot;

        if (!$snapshot || empty($snapshot->data)) {
            return;
        }

        $sourceRelationshipsToSnapshot = $this->getSourceRelationshipsToSnapshot();
        $sourceClass = $this->getSnapshotSourceClass();
        $sourceInstance = new $sourceClass();

        $snapshotData = $snapshot->data;

        $source = $this->source;

        $sourceBaseRelations = [];
        foreach ($relations as $relation => $constraints) {
            // Get base relation name without constraints
            $baseRelation = $this->getBaseRelationName($relation);

            // if($baseRelation === 'userType'){
            //     dd($relation, $constraints, $this->getRelations());
            // }

            // Skip if relation is already loaded
            // if ($this->relationLoaded($baseRelation)) {
            //     continue;
            // }

            // Get relation data from snapshot
            $relationData = $snapshotData[$baseRelation] ?? $snapshot->data[Str::snake($baseRelation)] ?? null;

            if (in_array($baseRelation, $sourceRelationshipsToSnapshot) && $relationData) {
                $relationInstance = $sourceInstance->{$baseRelation}();
                $relatedModelClass = get_class($relationInstance->getRelated());
                $relatedModel = new $relatedModelClass();

                $relatedModelRelations = method_exists($relatedModel, 'definedRelations')
                    ? $relatedModel->definedRelations()
                    : [];

                $arrayableRelations = array_filter($relatedModelRelations, function($relation) use ($relatedModel) {
                    return $this->isArrayableRelationship($relatedModel, $relation);
                });

                // Handle different relation types
                if (is_array($relationData) && !Arr::isAssoc($relationData)) {
                    // Many relationship
                    $relatedModels = collect($relationData)->map(function ($item) use ($relatedModelClass, $arrayableRelations) {
                        $relatedModel = new $relatedModelClass();

                        foreach($arrayableRelations as $arrayableRelation) {
                            if(isset($item[$arrayableRelation])) {
                                $item[$arrayableRelation] = collect($item[$arrayableRelation])->map(function($item) use ($relatedModel, $arrayableRelation) {
                                    $modelInstance = $relatedModel->{$arrayableRelation}()->getRelated();
                                    $modelInstance->fill($item);
                                    return $modelInstance;
                                });
                            }
                        }

                        return $relatedModel->newFromBuilder($item);
                    });

                    // Apply constraints if provided
                    if ($constraints instanceof Closure) {
                        $relatedModels = $relatedModels->filter(function($model) use ($constraints) {
                            $result = true;
                            $constraints(new class($model, $result) {
                                protected $model;
                                protected $result;

                                public function __construct($model, &$result) {
                                    $this->model = $model;
                                    $this->result = $result;
                                }

                                public function where($column, $operator = null, $value = null) {
                                    // Simple implementation for common where clause
                                    if ($value === null) {
                                        $value = $operator;
                                        $operator = '=';
                                    }

                                    $actualValue = $this->model->{$column};

                                    switch ($operator) {
                                        case '=':
                                            $this->result = $this->result && $actualValue == $value;
                                            break;
                                        case '!=':
                                        case '<>':
                                            $this->result = $this->result && $actualValue != $value;
                                            break;
                                        case '>':
                                            $this->result = $this->result && $actualValue > $value;
                                            break;
                                        case '>=':
                                            $this->result = $this->result && $actualValue >= $value;
                                            break;
                                        case '<':
                                            $this->result = $this->result && $actualValue < $value;
                                            break;
                                        case '<=':
                                            $this->result = $this->result && $actualValue <= $value;
                                            break;
                                    }

                                    return $this;
                                }

                                public function __call($method, $args) {
                                    return $this;
                                }
                            });

                            return $result;
                        });
                    }

                    $this->setRelation($baseRelation, $relatedModels);
                } else if (is_array($relationData) && Arr::isAssoc($relationData)) {
                    // Single relationship
                    $relatedModel = new $relatedModelClass();

                    foreach($arrayableRelations as $arrayableRelation) {
                        if(isset($relationData[$arrayableRelation])) {
                            $relationData[$arrayableRelation] = collect($relationData[$arrayableRelation])->map(function($item) use ($relatedModel, $arrayableRelation) {
                                $modelInstance = $relatedModel->{$arrayableRelation}()->getRelated();
                                $modelInstance->fill($item);
                                return $modelInstance;
                            });
                        }
                    }

                    $model = $relatedModel->newFromBuilder($relationData);

                    // For single models, we don't filter with constraints as it would be all or nothing
                    $this->setRelation($baseRelation, $model);
                }
            } else {
                // If not in snapshot data, load from source
                $source->load([$relation => $constraints]);
                $sourceBaseRelations[] = $baseRelation;
            }
        }

        if(count($sourceBaseRelations) > 0){
            foreach($sourceBaseRelations as $sourceBaseRelation){
                $this->setRelation($sourceBaseRelation, $source->{$sourceBaseRelation});
            }
        }
    }

    /**
     * Override loadMissing to handle snapshot relationships
     *
     * @param mixed $relations
     * @return $this
     */
    public function loadMissing($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        // Filter out already loaded relations
        $missingRelations = [];

        foreach ($relations as $key => $value) {
            if (is_numeric($key)) {
                if (!$this->relationLoaded($value)) {
                    $missingRelations[] = $value;
                }
            } else {
                if (!$this->relationLoaded($key)) {
                    $missingRelations[$key] = $value;
                }
            }
        }

        if (empty($missingRelations)) {
            return $this;
        }

        return $this->load($missingRelations);
    }

    /**
     * Override loadCount to handle snapshot relationships
     *
     * @param mixed $relations
     * @return $this
     */
    public function loadCount($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        $sourceRelationshipsToSnapshot = $this->getSourceRelationshipsToSnapshot();
        $regularRelations = [];
        $snapshotRelations = [];

        foreach ($relations as $key => $relation) {
            $baseRelation = is_numeric($key) ? $relation : $key;
            $baseRelation = $this->getBaseRelationName($baseRelation);

            if (in_array($baseRelation, $sourceRelationshipsToSnapshot)) {
                $snapshotRelations[] = $baseRelation;
            } else {
                if (is_numeric($key)) {
                    $regularRelations[] = $relation;
                } else {
                    $regularRelations[$key] = $relation;
                }
            }
        }

        // Handle snapshot relation counts
        if (!empty($snapshotRelations) && $this->snapshot) {
            foreach ($snapshotRelations as $relation) {
                if (isset($this->snapshot->data[$relation])) {
                    $relationData = $this->snapshot->data[$relation];
                    $count = is_array($relationData) && !Arr::isAssoc($relationData) ? count($relationData) : 1;
                    $this->setAttribute($relation.'_count', $count);
                }
            }
        }

        // Handle regular relation counts
        if (!empty($regularRelations)) {
            parent::loadCount($regularRelations);
        }

        return $this;
    }

    /**
     * Check if a relation is arrayable
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $relation
     * @return bool
     */
    public function isArrayableRelationship($model, $relation)
    {
        $relationshipType = get_class($model->{$relation}());

        return in_array($relationshipType, [
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
        ]);
    }
}
