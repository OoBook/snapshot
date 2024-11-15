<?php

namespace Oobook\Snapshot\Traits;

use Oobook\Database\Eloquent\Concerns\ManageEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Oobook\Snapshot\Models\Snapshot;
use Illuminate\Support\Arr;


/**
 * Trait for creating snapshot functionality for Eloquent models.
 *
 * This trait provides methods to snapshot models, set up event listeners for model creation, updating, retrieval, and deletion, and handle relationships between source and snapshot models.
 */
trait HasSnapshot
{
    use ManageEloquent;

    /**
     * Cached source model fields.
     *
     * @var array
     */
    protected $_snapshotSourceFields = [];

    /**
     * Original fillable attributes.
     *
     * @var array|null
     */
    protected $_originalFillable = null;

    /**
     * The source model for the snapshot.
     *
     * Required
     *
     * @var Model
     */
    // public $snapshotSourceModel = Test::class;

    /**
     * Fillable attributes to be copied from the source model.
     *
     * Optional
     *
     * @var array
     */
    // public $snapshotSourceFillable = [];

    /**
     * Fields to be excluded from snapshots.
     *
     * Optional
     *
     * @var array
     */
    // public $snapshotSourceExcepts = [];

    /**
     * Relationships to add to snapshot data.
     *
     * Optional
     *
     * @var array
     */
    // public $snapshotSourceRelationships = [];

    /**
     * Relationships not to add to snapshot data.
     *
     * Optional
     *
     * @var array
     */
    // public $snapshotSourceRelationshipsExcepts = [];

    /**
     * Boot the trait.
     *
     * Sets up event listeners for model creation, updating, retrieval, and deletion.
     *
     * @return void
     */
    public static function bootHasSnapshot()
    {
        self::saving(function ($model) {
            $snapshot = $model->snapshot;
            $snapshotSourceChanged = false;
            if($snapshot && $snapshot->source_id != $model->{$model->getSourceForeignKey()}){
                $snapshotSourceChanged = true;
                $snapshot->update(
                    [
                        'source_id' => $model->{$model->getSourceForeignKey()},
                        'data' => ['id' => $model->{$model->getSourceForeignKey()} ],
                    ]
                );
            }
            foreach (array_keys($model->_snapshotSourceFields) as $field) {
                if (!in_array($field, $model->getColumns()) && isset($model->{$field})) {
                    $shouldUpdateSnapshot = !$snapshotSourceChanged ||
                        json_encode($model->_snapshotSourceFields[$field] ?? null) !== json_encode($model->{$field});

                    if ($shouldUpdateSnapshot) {
                        $model->_snapshotSourceFields[$field] = $model->{$field};
                    } else {
                        unset($model->_snapshotSourceFields[$field]);
                    }
                    $model->offsetUnset($field);
                }
            }
        });

        self::creating(function ($model) {
            foreach ($model->getSourceFields() as $field) {
                if (isset($model->{$field})) {
                    $model->_snapshotSourceFields[$field] = $model->{$field};
                    $model->offsetUnset($field);
                    $model->fillable = array_diff($model->fillable, [$model->{$field}]);
                }
            }
            return true;
        });
        self::updating(function ($model) {
            foreach ($model->getSourceFields() as $field) {
                if (isset($model->{$field})) {
                    $model->_snapshotSourceFields[$field] = $model->{$field};
                    $model->offsetUnset($field);
                    $model->fillable = array_diff($model->fillable, [$model->{$field}]);
                }
            }
            return true;
        });

        static::saved(function ($model) {
            $model->saveSnapshot();

            $model->fillable = $model->_originalFillable;
        });

        static::retrieved(function ($model) {

            if (($foreignKey = $model->getSourceForeignKey())) {
                $snapshot = $model->getSnapshot();
                $source = $model->source()->with($model->getSnapshotSourceRelationships())->first();

                $fields = array_merge($source->toArray(), $snapshot->getAttributes());

                foreach ($fields as $fieldName => $value) {
                    if (!$model->{$fieldName}) {
                        $model->_snapshotSourceFields[$fieldName] = $value;
                        $model->setAttribute($fieldName, $value);
                    }
                }

                $model[$foreignKey] = $model->snapshot->source_id;
                $model->_snapshotSourceFields[$foreignKey] = $model[$foreignKey];
            }
        });
    }

