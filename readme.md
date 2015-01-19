# Eloquent Single Table Inheritance

Extend Eloquent Models to provide single table inheritance. 

### Installation

This package can be installed via composer: 

```shell
$ composer require srlabs/eloquent-sti
```

### Usage

To start, add this trait to the model you wish to convert to STI.  You also need to specify a few extra parameters.  Here is an imaginary Widget model as an example: 

```php
// app/Epiphyte/Widget.php
class Widget extends Illuminate\Database\Eloquent\Model {

    /*****************************************************************************
     *  Eloquent Configuration
     *****************************************************************************/
    
    protected $guarded = ['id'];
    protected $fillable = ['name', 'description', 'status'];
     
    /*****************************************************************************
     * Single Table Inheritance Configuration
     *****************************************************************************/

    use SingleTableInheritanceTrait;
    protected $table = 'widgets';
    protected $morphClass = 'Epiphyte\Widget';
    protected $discriminatorColumn = 'status';
    protected $inheritanceMap = [
        'new' => 'Epiphyte\Entities\Widgets\NewWidget',
        'processed' => 'Epiphyte\Entities\Widgets\ProcessedWidget'
        'complete' => 'Epiphyte\Entities\Widgets\CompleteWidget'
    ];
    
    // ...
}
```

You must specify the database table name and the full class name of the base model. The ```discriminatorColumn``` is the name of the column used to distinguish your entity types.  The inheritance map specifies the full class name of each of the child entity types that you want made available to the base model. The child entities can be put in any convenient location.

Next you need to create each of your child entity classes. You can optionally provide an implementation of the ```newQuery()``` funtion - see below.  Here is a hypothetical example: 

```php
// app/Epiphyte/Entities/Widgets/NewWidget.php
class NewWidget extends Epiphyte\Widget {

	/**
	 * Limit the query scope if we define a query against the base table using this class.
	 *
	 * @param bool $excludeDeleted
	 *
	 * @return $this
	 */
	public function newQuery( $excludeDeleted = true )
	{
		return parent::newQuery( $excludeDeleted )->where( 'status', '=', 'new' );
	}

    // Remaining child entity methods go here...
}
```

Providing the ```newQuery()``` method in your child entity will allow you to use the Entity as a traditional Eloquent accessor that only returns its own type of entities.  In this case, ```NewWidget::all()``` would return all of the widgets flagged as 'new' in the database.  

Any methods or relationships that are only needed for this type of child entity can be specified in the ```NewWidget``` class.  Any methods or relationships specified in the parent class are still available to the child entity. 