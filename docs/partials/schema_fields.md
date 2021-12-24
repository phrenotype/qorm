# Schema Fields

## Value Fields  

These are static methods of the `Q\Orm\Field` class that construct and return an instance of the class.  

They take in just two parameters  

1. The mutator : This is required. This is a callable that takes just one argument, an instance of `Q\Orm\Migration\Column` and modifies or mutates it. [This page](column.md) contains information about columns and their attributes .[This page](../parts/defaults.md) contains information about how to set default values for a column.

2. The index : This is optional. It indicates the type of index you wish to add to the column. The fully qualified namespace for the `Index` class is `Q\Orm\Migration\Index`. [This page](indexes.md) has a list of all the index types.

A quick example of value fields :

```php
    //Snippet from model class

	public static function schema(): array
	{
		return [

            // Has no index
			'name' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = false;
			}),

            // Has a unique index
			'email' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = false;
			}, Index::UNIQUE)
        ]
    }            

```

Below is a complete list of all the value field types supported

`Field::BooleanField`  

`Field::TextField`  

`Field::IntegerField`  

`Field::FloatField`  

`Field::CharField`  

`Field::DoubleField`  

`Field::DecimalField`

`Field::NumericField`  

`Field::EnumField`  
For enum fields, the `size` attribute should be an array of acceptable values, not a precision or size value.

`Field::DateField`  

`Field::DateTimeField`  

## Relationship Fields
These are used to define foreign keys and establish relationship with other models. There are two types.

`Field::OneToOneField`  

`Field::ManyToOneField`

Theses methods take in four(4) arguments, three(3) of which are required  

1. The fully qualified classname of the model you with to reference.
2. The mutator, same as the one for value fields, but you can only modify the `name`, `null`, and `type` attributes of the column.
3. The index, same as the one for value fields.
4. This argument is optional. It dictates what happens when the referenced field is deleted. There are three options  
    - `ForeignKey::CASCADE`
    - `ForeignKey::RESTRICT`
    - `ForeignKey::NULLIFY`  

    The `ForeignKey` class fully qualified name is `Q\Orm\Migration\ForeignKey`.  

Examples  

```php
    // Snippet from model class
    public static function schema(): array
    {
		return [

            'user' => Field::OneToOneField(User::class, function (Column $column) {
                $column->null = false;
            }, Index::INDEX, ForeignKey::CASCADE),

            // No onDelete option defined
            'post' => Field::ManyToOneField(Post::class, function (Column $c) {
                $c->null = false;
            }, Index::INDEX),

        ];
    }

```

