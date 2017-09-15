<?php

namespace Nova\Database\ORM\Relations;

use Nova\Database\ORM\Model;
use Nova\Database\ORM\Builder;
use Nova\Database\ORM\Collection;
use Nova\Support\Collection as BaseCollection;


class MorphTo extends BelongsTo
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The models whose relations are being eager loaded.
     *
     * @var \Nova\Database\ORM\Collection
     */
    protected $models;

    /**
     * All of the models keyed by ID.
     *
     * @var array
     */
    protected $dictionary = array();

    /*
     * Indicates if soft-deleted model instances should be fetched.
     *
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Nova\Database\ORM\Builder  $query
     * @param  \Nova\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $type
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey, $type, $relation)
    {
        $this->morphType = $type;

        parent::__construct($query, $parent, $foreignKey, $otherKey, $relation);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->buildDictionary($this->models = Collection::make($models));
    }

    /**
     * Build a dictionary with the models.
     *
     * @param  \Nova\Database\ORM\Collection  $models
     * @return void
     */
    protected function buildDictionary(Collection $models)
    {
        foreach ($models as $model) {
            $morphType = $this->morphType;

            if (isset($model->{$morphType})) {
                $type = $model->{$morphType};

                $key = $model->getAttribute($this->foreignKey);

                $this->dictionary[$type][$key][] = $model;
            }
        }
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Nova\Database\ORM\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $models;
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Nova\Database\ORM\Model  $model
     * @return \Nova\Database\ORM\Model
     */
    public function associate(Model $model)
    {
        $this->parent->setAttribute($this->foreignKey, $model->getKey());

        $this->parent->setAttribute($this->morphType, $model->getMorphClass());

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Get the results of the relationship.
     *
     * Called via eager load method of ORM query builder.
     *
     * @return mixed
     */
    public function getEager()
    {
        foreach (array_keys($this->dictionary) as $type) {
            $this->matchToMorphParents($type, $this->getResultsByType($type));
        }

        return $this->models;
    }

    /**
     * Match the results for a given type to their parents.
     *
     * @param  string  $type
     * @param  \Nova\Database\ORM\Collection  $results
     * @return void
     */
    protected function matchToMorphParents($type, Collection $results)
    {
        foreach ($results as $result) {
            $key = $result->getKey();

            if (isset($this->dictionary[$type][$key])) {
                foreach ($this->dictionary[$type][$key] as $model) {
                    $model->setRelation($this->relation, $result);
                }
            }
        }
    }

    /**
     * Get all of the relation results for a type.
     *
     * @param  string  $type
     * @return \Nova\Database\ORM\Collection
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $key = $instance->getKeyName();

        $query = $instance->newQuery();

        $query = $this->useWithTrashed($query);

        return $query->whereIn($key, $this->gatherKeysByType($type)->all())->get();
    }

    /**
     * Gather all of the foreign keys for a given type.
     *
     * @param  string  $type
     * @return array
     */
    protected function gatherKeysByType($type)
    {
        $foreign = $this->foreignKey;

        //
        $results = $this->dictionary[$type];

        return BaseCollection::make($results)->map(function($models) use ($foreign)
        {
            $model = head($models);

            return $model->{$foreign};

        })->unique();
    }

    /**
     * Create a new model instance by type.
     *
     * @param  string  $type
     * @return \Nova\Database\ORM\Model
     */
    public function createModelByType($type)
    {
        return new $type;
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the dictionary used by the relationship.
     *
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }

    /**
     * Fetch soft-deleted model instances with query
     *
     * @return $this
     */
    public function withTrashed()
    {
        $this->withTrashed = true;

        $this->query = $this->useWithTrashed($this->query);

        return $this;
    }

    /**
     * Return trashed models with query if told so
     *
     * @param  \Nova\Database\ORM\Builder  $query
     * @return \Nova\Database\ORM\Builder
     */
    protected function useWithTrashed(Builder $query)
    {
        if ($this->withTrashed && $query->getMacro('withTrashed') !== null) {
            return $query->withTrashed();
        }

        return $query;
    }

}
