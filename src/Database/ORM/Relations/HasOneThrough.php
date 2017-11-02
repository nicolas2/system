<?php

namespace Nova\Database\ORM\Relations;

use Nova\Database\ORM\Relations\Relation;
use Nova\Database\ORM\Builder;
use Nova\Database\ORM\Collection;
use Nova\Database\ORM\Model;
use Nova\Database\ORM\ModelNotFoundException;
use Nova\Database\Query\Expression;


class HasOneThrough extends Relation
{
    /**
     * The distance parent model instance.
     *
     * @var \Nova\Database\ORM\Model
     */
    protected $farParent;

    /**
     * The near key on the relationship.
     *
     * @var string
     */
    protected $firstKey;

    /**
     * The far key on the relationship.
     *
     * @var string
     */
    protected $secondKey;

    /**
     * The local key on the relationship.
     *
     * @var string
     */
    protected $localKey;

    /**
     * Create a new has many through relationship instance.
     *
     * @param  \Nova\Database\ORM\Builder  $query
     * @param  \Nova\Database\ORM\Model  $farParent
     * @param  \Nova\Database\ORM\Model  $parent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $farParent, Model $parent, $firstKey, $secondKey, $localKey)
    {
        $this->localKey  = $localKey;
        $this->firstKey  = $firstKey;
        $this->secondKey = $secondKey;
        $this->farParent = $farParent;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        $this->setJoin();

        if (static::$constraints) {
            $parentTable = $this->parent->getTable();

            $localValue = $this->farParent->{$this->localKey};

            $this->query->where($parentTable .'.' .$this->firstKey, '=', $localValue);
        }
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param  \Nova\Database\ORM\Builder  $query
     * @param  \Nova\Database\ORM\Builder  $parent
     * @return \Nova\Database\ORM\Builder
     */
    public function getRelationCountQuery(Builder $query, Builder $parent)
    {
        $parentTable = $this->parent->getTable();

        $this->setJoin($query);

        $query->select(new Expression('count(*)'));

        $key = $this->wrap($parentTable .'.' .$this->firstKey);

        return $query->where($this->getHasCompareKey(), '=', new Expression($key));
    }

    /**
     * Set the join clause on the query.
     *
     * @param  \Nova\Database\ORM\Builder|null  $query
     * @return void
     */
    protected function setJoin(Builder $query = null)
    {
        $query = $query ?: $this->query;

        $foreignKey = $this->related->getTable().'.'.$this->related->getKeyName();

        $localKey = $this->parent->getTable().'.'.$this->secondKey;

        $query->join($this->parent->getTable(), $localKey, '=', $foreignKey);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $table = $this->parent->getTable();

        $this->query->whereIn($table .'.' .$this->firstKey, $this->getKeys($models, $this->localKey));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
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
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getKey();

            if (isset($dictionary[$key])) {
                $value = reset($dictionary[$key]);

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Nova\Database\ORM\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = array();

        $foreign = $this->firstKey;

        foreach ($results as $result) {
            $dictionary[$result->{$foreign}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->first();
    }

    /**
     * Execute the query and get the first related model.
     *
     * @param  array   $columns
     * @return mixed
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return (count($results) > 0) ? $results->first() : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return \Nova\Database\ORM\Model|static
     *
     * @throws \Nova\Database\ORM\ModelNotFoundException
     */
    public function firstOrFail($columns = array('*'))
    {
        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Nova\Database\ORM\Collection
     */
    public function get($columns = array('*'))
    {
        $columns = $this->query->getQuery()->columns ? array() : $columns;

        $select = $this->getSelectColumns($columns);

        $models = $this->query->addSelect($select)->getModels();

        if (count($models) > 0) {
            $models = $this->query->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Set the select clause for the relation query.
     *
     * @param  array  $columns
     * @return array
     */
    protected function getSelectColumns(array $columns = array('*'))
    {
        if ($columns == array('*')) {
            $columns = array($this->related->getTable().'.*');
        }

        return array_merge($columns, array($this->parent->getTable() .'.' .$this->firstKey));
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->farParent->getQualifiedKeyName();
    }
}
