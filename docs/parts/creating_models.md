
# CREATING MODELS  

**[ Table Of Contents ](toc.md)**


A model is a class that serves as a blueprint for your database tables.

The Q Orm convention for naming models is the **pascal case**, e.g. **User, Post, Address, UserAddress**.

Also, use of singular words for naming models is encouraged. For instance User, instead of Users.

A model should always extend the `Q\Orm\Model` abstract class. It's also required to implement an abstract static method `Q\Orm\Model::schema()`. This static method is what is used to build the database schema. It returns an associative array. The keys here are the column names as you want them in the database, and the values are instances of `Q\Orm\Field` object.  

The benefit of doing things this way is that both your class definition and database table definition are in one place. At a glance, you can see what your database looks like without switching to a database viewer.

We are now going to create a **User** model. In your models folder, create a file **User.php** or if you are using a single file to store all the models, add this code to the file.

```php
<?php

/* 
This this can be any namespace you want 
or you can simply remove it if namespaces are not your thing
*/
namespace models;

/* Just a bunch of classes we will be using */
use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class User extends Model {

	public $firstname;
	public $lastname;	
	public $date_joined;


	public static function schema(): array {
		return [
			'firstname' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = false;
			}),
			'lastname' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = false;
			}),
			'date_joined' => Field::DateTimeField(function(Column $column){				
				$column->default = function(){ return date("Y-m-d H:i:s"); };
			}),			
		];
	}

}

```

**An auto-incrementing integer field called `id` is automatically added. So, except your primary is of a different type, don't bother defining one.**

**Note that in php 8, the above schema could be shortened to :**
```php
public static function schema(): array {
	return [
		'firstname' => Field::CharField(),
		'lastname' => Field::CharField(),
		'date_joined' => Field::DateTimeField(function(Column $column){
			$column->default = function(){ return date("Y-m-d H:i:s"); }
		})
	];
}
```

### SCHEMA FIELDS

It is important to know that the fields that matter to the orm are the keys of the associative array that `Q\Orm\Model::schema()` returns. The publicly defined attributes are only for code auto-completion and are completely optional, though adding them is strongly recommended.

The value of each key in tha `schema` array is a `Q\Orm\Field` Object. There are different field types. Each field has a column, which the is passed to a closure for mutation.

**For more details on Schema fields, refer to [this page](../partials/schema_fields.md)**.

A column has the following attributes : 

**`type`**  
This is the type the column should have. This is a **readonly** field.

**`size`**  
This is for fields that require type/precision. For enum fields, this should be an array.

**`null`**  
If the value is set to `true`, it results in `NULL` in SQL. `false` results in `NOT NULL`. The default is `false`.

**`default`**  
This is used to define the default value for a column. It can take a static value or a callable that will be invoked every time a new object is created.

**`auto_increment`**  
This is also set to true or false. There is no default.

**To find out more about columns, refer to [this page](../partials/column.md)**.  


A schema also has an `Index`, which is optional for all fields except `Q\Orm\Field::ManyToOne()` and `\Q\Orm\Field::OneToOne()`.

Indexes can be :

**`Index::PRIMARY_KEY`**  
This declares a field to be the primary key

**`Index::INDEX`**  
This creates a regular index on a field

**`Index::UNIQUE`**
This creates a unique index on a field 

**To find out more about indexes, refer to [this page](../partials/indexes.md)**. 

If the field is `Q\Orm\Field::ManyToOne()` or `Q\Orm\Field::OneToOne()`, there is another optional parameter, called `onDelete`.

It can be :   
**`ForeignKey::CASCADE`**  
**`ForeignKey::RESTRICT`**  
**`ForeignKey::NULLIFY`**

**For more details on Schema fields, refer to [this page](../partials/schema_fields.md)**.  

----
**[ Previous : Table Of Contents](toc.md)**  |  **[Next Part : Migrating Models](migrating_models.md)**   