    /**
     * Initialize the trait.
     *
     * Sets up the snapshot source model and merges fillable attributes.
     *
     * @return void
     */
    public function initializeHasSnapshot()
    {
        if (!isset($this->snapshotSourceModel)) {
            throw new \Exception(static::class . ' must have a $snapshotSourceModel public property.');
        }

        $this->_originalFillable = $this->getFillable();

        $this->mergeFillable($this->getSourceFields());
    }

    /**
     * Defines the relationship with the snapshot model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function snapshot(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Snapshot::class, 'snapshotable');
    }

    /**
     * Defines the relationship with the source model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            $this->getSource(),
            Snapshot::class,
            firstKey: 'snapshotable_id',
            secondKey: 'id',
            localKey: 'id',
            secondLocalKey: 'source_id',
        );
    }

    /**
     * Saves the snapshot.
     *
     * @return void
     */
    public function saveSnapshot()
    {
        $source = $this->getSourceRecord();

        $res = $this->snapshot()->updateOrCreate(
            [
                'snapshotable_id' => $this->id,
                'snapshotable_type' => get_class($this),
                'source_type' => get_class($source),
                'source_id' => $source->id,
            ],
            [

            ]
        );

        $this->snapshot()->update([
            'data' => $this->getSnapshotData()
        ]);
    }

