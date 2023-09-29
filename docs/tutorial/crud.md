# CRUD OPERATIONS
CRUD stands for create, read, update and delete. We will quickly go through these now.

For more details on these operations and variants, please refer to [ this page ](./../parts/making_queries.md)

## CREATE

To create new records

```php

$a = new Author;
$a->name = 'James Hadley Chase';
$author = $a->save();

$p = new Post;
$p->title = 'First Post';
$p->content = 'How to write a blog post';
$p->author = $author;
$post = $p->save();

```

To find out other ways to create records, please refer to [ this page ](./../parts/making_queries.md#creating-objects)

## READ
To query a single author
```php

$author = Author::items()->filter(['id'=>1])->one();

```

To find an author's collection of posts  

```php

$allPosts = $author->post_set->all();

$filtered = $author->post_set->filter(['title.startswith'=>'The'])->one()

```

To find a post

```php
$post = Post::items()->one();
$post = Post::items()->filter(['id'=>1])->one();
```

To find out more about other ways to query records, please refer to [ this page ](./../parts/making_queries.md)  
To find out more about filters , please refer to [ this page ](./../parts/query_filters.md)

## UPDATE

```php
Post::items()->filter(['id'=>4])->update([
    'title' => 'Blogging'
]);
```

To find out more about other ways to update records, please refer to [ this page ](./../parts/making_queries.md#updating)  
To find out more about filters , please refer to [ this page ](./../parts/query_filters.md)

## DELETE
```php
$post = Post::items()->filter(['id'=>4])->delete();
```
To find out other ways to delete objects, please refer to [ this page ](./../parts/making_queries.md#deleting-objects)  
To find out more about filters , please refer to [ this page ](./../parts/query_filters.md)

And that's it !

---
[ Previous : Migrating the models](migrating.md)