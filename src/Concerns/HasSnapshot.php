<?php

namespace Oobook\Snapshot\Concerns;

use Oobook\Database\Eloquent\Concerns\ManageEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Oobook\Snapshot\Models\Snapshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trait for creating snapshot functionality for Eloquent models.
 *
 * This trait provides methods to snapshot models, set up event listeners for model creation, updating, retrieval, and deletion, and handle relationships between source and snapshot models.
 */
trait HasSnapshot
{
    use ManageEloquent,
        SnapshotFields,
        ConfigureSnapshot,
        Relationships;

    /**
     * Cached snapshot data
     */
    protected array $snapshotSavingData = [];

    /**
     * Original fillable attributes.
     *
     * @var array|null
     */
    protected $originalFillableForSnapshot = null;

    protected $withSnapshot = [];

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
            $snapshotSourceForeignKey = $model->getSnapshotSourceForeignKey();

            if( $model->getAttribute($snapshotSourceForeignKey) 
                && $snapshot 
                && ((int)$snapshot->source_id != (int)$model->getAttribute($snapshotSourceForeignKey))
            ){ // updating snapshot source
                $snapshotSourceChanged = true;
                $snapshot->update(
                    [
                        'source_id' => $model->getAttribute($snapshotSourceForeignKey),
                        'data' => ['id' => $model->getAttribute($snapshotSourceForeignKey) ],
                    ]
                );
            }

            foreach ($model->getFillableForSnapshot() as $field) { // moving snapshot data to snapshotSavingData from attributes
                $attribute = $model->getAttribute($field);
                if (isset($attribute)) {
                    $valueFromFillable = $attribute;

                    if(!($attribute instanceof Collection || $attribute instanceof Model)){
                        $model->snapshotSavingData[$field] = $attribute;
                    }
                    $model->offsetUnset($field);
                }
            }

