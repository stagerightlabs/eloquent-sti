<?php namespace SRLabs\EloquentSTI;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Extend Eloquent Model objects to support Single Table Inheritance
 * Based on http://snooptank.com/single-table-inheritance-with-eloquent-laravel-4/
 *
 * After including this trait in your model class you will need to specify these
 * members in that same class:
 *
 * // Db table for the base class
 * protected $table = 'widgets';
 *
 * // This is the full base class name
 * protected $morphClass = 'Epiphyte\Widget';
 *
 * // Column used for entity distinction
 * protected $discriminatorColumn = 'status';
 *
 * // Map each allowable entity type to its proper class. Entity classes can be
 * // located anywhere you find convenient.
 * protected $inheritanceMap = [
 *      'new' => 'Epiphyte\Entities\Widgets\NewWidget',
 *      'processed' => 'Epiphyte\Entities\Widgets\ProcessedWidget'
 *      'complete' => 'Epiphyte\Entities\Widgets\CompleteWidget'
 *  ];
 *
 */
trait SingleTableInheritanceTrait
{
    /**
     * Use the inheritance map to determine the appropriate object type for a given Eloquent object
     *
     * @param array $attributes
     * @return mixed
     */
    public function mapData(array $attributes)
    {
        // Determine the type of entity specified by the discriminator column
        $entityType = isset($attributes[$this->discriminatorColumn]) ? $attributes[$this->discriminatorColumn] : null;

        // Throw an exception if this entity type is not in the inheritance map
        if (!array_key_exists($entityType, $this->inheritanceMap)) {
            throw new ModelNotFoundException($this->inheritanceMap[$entityType]);
        }

        // Get the appropriate class name from the inheritance map
        $class = $this->inheritanceMap[$entityType];

        // Return a new instance of the specified class
        return new $class;
    }

    /**
     * Create a new model instance requested by the builder.
     *
     * @param  array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function newFromBuilder($attributes = array())
    {
        // Create a new instance of the Entity Type Class
        $m = $this->mapData((array)$attributes)->newInstance(array(), true);

        // Hydrate the new instance with the table data
        $m->setRawAttributes((array)$attributes, true);

        // Return the assembled object
        return $m;
    }

    /**
     * Get a new query builder for the model's table.
     *
     * @return \Illuminate\Database\Eloquent\Builder;
     */
    public function newRawQuery()
    {
        $builder = new Builder($this->newBaseQueryBuilder());

        // Once we have the query builders, we will set the model instances
        // so the builder can easily access any information it may need
        // from the model while it is constructing and executing various
        // queries against it.
        $builder->setModel($this)->with($this->with);

        return $builder;
    }

    /**
     * Get a new query builder for the model. Set any type of scope you want
     * on this builder in a child class, and it will keep applying
     * the scope on any read-queries on this model
     *
     * @return \Illuminate\Database\Eloquent\Builder;
     */
    public function newQuery($excludeDeleted = true)
    {
        $builder = $this->newRawQuery();

        if ($excludeDeleted and $this->softDelete) {
            $builder->whereNull($this->getQualifiedDeletedAtColumn());
        }

        return $builder;
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(array $options = array())
    {
        $query = $this->newRawQuery();

        // If the "saving" event returns false we'll bail out of the save
        // and return false, indicating that the save failed This gives
        // an opportunities to listeners to cancel save operations
        // if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update
        // our record that is already in this database using the current
        // IDs in this "where" clause to only update this model.
        // Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->performUpdate($query, $options);
        }

        // If the model is brand new, we'll insert it into our database
        // and set the ID attribute on the model to the value of the newly
        // inserted row's ID which is typically an auto-increment value
        // managed by the database.
        else {
            $saved = $this->performInsert($query, $options);

            $this->exists = $saved;
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Return the possible object types, as specified by the base class
     *
     * @return array
     */
    public function getBaseTypes()
    {
        return array_keys($this->inheritanceMap);
    }

    /**
     * Return a copy of this instance cast to the base class type.
     *
     * @return mixed
     */
    public function toBaseObject()
    {
        $object = app()->make($this->morphClass);
        $object->setRawAttributes((array)$this->getAttributes());

        return $object;
    }

}
