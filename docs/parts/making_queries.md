# MAKING QUERIES  

**[ Table Of Contents](toc.md)**

Now the fun begins. We have models, now it's time to play with them. From here, you'll begin to see that 'powerful query api' thing I talked about in the README.md.

 ## THE MODEL HANDLER

 This is the center of quering. Every model has a 'handler' or 'manager' that takes care of performing operations on all the objects (rows) of the model. You can think of the manager as a representation of the table itself.

 `Model::items()` returns an instance of `Q\Orm\Handler`. This class uses a fluent interface, where all the methods can be chained. And this is the class that performs queries on models. 
 
 Based on the model we created previously, `User::items()` represents all the rows or objects of the User model or table.

 `User::items()->one()` will return a single user object based on default sorting.

 `User::items()->all()` will return a generator pointing to the first item based on the default sorting.

 `User::items()->exists()` will return a boolean value, indicating whether an object exists in the handler.

 The **second thing** at the heart of querying is the `\Q\Orm\Handler::filter(array $filters)` method. The mantra is **always filter before you do anything, otherwise your operation will be applied to all the objects (rows) of the model (table)**. I know, that was a very long mantra.
 
 Filter is lazily evaluated. It stores the intended operation and and returns the model manager. **Filter always returns the same model manager you called it on**, so you can chain several filters and other methods that are available to the model manager like delete, update, create, and many others.

 For instance `User::items()->filter(['username.length.gt'=>5])->delete()` will delete only users whose usernames have greater than 5 characters. On the other hand `User::items()->delete()` will literally delete all the rows (users). **Take note**.

-----   

## **CREATING OBJECTS**


1. Using the `\Q\Orm\Handler::create()`
    ```php
    <?php

    User::items()->create([
        'firstname' => 'Paul',
        'lastname' => 'Robert'
    ]); 
    ```
    This method **will return the model manager** on success and null on failure.  

    **Note that primary keys will not be overwritten.**

    **Also keep in mind the mass assignment vulnerability.**

2. The constructor method
    ```php
    <?php
     $user = new User;
     $user->firstname = 'Paul';
     $user->lastname = 'Robert';
     $user->save();

    ```
    Please note that if the primary key is assigned in the constructor method, the action is considered an update. And the primary key can never be overwritten. This method actually uses the the `\Q\Orm\Handler::create()` method under the hood.

    `\Q\Orm\Handler::save()` will return the newly created object or null.  

3. Passing attributes to the constructor
    ```php
    <?php

    $user = new User([
        'firstname'=>'John',
        'lastname'=>'Doe'
        ]);
    $user->save();

    ```

**Again, keep in mind the mass assignment vulnerability.**

------

## **UPDATING**

An update is essentially fetching an object or collection of objects, making changes, and then saving those changes.

**To set a field to `NULL` in the database, simply assign the attribute on the object to `null`**.

1. Using the `\Q\Orm\Handler::update()`
    ```php 
    <?php

    User::items()->filter(['email'=>'andrea@gmail.com'])->update([
        'firstname' => 'Andrea',
        'lastname' => 'Brocelli',
        'email'=>'a.b@example.com',
        'password'=>'secretpassword'
    ]); 
    ```
    If the filter method is not used, a batch update will occur. As in :

    ```php
    <?php

    User::items()->update([
        'firstname' => 'Andrea',
        'lastname' => 'Brocelli',
        'email'=>'a.b@gmail.com',
        'password'=>'secretpassword'
    ]); 
    ```
    This will update all the rows to the given values. Be careful.

    `\Q\Orm\Handler::update()` returns the model manager, for further chaining.

2. Using the `\Q\Orm\Handler::one()` method
    ```php
    <?php
     $user = User::items()->one();
     $user->firstname = 'Andrea';
     $user->lastname = 'Brocelli';
     $user->email = 'a.b@gmail.com';
     $user->password = 'secretpassword';
     $user->save();

    ```
    The primary key will be used to filter out the row. **The primary key will not be overwritten**. And only fields that are different from the previous values are updated. This method actually uses the the `\Q\Orm\Handler::update()` method under the hood. 

    Here's another example
    ```php
    <?php

    //Fetching a single User object
    $user = User::items()->filter(['email'=>'paul@example.com'])->one();
    $user->username = 'new_username';
    $user->save()
    ```
    You get the idea.    

----

## **DELETING OBJECTS**

```php
<?php

// This will delete all the objects, i.e truncate the table
User::items()->delete(); 

// This will only delete found rows (objects)
User::items()->filter(['id'=>7])->delete(); 
```
`\Q\Orm\Handler::delete()` returns deletes the rows and returns the model manager