            if($snapshot){
                foreach (array_keys($model->snapshotSavingData) as $field) { // whether to update snapshot fields individually
                    if (!in_array($field, $model->getColumns()) && isset($model->{$field})) {
                        $shouldUpdateSnapshot = !$snapshotSourceChanged ||
                            json_encode($model->snapshotSavingData[$field] ?? null) !== json_encode($model->{$field});

                        if ($shouldUpdateSnapshot) {
                            $model->snapshotSavingData[$field] = $model->{$field};
                        } else {
                            unset($model->snapshotSavingData[$field]);
                        }
                    }
                }
            }


        });

        static::saved(function ($model) {
            $model->saveSnapshot();

            if(!$model->snapshot){
                $model->refresh();
            }
            // $model->fillable = $model->originalFillableForSnapshot;
        });

        $sourceClass = static::getSnapshotSourceClass();
        $sourceInstance = new $sourceClass();
        $syncedSourceRelationships = static::getSourceRelationshipsToSync();

        foreach ($syncedSourceRelationships as $relationship) {
            if(!method_exists(static::class, $relationship)){
                static::resolveRelationUsing($relationship, function ($snapshotable) use ($sourceInstance, $relationship) {
                    $source = $snapshotable->source;

                    if($source){
                        return $source->{$relationship}();
                    }

                    return $sourceInstance->{$relationship}();
                });
            }
        }

        // if(method_exists($sourceInstance, 'definedRelationsTypes')){
        // }

        // static::resolveRelationUsing('source', function ($model) {
        //     return $model->hasOneThrough(Snapshot::class, Snapshot::class, 'snapshotable_id', 'id', 'id', 'source_id');
        // });
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
        $this->mergeFillable($this->getFillableForSnapshot());

        $this->withSnapshot = array_values(array_intersect($this->with, $this->getSourceRelationshipsToSnapshot()));

        $this->with = array_merge(array_values(array_diff($this->with, $this->withSnapshot)), ['snapshot']);
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
     * Gets the source model record.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getSnapshotSource()
    {
        $class = $this->getSnapshotSourceClass();

        if (!$class) {
            return null;
        }

        $snapshotSourceForeignKey = $this->getSnapshotSourceForeignKey();

        if(isset($this->snapshotSavingData[$snapshotSourceForeignKey])){
            $sourceModelOwnerId = $this->snapshotSavingData[$snapshotSourceForeignKey];

            return $class::find($sourceModelOwnerId);
        }

        return $this->source;
    }

    /**
     * Prepare data to snapshot.
     *
     * @param \Illuminate\Database\Eloquent\Model $sourceModel
     * @return array
     */
    public function prepareDataToSnapshot(): array
    {
        $source = $this->snapshotSource()->with($this->getSourceRelationshipsToSnapshot())->first();

        // Instead of using toArray(), we'll build the array manually to preserve relationship names
        $data = $source->attributesToArray();

        // Add relationships with original names
        foreach ($this->getSourceRelationshipsToSnapshot() as $relation) {
            if ($source->relationLoaded($relation)) {
                $data[$relation] = $source->getRelation($relation);
            }
        }

        $data = array_merge($data, $this->snapshot?->data ?? []);

        foreach ($this->getSourceAttributesToSnapshot() as $field) {
            $value = $this->snapshotSavingData[$field] ?? $data[$field] ?? null;
            $data[$field] = $value;
        }

        foreach ($this->getSourceRelationshipsToSnapshot() as $relationshipName) {
            $relatedClass = $source->{$relationshipName}()->getRelated();

            if ($this::class === get_class($relatedClass)) {
                continue;
            }

            $valueOnModel = $this->snapshotSavingData[$relationshipName] ?? null;

            $serializedData = null;

            // if relationshipName exists on payload, get this value but not real relationship
            if ($valueOnModel) {
                if ($valueOnModel instanceof Collection) {
                    $valueOnModel = $valueOnModel->toArray();
                }

                $arrayableRelation = false;
                $relationshipType = get_class($source->{$relationshipName}());

                if(in_array($relationshipType, [
                    \Illuminate\Database\Eloquent\Relations\HasMany::class,
                    \Illuminate\Database\Eloquent\Relations\MorphMany::class,
                    \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
                    \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
                    \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
                ])){
                    $arrayableRelation = true;
                }

                if($arrayableRelation){
                    $valueOnModel = array_map(function($item){
                        if(is_array($item) && Arr::isAssoc($item)){
                            return $item['id'];
                        }

                        if($item instanceof \Illuminate\Database\Eloquent\Model){
                            return $item->{$item->getKeyName()};
                        }

                        return $item;
                    }, $valueOnModel);
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

        return $data;
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
        $relations = method_exists($model, 'definedRelations')
            ? $model->definedRelations()
            : [];

        $model->setRawAttributes(array_diff_key($data, array_flip($relations)));

        foreach ($data as $key => $value) {
            if(method_exists($model, $key)){
                $reflectionMethod = new \ReflectionMethod($model, $key);

                if ($reflectionMethod->isPublic() && $reflectionMethod->isStatic() === false) {
                    $relationInstance = $model->$key();
                    if ($relationInstance instanceof Relation) {
                        $relatedModelClass = get_class($relationInstance->getRelated());

                        if (is_array($value) && Arr::isAssoc($value)) {
                            $model->setRelation($key, $this->castToEloquent($value, $relatedModelClass));
                        } elseif (is_array($value)) {

                            $model->setRelation($key, collect($value)->map(function ($item) use ($relatedModelClass) {
                                return $this->castToEloquent($item, $relatedModelClass);
                            }));
                        }

                        $model->offsetUnset($key);
                    }
                }
            }
        }

        return $model;
    }

    /**
     * Saves the snapshot.
     *
     * @return void
     */
    public function saveSnapshot()
    {
        $source = $this->getSnapshotSource();

        if(!$source){
            return;
        }

        $this->snapshot()->updateOrCreate(
            [
                'snapshotable_id' => $this->id,
                'snapshotable_type' => get_class($this),
                'source_type' => get_class($source),
                'source_id' => $source->id,
            ],
            []
        );

        $this->snapshot()->update([
            'data' => $this->prepareDataToSnapshot()
        ]);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        $source = $this->relationLoaded('source')
            ? $this->getRelation('source')
            : $this->source;

        $snapshot = $this->snapshot;


        if($source){
            $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();
            $snapshotableSourceAttributes = $this->getSnapshotableSourceAttributes();

            $sourceKeys= array_values(
                array_diff(
                    $snapshotableSourceAttributes,
                    $reservedAttributes
                )
            );

            $sourceAttributes = array_intersect_key($source->toArray(), array_flip($sourceKeys));

            $snapshottedWiths = Collection::make($this->withSnapshot)
                ->mapWithKeys(fn($with) => [$with => self::$snakeAttributes ? Str::snake($with) : $with])
                ->toArray();

            $snapshottedKeys = $this->getSourceAttributesToSnapshot();
            $snapshottedAttributes = [];
            if($snapshot){
                $snapshottedAttributes = array_intersect_key($snapshot->data, array_flip($snapshottedKeys));
                if($snapshottedWiths){
                    foreach($snapshottedWiths as $relationship => $serializeKey){
                        if(isset($snapshot->data[$relationship])){
                            $snapshottedAttributes[$serializeKey] = $snapshot->data[$relationship];
                        }
                    }
                }
            }

            return array_merge($sourceAttributes, $snapshottedAttributes, $attributes);
        }

        return $attributes;
    }

    public function __get($key)
    {
        $snapshotableSourceAttributes = $this->getSnapshotableSourceAttributes();
        $snapshotableSourceRelationships = $this->getSnapshotableSourceRelationships();
        $reservedAttributes = $this->getReservedAttributesAgainstSnapshot();
        $sourceClass = new ($this->getSnapshotSourceClass());
        $foreignKey = $this->getSnapshotSourceForeignKey();


        if($this->exists 
            && !in_array($key, $reservedAttributes) 
            && !in_array($key, ['snapshot', 'source', 'snapshotSource'])
        ){

            if($this->snapshot()->exists() && $foreignKey ){

                $snapshot = $this->snapshot;

                if($foreignKey == $key){
                    return $snapshot->source_id;
                }

                $source = $this->relationLoaded('source')
                    ? $this->getRelation('source')
                    : $this->source;

                if($this->fieldIsSnapshotSynced($key)){
                    if($source){
                        return $source->{$key};
                    }
                }

                if(in_array($key, $snapshotableSourceAttributes)){
                    if($snapshot && $snapshot->data && isset($snapshot->data[$key])){
                        return $snapshot->data[$key];
                    }
                }

                if(in_array($key, $snapshotableSourceRelationships) && !$this->relationshipIsSnapshotSynced($key)){

                    $sourceRelationshipsSnapshotted = $this->getSourceRelationshipsToSnapshot();

                    if(in_array($key, $sourceRelationshipsSnapshotted)){

                        $relation = $sourceClass->{$key}();
                        $relatedModelClass = $relation->getRelated();

                        if($snapshot){

                            $data = $snapshot->data;

                            $rawData = null;

                            if(isset($data[$key])){
                                $rawData = $data[$key];
                            } else if(isset($data[Str::snake($key)])){
                                $rawData = $data[Str::snake($key)];
                            }

                            if(!is_null($rawData)){
                                if(in_array($relation::class, [
                                    \Illuminate\Database\Eloquent\Relations\HasMany::class,
                                    \Illuminate\Database\Eloquent\Relations\MorphMany::class,
                                    \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
                                    \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
                                    \Illuminate\Database\Eloquent\Relations\MorphToMany::class,
                                ])){
                                    return Collection::make($rawData)->map(function($item) use ($relatedModelClass){
                                        $relatedModel = new $relatedModelClass();
                                        return $relatedModel->newFromBuilder($item);
                                    });
                                }else if (is_array($rawData) && Arr::isAssoc($rawData)){
                                    $relatedModel = new $relatedModelClass();
                                    return $relatedModel->newFromBuilder($rawData);
                                }
                            }
                        }
                    }
                }
            }
        }

        return parent::__get($key);
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

        if($this->exists && !$this->hasColumn($method) ){

            $snapshotSourceClass = $this->getSnapshotSourceClass();

            if(method_exists($snapshotSourceClass, $method) && $this->isSnapshotRelationship($method)){

                $relationClassesPattern = "|" . preg_quote(config('manage-eloquent.relations_namespace'), "|") . "|";

                $methodReflector = new \ReflectionMethod($snapshotSourceClass, $method);

                if($methodReflector->hasReturnType() && preg_match("{$relationClassesPattern}", $methodReflector->getReturnType() )){

                    $source = $this->relationLoaded('source')
                        ? $this->getRelation('source')
                        : $this->source;

                    $query = $source->{$method}();

                    $snapshot = $this->snapshot;

                    if($snapshot){
                        $value = $snapshot->data[$method] ?? null;

                        if(isset($value)){
                            if (is_array($value) && !Arr::isAssoc($value)) { // multiple relationship
                                $query = $query->whereIn('id', Arr::map($value, fn($v) => $v['id']));
                            } else if(is_array($value) && Arr::isAssoc($value)) { // single relationship
                                $query = $query->where('id', $value['id']);
                            }
                        }
                    }

                    return $query;
                }
            }
        }



        return parent::__call($method, $parameters);
    }
}