    /**
     * Get snapshot data.
     *
     * @param \Illuminate\Database\Eloquent\Model $sourceModel
     * @return array
     */
    public function getSnapshotData(): array
    {
        $source = $this->source;

        $data = array_merge($source->toArray(), $this->snapshot?->data ?? []);

        foreach ($this->getSnapshotSourceFillable() as $field) {
            $data[$field] = $this->_snapshotSourceFields[$field] ?? $data[$field];
        }

        foreach ($this->getSnapshotSourceRelationships() as $relationshipName) {
            $relatedClass = $source->{$relationshipName}()->getRelated();

            if ($this::class === get_class($relatedClass)) {
                continue;
            }

            $valueOnModel = $this->_snapshotSourceFields[$relationshipName] ?? null;

            $serializedData = null;

            // if relationshipName exists on payload, get this value but not real relationship
            if ($valueOnModel) {
                if ($valueOnModel instanceof \Illuminate\Database\Eloquent\Collection) {
                    $valueOnModel = $valueOnModel->toArray();
                }

                $oldValue = isset($data[$relationshipName])
                    ? $data[$relationshipName]
                    : $source->{$relationshipName};

                if (json_encode($valueOnModel) != json_encode($oldValue)) {
                    if (is_array($valueOnModel)) {
                        if (count($valueOnModel) > 0) {
                            $serializedData = $source->{$relationshipName}()->getRelated()->whereIn('id', $valueOnModel)->get();
                        }
                    } else {
                        $serializedData = $source->{$relationshipName}()->getRelated()->where('id', $valueOnModel)->first();
                    }
                } else {
                    $serializedData = $oldValue;
                }

            } else {
                $serializedData = $source->{$relationshipName};
            }

            if ($serializedData) {
                $data[$relationshipName] = $serializedData;
                $serializedData = null;
            }
        }

        return array_filter($data, fn ($key) => !in_array($key, $this->getSnapshotSourceExcepts()), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Gets the snapshot model.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getSnapshot()
    {
        $snapshot = $this->snapshot;

        if (!$snapshot) {
            return null;
        }

        $sourceModelClass = $snapshot->source_type;

        $data = $snapshot->data;

        return $this->castToEloquent($data, $sourceModelClass);
    }

    /**
     * Casts data to an Eloquent model.
     *
     * @param array $data
     * @param string $modelClass
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function castToEloquent(array $data, string $modelClass)
    {
        $model = new $modelClass();
        $model->setRawAttributes($data);

        foreach ($data as $key => $value) {
            if (method_exists($model, $key) && $model->$key() instanceof Relation) {
                $relatedModelClass = get_class($model->$key()->getRelated());
                if (is_array($value) && !isset($value[0])) {
                    $model->setRelation($key, $this->castToEloquent($value, $relatedModelClass));
                } elseif (is_array($value)) {
                    $model->setRelation($key, collect($value)->map(function ($item) use ($relatedModelClass) {
                        return $this->castToEloquent($item, $relatedModelClass);
                    }));
                }
            }
        }

        return $model;
    }

    /**
     * Gets the source model.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getSource()
    {
        return $this->snapshotSourceModel ?? null;
    }

    /**
     * Gets the source model record.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getSourceRecord()
    {
        $class = $this->getSource();

        if (!$class) {
            return null;
        }

        $sourceModelForeignKey = $this->getSourceForeignKey();
        $sourceModelOwnerId = $this->_snapshotSourceFields[$sourceModelForeignKey];

        return $class::find($sourceModelOwnerId);
    }

    /**
     * Gets the source model fields.
     *
     * @return array
     */
    protected function getSourceFields()
    {
        $this->_originalFillable ??= $this->getFillable();

        $fillable = array_values(array_diff(
            $this->getSnapshotSourceFillable(),
            $this->getColumns()
        ));

        foreach ($this->getSnapshotSourceRelationships() as $relationshipName) {
            $fillable[] = $relationshipName;
        }

        $fillable[] = $this->getSourceForeignKey();

        return $fillable;
    }

    /**
     * Gets the source model foreign key.
     *
     * @return string|null
     */
    public function getSourceForeignKey()
    {
        $class = $this->getSource();

        if (!$class) {
            return null;
        }

        $instance = new $class();

        return $instance->getForeignKey();
    }

    /**
     * Gets defined relationships on default.
     *
     * @return array
     */
    public function getSnapshotSourceRelationships(): array
    {
        return array_values(array_diff(
            $this->snapshotSourceRelationships ?? [],
            $this->getSnapshotSourceRelationshipExcepts()
        ));
    }

    /**
     * Gets the snapshot source model fillable attributes.
     *
     * @return array
     */
    public function getSnapshotSourceFillable(): array
    {
        $class = $this->getSource();
        $instance = new $class();

        return array_values(
            array_diff(
                $instance->getFillable(),
                $this->snaphotSourceFillableExcept ?? []
            )
        );
    }

    /**
     * getSnapshotSourceRelationshipExcepts
     *
     * @return array
     */
    protected function getSnapshotSourceRelationshipExcepts(): array
    {
        return array_values(
            array_unique(
                array_merge($this->snaphotSourceRelationshipsExcepts ?? [], $this->getSnapshotSourceExcepts())
            )
        );
    }
    /**
     * getSnapshotSourceExcepts
     *
     * @return array
     */
    protected function getSnapshotSourceExcepts(): array
    {
        return $this->snapshotSourceExcepts ?? [];
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if(!$this->hasColumn($method) && method_exists($this->getSource(), $method)){
            $sourceClass = $this->getSource();

            $relationClassesPattern = "|" . preg_quote(config('manage-eloquent.relations_namespace'), "|") . "|";

            $methodReflector = new \ReflectionMethod($sourceClass, $method);

            if($methodReflector->hasReturnType() && preg_match("{$relationClassesPattern}", $methodReflector->getReturnType() )){

                $query = $this->source->{$method}();

                $value = $this->{$method};
                if(isset($value)){

                    if (is_array($value) && !Arr::isAssoc($value)) { // multiple relationship
                        $query = $query->whereIn('id', Arr::map($value, fn($v) => $v['id']));

                    }else if(is_array($value) && Arr::isAssoc($value)) { // single relationship
                        $query = $query->where('id', $value['id']);
                    }
                }

                return $query;
            }
        }

        return parent::__call($method, $parameters);
    }
}
