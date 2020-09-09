# Eloquent Single Table Inheritance

This package provides an easy way to extend Eloquent model objects to provide support for single table inheritance.  Inspired by an [article](http://snooptank.com/single-table-inheritance-with-eloquent-laravel-4/) written by Pallav Kaushish.

### Installation
This package should be installed via composer:

```bash
$ composer require srlabs/eloquent-sti
```

| Laravel Version  | Sentinel Version  |
|---|---|
| 6.*  | 3.*  |
| 7.*  | 4.*  |
| 7.*  | 5.*  |

### Usage

In your Model, add the ```SingleTableInheritanceTrait``` trait and then specify these configuration values:

- ```table```: The name of the database table assigned to the base model
- ```morphClass```: The full class name of the base Eloquent Object
- ```discriminatorColumn```: The column in the database table used to distinguish STI entity types
- ```inheritanceMap```: An array mapping discriminator column values to corresponding entity classes

Here is an imaginary Widget model as an example:

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

Next you need to create each of your child entity classes. I often keep them in an ```Entities``` folder, but any namespaced location will work.

Here is a hypothetical example:

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
    public function newQuery($excludeDeleted = true)
    {
        return parent::newQuery($excludeDeleted)->where('status', '=', 'new');
    }

    // Remaining child entity methods go here...
}
```

Whenever Eloquent wants to return an instance of the base model it will use the value of the discriminator column to determine the appropriate entity type and return an instance of that class instead.  This holds true for all Eloquent actions but it will not work on direct database (i.e. ```DB::table()```) calls.

Providing the ```newQuery()``` method in the child class will allow you to use the Entity as a traditional Eloquent accessor that only returns entities of its own type.  In this case, ```NewWidget::all();``` would return all of the widgets flagged as 'new' in the database.

Any questions about this package should be posted on the [package website](http://stagerightlabs.com/projects/eloquent-sti).