**THE RULE IS ALWAYS FILTER BEFORE YOU DELETE OR UPDATE, EXCEPT YOU WANT BATCH UPDATES OR DELETIONS.**  
  
----

## PULLING OUT OBJECTS

After calling all the methods we want on the model manager, we'll have to retrieve the results.  

To retrieve an object or a collection, the `\Q\Orm\Handler::one()` and `\Q\Orm\Handler::all()` methods are used on the model manager, before or after filtering.

```php
<?php
$paul = User::items()->filter(['username'=>'paul'])->one();
$users = User::objects->filter(['username.length.eq'=>10])->all();

```

You get the idea.

----
## RELOADING AN OBJECT
This is used to restore an object to it's original state, if no changes on the objects have been `updated`.
```php
<?php
$user = User::items()->one();
$user->name = 'Peter';
$user->reload();

// User now restored to initial state
```

----
## CHECKING EXISTENCE
To check if an object exists without pulling it out.
```php
<?php
$users = User::items()->filter(['id.gt'=>5])->exists();
```
----
## PROJECTING FIELDS

To select only certain properties, you use the `\Q\Orm\Handler::project(...$fieldnames)`

```php
<?php

$users = User::items()->project('firstname', 'lastname')->all();
```
---

## ORDERING RESULTS

To order a collection (result set), the `\Q\Orm\Handler::order_by(...$fields)` method is used on the model manager.

```php
<?php

$lastUser = User::items()->order_by('id DESC')->one();

```

To order by multiple fields

```php
<?php

$users = User::items()->order_by('id DESC', 'firstname ASC')->all();

```
----
## LIMITING RESULTS
It is advised you always limit your queries, even on small databases. The method `Q\Orm\Handler::limit($limit, $offset = 0)` is used.

```php
<?php
$users = User::items()->limit(20);
$users = user::items()->limit(20, 10);
```
----

## PAGINATING RESULTS
To get a certain page out of a result set, use the `\Q\Orm\Handler::page($page, $ipp)`.
To do this, you need to know the page number and items per page. This will return an iterable containing the fetched users.

```php
<?php
$users = User::items()->page(2, 10)
```

---- 

## AVOIDING RACE CONDITIONS
In order to avoid race conditions, it is important we move operations like incrementing, decrementing, multiplying, dividing, appending, or prepending to the database.

```php
<?php

User::items()->filter(['id'=>5])->increment(['votes'=>3]);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET votes = votes + 3 WHERE id > 5`;

```php
<?php

User::items()->filter(['id'=>5])->decrement(['votes'=>1]);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET votes = votes - 1 WHERE id > 5`;

```php
<?php

User::items()->filter(['id'=>5])->multiply(['votes'=>3]);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET votes = votes * 3 WHERE id > 5`;

```php
<?php

User::items()->filter(['id'=>5])->divide(['votes'=>2]);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET votes = votes / 2 WHERE id > 5`;


```php
<?php

User::items()->filter(['id'=>5])->append(['firstname'=>'son']);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET firstname = firstname || 'son' WHERE id > 5`;


```php
<?php

User::items()->filter(['id'=>5])->prepend(['firstname'=>'son']);
```
The above translates to (depending on the sql dialect/flavour)  

`UPDATE user SET firstname = 'son' || firstname WHERE id > 5`;

----
## SIMPLE AGGREGATES
```php
<?php

$count = User::items()->count();
$max = User::items()->max('salary');
$min = User::items()->min('salary');
$sum = User::items()->sum('salary');
$avg = User::items()->avg('salary');

```
----
## RANDOM OBJECTS
To get a single random object  

```php
<?php
$user = User::items()->random();
```

To get 10 random objects.

```php
<?php

$users = User::items()->sample(10);

```

## RAW SQL QUERIES
To write raw select queries, do the following.  

```php
<?php
$users = User::raw("SELECT ... FROM user WHERE ....");
//OR, MUCH BETTER
$users = User::raw(sprintf("SELECT ... FROM user WHERE ...."));
```
As above, it is recommended you always use sprintf for more secured raw queries.  

Note though, that only select queries are allowed.  

----
## MAP AND PICK
These methods are the equivalent of `map` or `filter` in functional programming. You can run the objects gotten from a handler through them.

```php
<?php
$users = User::items()->map(function($user){
    return $user->name;
});

$users = User::items()->pick(function($user){
    if($user->id > 89){
        return true;
    }
    return false;
});
```


----
**[Previous : Migrating Models](migrating_models.md)**  |  **[Next Part : Query Filters](query_filters.md)** 