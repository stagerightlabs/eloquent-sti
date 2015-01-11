<?php namespace Phylos\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Implements Single Table Inheritance for Eloquent
 * Based on http://snooptank.com/single-table-inheritance-with-eloquent-laravel-4/
 *
 * You must add these options to the Model using this trait:
 *
 * use StiEntityTrait;
 * protected $table = 'samples';
 * protected $morphClass = 'User';
 * protected $baseTypes = ['new', 'registered', 'suspended', 'banned'];
 *
 */

trait StiEntityTrait {

	/**
	 * Provide an attributes to object map
	 *
	 * @return Model
	 */
	public function mapData( array $attributes )
	{
		$type = isset( $attributes['type'] ) ? $attributes['type'] : null;

		if (! in_array($type, $this->baseTypes) )
		{
			throw new ModelNotFoundException($this->morphClass . $type);
		}

		$class = $this->prepareClassName($type);

		return new $class;

	}

	protected function prepareClassName( $type )
	{
		return 'Phylos\\Entities\\'
		       . str_plural( class_basename( $this->morphClass ) )
		       . '\\'
		       . class_basename( $this->morphClass )
		       . str_replace( ' ', '', ucwords( $type ) );
	}

	/**
	 * Create a new model instance requested by the builder.
	 *
	 * @param  array  $attributes
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public function newFromBuilder( $attributes = array() )
	{
		$m = $this->mapData( (array) $attributes )->newInstance( array(), true );
		$m->setRawAttributes( (array) $attributes );
		return $m;
	}

	/**
	 * Get a new query builder for the model's table.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder;
	 */
	public function newRawQuery()
	{
		$builder = new Builder( $this->newBaseQueryBuilder() );

		// Once we have the query builders, we will set the model instances
		// so the builder can easily access any information it may need
		// from the model while it is constructing and executing various
		// queries against it.
		$builder->setModel( $this )->with( $this->with );

		return $builder;
	}

	/**
	 * Get a new query builder for the model.
	 * set any type of scope you want on this builder in a child class, and it'll
	 * keep applying the scope on any read-queries on this model
	 *
	 * @return \Illuminate\Database\Eloquent\Builder;
	 */
	public function newQuery( $excludeDeleted = true )
	{
		$builder = $this->newRawQuery();

		if ( $excludeDeleted and $this->softDelete )
		{
			$builder->whereNull( $this->getQualifiedDeletedAtColumn() );
		}

		return $builder;
	}

	/**
	 * Save the model to the database.
	 *
	 * @return bool
	 */
	public function save( array $options = array() )
	{
		$query = $this->newRawQuery();

		// If the "saving" event returns false we'll bail out of the save
		// and return false, indicating that the save failed.
		// This gives an opportunities to listeners to cancel save operations
		// if validations fail or whatever.
		if ($this->fireModelEvent( 'saving' ) === false)
		{
			return false;
		}

		// If the model already exists in the database we can just update
		// our record that is already in this database using the current IDs
		//  in this "where" clause to only update this model.
		//  Otherwise, we'll just insert them.
		if ( $this->exists )
		{
			$saved = $this->performUpdate( $query, $options );
		}

		// If the model is brand new, we'll insert it into our database
		// and set the ID attribute on the model to the value of the newly
		// inserted row's ID which is typically an auto-increment value
		// managed by the database.
		else
		{
			$saved = $this->performInsert( $query, $options );

			$this->exists = $saved;
		}

		if ($saved) $this->finishSave( $options );

		return $saved;
	}

	/**
	 * Get the object's current status
	 *
	 * @return string
	 */
	public function getStatusAttribute()
	{
		return ucwords( $this->type );
	}

	/**
	 * Retrieve actions for particular user types
	 * @return $this
	 */
	public function getActions( array $parameters = null )
	{
		// Make sure the required parameters are available
		if ( is_null( $parameters ) || !isset( $parameters['level'] ) )
		{
			return [];
		}

		$actions = new Collection();

		$options = $this->actions[$parameters['level']] ?: [];

		foreach ( $options as $item )
		{
			// If we have not specified a route action for this item, ignore it.
			if (! isset($item['action'])) { continue; }

			// If we only want concise action items, and this is not one, don't return it
			if ( isset( $parameters['concise'] ) && $parameters['concise'] && !$item['concise'] ) { continue; }

			// Create a new Pylos\Models\Action object using $item as the constructor values
			$actions->push( new Action($item, $this->getRouteParameters()) );
		}

		// Sort the collection by their specified order and return it
		return $actions->sortBy( 'order' );
	}

	/**
	 * Determine if there are actions available for a given access level
	 *
	 * @param $level
	 *
	 * @return bool
	 */
	public function hasActions( $level )
	{
		return ( array_key_exists( $level, $this->actions ) && count( $this->actions[$level] ) > 0 );
	}

	/**
	 * Determine if a given action is available for a certain access level
	 *
	 * @param $action
	 * @param $level
	 *
	 * @return bool
	 */
	public function isActionable( $action, $level )
	{
		foreach($this->actions[$level] as $item)
		{
			if ($item['name'] == $action)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the possible object types, as specified by the base class
	 *
	 * @return array
	 */
	public function getBaseTypes()
	{
		return $this->baseTypes ?: [];
	}

	/**
	 * Return the actions provided by the base class
	 *
	 * mixed
	 */
	public function getBaseActions()
	{
		return $this->actions;
	}

	public function toBaseObject()
	{
		$object = app()->make($this->morphClass);
		$object->setRawAttributes( (array) $this->getAttributes() );
		return $object;
	}

}

