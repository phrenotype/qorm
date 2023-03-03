# A QUICK TUTORIAL

In this tutorial, we are going to model a database for a simple blog. This is to get you started quickly.

Follow the [ setup guide ](./../../readme.md) here. Here, it is assumed you've carried out the setup already.

For beginners, each model is a class that represents a database table in code.

In the models folder, we will create the following files : 

**`Author.php`**

```php
<?php

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class Author extends Model {

	public $name;

	public static function schema() : array {
		return [
			'name' => Field::CharField(function(Column $column){		
				$column->size = 255;
				$column->null = false;
			}),		
		];
	}

	public function __toString(){
		return $this->name;
	}
}
```
**If your intended primary key is an auto-incrementing integer, do not bother defining one. A field called `id` is generated and added automatically.**

**Note that above schema could be shortened (using defaults) to :**
```php
public static function schema() : array {
	return [
		'name' => Field::CharField()
	];
}
```

To find out about other types of schema fields, please refer to [ this page ](./../parts/creating_models.md).

If you use a `uuid` as primary key, look at [ this page ](./../parts/uuid.md) to see how to setup the model.  

If you would like to use a more robust primary key generator, like the twitter snowflake, look at [ this page ](./../parts/peculiar.md) to see how to setup the model.



**`Post.php`**  
```php
<?php

namespace models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class Post extends Model {

	public $title;
	public $content;	
	public $author;


	public static function schema() : array {
		return [
			'title' => Field::CharField(function(Column $column){
				$column->size = 255;
				$column->null = false;
			}, Index::UNIQUE),
			'content' => Field::TextField(function(Column $column){		
				$column->null = false;
			}),
			'author' => Field::ManyToOneField(function(Column $column){	
				$column->null = false;
			}),			
		];
	}
}
```


For more on model relationships, see [ this page ](./../parts/relationships.md).

---
[ Next : Migrating the models](migrating.md)