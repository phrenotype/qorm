# JOINS 
**[ Table Of Contents](toc.md)**

If you don't already know what joins are, or do not know (or have an idea on) how to write joins in sql, you may not be able to follow this section.

If you insist you want to write joins, maybe for performance gains or whatever reason you have, then we have you covered.

Keep in mind though, alot of problems solved by joins can be solved with model relationships or subqueries in filters (in and not_id).

A way of introduction and summary, joins here are all about calling `Q\Orm\Handler::join()` and passing to it the Handler you with to join. Examples will explain this better.

Another thing you need to know about is the `Q\Orm\Handler::as()` method. This method confers an alias on a Handler same way one would alias a table in sql.

The `Q\Orm\Handler::join` method takes three parameters, all required. The first is field on the handler.The second is the handler you wish to join on, the last is the field on the handler (the previous parameter). Look below.

For left and right joins, simply use `Q\Orm\Handler::leftJoin` and `Q\Orm\Handler::rightJoin` respectively. The parameters remain the same.

```php
<?php

Comment::items()->as('comment')
            ->join('user', User::items()->as('user'), 'id');
``` 

I hope it makes sense now.

Finally, all joins in this orm are equi-joins.

## THE FIVE(5) RULES
To write a join or joins in the Q orm, there are **5 rules** you must observe.

1. Every handler **taking part in the join** must have an alias.
1. The first Handler **must** project the needed columns.
1. The projected columns **must be** prefixed with their handler alias.
1. Note that **reference columns will never be returned**, since they are essentially primary keys of the joined tables, but then **every aliased column will be returned**.
1. When filtering a joined Handler, **do not** prefix the fieldnames with aliases, just filter as though the fields existed on the first Handler's model.

## PROPER EXAMPLES

Assume we have the following models.

```php
<?php

namespace models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class User extends Model {
    
    public $name;	
    public $date_joined;	    

    public static function schema(){
        return [
            'name' => Field::CharField(function(Column $column){				
                $column->size = 255;
                $column->null = true;
            }),
            'date_joined' => Field::DateTimeField(function(Column $column){				
                $column->default = function(){ return date("Y-m-d"); };
            })			
        ];
    }
}



class Comment extends Model {

	public $text;
	public $user;
	public $post;

	public static function schema()
	{
		return [
            'text' => Field::TextField(function (Column $column) {
                $column->null = false;
            }),
            'user' => Field::ManyToOneField(User::class, function (Column $column) { $column->null = false; },Index::INDEX),
            'post' => Field::ManyToOneField(Post::class, function(Column $column){
                $column->null = false;
            }, Index::INDEX),
		];
	}
}

class Post extends Model {

	public $title;
	public $body;
	public $user;

	public static function schema(){
		return [
			'title' => Field::CharField(function(Column $column){
				$column->size = 255;
				$column->null = false;
			}),
			'body' => Field::TextField(function(Column $column){				
				$column->null = false;
			}),
			'user' => Field::ManyToOneField(
				User::class, 
				function(Column $column){ $column->null = false; },
				Index::INDEX
			),
		];
	}
}

```

Now let's say we want to create a table or list that shows for each comment the following
- The comment's text
- The title of the post it was made on
- The name of the user who mad the comment

We will the write it like so ( based on the five(5) rules):

```php
<?php

$all = Comment::items()->as('comment')
    ->project('comment.text', 'user.name', 'post.title')

    ->join('user', User::items()->as('user'), 'id')
    ->join('post', Post::items()->as('post'), 'id')

    ->limit(10)
    ->all();

```

## AVOIDING COLUMN NAME AMBIGUITY

To avoid running into problems when the column names class, simply alias the columns.

```php
<?php

$all = Comment::items()->as('comment')
    ->project('comment.id AS cid', 'comment.text', 'user.id AS uid', 'user.name', 'post.title')
    
    ->join('user', User::items()->as('user'), 'id')
    ->join('post', Post::items()->as('post'), 'id')

    ->limit(10)
    ->all();

```

## FILTER, LIMIT AND ORDER BY... E.T.C.

You are not allowed to filter, limit or order any of the handlers taking part in the join. You can only do these **after** the all the joins have been made. Look below.

```php
<?php

$all = Comment::items()->as('comment')
    ->project('comment.text', 'comment.id AS cid', 'user.name', 'post.title')
    
    ->join('user', User::items()->as('user'), 'id')
    ->join('post', Post::items()->as('post'), 'id')

    ->filter(['title.startswith'=>'a', 'cid.gt' => 5])
    ->limit(10)
    ->all();

```

**As per rule 5, you will notice that the filtered fields are not prefixed. Please just call the names or aliases as though they existed on the Handler**

## SELF JOINS

Yes. You can perform self joins. Simply give the handlers different aliases. If you don't know what self joins are, well... ;)

---
**[Previous Part : UUIDs](uuid.md)**  | **[Next Part : GROUPING AGGREGATES](grouping.md)**