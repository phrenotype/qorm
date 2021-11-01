# MODEL RELATIONSHIPS  
**[ Table Of Contents](toc.md)**

Database tables are often related. This relationship, in a database, is implemented via foreign keys. Relationships in Q orm are just the same, only that here, we are dealing with objects.

Although there are three identified types of relationships between objects: one to one, one to many, and many to many, only the first two are atomic. The third relationship, many to many, is composed from one to many. Also, a junction table ( or class ) usually has additional properties. As such, it is left to the user to compose many to many relationships.

Like stated earlier. One of the aims of this orm is to teach principles of traditional database design.

----

## ONE TO ONE

To define a one-to-one relationships, the `\Q\Orm\Field::OneToOne()` method is used.

For this example, we have two models: **User** and **Address**. The idea is that a user has one and only one address.

```php
<?php

/* Just a bunch of classes we will be using */
use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class User extends Model {

	public $firstname;
	public $lastname;

	public static function schema(){
		return [
			'firstname' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = true;
			}),
			'lastname' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = true;
			}),		
		];
	}
}

class Address extends Model {

	public $zip;
	public $user;


	public static function schema(){
		return [
			'zip' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = true;
			}),
			'user' => Field::OneToOneField(User::class, function(Column $column){                
				$column->null = false;
			}, Index::UNIQUE, ForeignKey::CASCADE)            
		];
	}
}

```


### CREATING OBJECTS
```php
<?php

$user = User::items()->create([
	'firstname' => 'John',
	'lastname' => 'Doe'
])->order_by('id DESC')->one();

$address = Address::items()->create([
	'zip' => random_int(12345, 99999),
	'user' => $user
])->order_by('id DESC')->one();
```

### QUERYING OBJECTS

An Address can access it's user like so :
```php
<?php

$address->user();

```
A user can also access it's Address (If it has any) like so : 

```php
<?php

$user->address();
```
If a user does not have a associated Address, `false` is returned.


A you will see, the `User` object automatically has an attribute/method called `address`.

Since the Address model points to the User model, the orm automatically adds a field `address` to every address object as well as every User object (to ease bidirectional flow). 

Here's how the field names on the parent object (User) were decided.

If an object, A, points to another object, B, through a field called 'B' or 'b', an attribute will be automatically added to B called 'a'.

However, if A points to B through a field with a name that does not match 'b' or 'B', the an attribute will be added to B with the fieldname A used.

That is if Address had named the reference field `owner` instead of `user`, User would then have an attribute called owner, not user.


----


## ONE TO MANY

One-to-many or many-to-one is actually the same relationship. It's just how one is viewing the relationship.

To define a one-to-many relationships, the `Q\Orm\Field::ManyToOne()` method is used.

For this example, we have two models: **Author** and **Book**. The idea is that an `Author` has many `Book` objects pointing to it.

```php
<?php

/* Just a bunch of classes we will be using */
use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class Author extends Model {

	public $name;	

	public static function schema(){
		return [
			'name' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = true;
			})		
		];
	}
}


class Book extends Model {

    public $title;
    public $author;


	public static function schema(){
		return [
			'title' => Field::CharField(function(Column $column){				
				$column->size = 255;
				$column->null = true;
			}),
            'author' => Field::ManyToOneField(Author::class, function(Column $column){                
                $column->null = false;
            }, Index::INDEX, ForeignKey::CASCADE)            
		];
	}
}

```


### CREATING OBJECTS
```php
<?php

$author = User::items()->create([
	'name' => 'James Hadley Chase',	
])->order_by('id DESC')->one();

```

Now, author will automatically have an attribute called `book_set`, which is a `Q\Orm\Handler` that represents a collection of the books that belong to the user.

This gives use two different ways of creating `Book` objects.

```php
<?php

$book = $author->book_set->create([
    'title'=>'The world in my pocket'
]);

//OR

$book = Book::items()->create([
    'title'=>'The world in my pocket',
    'author'=> $author
]);

```

### QUERYING OBJECTS

An Book can access it's author like so :
```php
<?php

$book->author();

```

An `Author` can access it's collection of books like so:

```php
<?php

$lastBook = $author->book_set->filter(['title.startswith'=>'The'])->one();
```

Like any other manager, the `book_set` can be queried, inserted, updated, deleted, and aggregated.


A you will see, the `Author` object automatically has an attribute called `book_set`.

Here's how the field names on the parent object (Author) were decided.

If an object, A, points to another object, B, through a field called 'B' or 'b', an attribute will be automatically added to B called 'a_set'.

However, if A points to B through a field with a name that does not match 'b' or 'B', the an attribute will be added to B called `fieldname_set`.


----
**[Previous Part : Query Filters](query_filters.md)** | **[Next Part : Migration Commands](cli.md)**