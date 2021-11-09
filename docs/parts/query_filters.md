# QUERY FILTERS  
**[ Table Of Contents](toc.md)**

Filters provide a way to select objects (rows) whose attributes (fields) match a certain criteria. In translates into SQL's `WHERE` statement.

The general syntax if
`field_name.[non-terminal]*.terminal`  

The entire syntax is called a filter, and the individual parts are called operators. So, there are three types of operators : `terminal`, `non-terminal` and `conjuctions`.

`field_name` and `terminal` are always required, except when an equality (`=`) is implied, then only `field_name` is required.

A filter cannot end with a non-terminal operator.

```php
<?php

$users = User::items()
            ->filter(['username.trim.length.gt'=>5]);

$newUsers = User::items()
            ->filter(['date_joined.year.gte'=>2021]);
```
```sql
SELECT ... FROM user WHERE LENGTH(TRIM(username)) > 5

SELECT ... FROM user WHERE YEAR(date_joined) >= 2021
```
Remember, there are three types of operators : *terminal*, *non-terminal* and *conjunctions*.


## TERMINAL OPERATORS

`eq`  
Translates to `=` in SQL. This is the default filter, when none is provided.
```php
<?php
Users::items()->filter(['id.eq'=>4]);
//OR
Users::items()->filter(['id'=>4]);
```  

```sql
SELECT ... FROM user WHERE id = 4
```

`neq`  
Translates to `<>` in SQL.  
```php
<?php
Users::items()->filter(['id.neq'=>4]);
```  

```sql
SELECT ... FROM user WHERE id <> 4
//OR
SELECT ... FROM user WHERE id != 4
```

`lt`  
Translates to `<` in SQL.  
```php
<?php
Users::items()->filter(['id.lt'=>4]);
```  

```sql
SELECT ... FROM user WHERE id < 4
```

`gt`  
Translates to  `>` in SQL.  
```php
User::items()->filter(['id.gt'=>4]);
```  

```sql
SELECT ... FROM user WHERE id > 4
```

`lte`  
Translates to `<=` in SQL.  
```php
User::items()->filter(['id.lte'=>4]);
```  

```sql
SELECT ... FROM user WHERE id <= 4
```

`gte`  
Translates to `>=` in SQL.  
```php
User::items()->filter(['id.gte'=>4]);
```  

```sql
SELECT ... FROM user WHERE id >= 4
```

### NESTED QUERIES FOR AGGREGATES
Instead of passing scaler values to `eq`, `lt`, `gt`, `eq`, `lte`, and `gte`, you can pass an aggregated Handler.

```php
<?php
User::items()->filter(['salary.gte' => User::items()->aggregate(Aggregate::AVG, 'salary')])
```

```sql
SELECT ... FROM user WHERE salary >= ( SELECT AVG(salary) FROM user )
```

All Aggregates supported are  

`Aggregate::MAX`  

`Aggregate::MIN`  

`Aggregate::SUM`  

`Aggregate::AVG`

`Aggregate::COUNT`

----

`contains`  
Checks if a field contains a particular value.  
```php
User::items()->filter(['firstname.contains'=>'son']);
```  

```sql
SELECT ... FROM user WHERE firstname LIKE '%son%'
```

`icontains`  
Case insensitive version of `contains`.
```php
User::items()->filter(['firstname.icontains'=>'son']);
```  

`regex`  
Check if the value in a field matches a regular expression. 
```php
User::items()->filter(['firstname.regex'=>'^a']);
```  

```sql
SELECT ... FROM user WHERE firstname REGEXP '^a'.
```

`iregex`  
Case insensitive version of `regex`.  
```php
User::items()->filter(['firstname.iregex'=>'^a']);
```  

`startswith`  
Check if a field starts with a particular value.
```php
User::items()->filter(['firstname.starswith'=>'son']);
```  

```sql
SELECT ... FROM users WHERE firstname LIKE 'son%'
```

`endswith`  
Checks if a field ends with a particular value.
```php
User::items()->filter(['firstname.endswith'=>'son']);
```  

```sql
SELECT ... FROM users WHERE firstname LIKE '%son'
```  
`istartswith`  
Case insensitive version of `startswith`.
```php
User::items()->filter(['firstname.istarswith'=>'son']);
```  


`iendswith`  
Case insensitive version of `endswith`
```php
User::items()->filter(['firstname.iendswith'=>'son']);
```

`is_null`  

Checks if the field value is `NULL`. It requires the value passed to be either a boolean `true` or `false`. Not `1` or `0` or other 'truthy' or 'falsey' values.

```php
User::items()->filter(['firstname.is_null'=>true]);
User::items()->filter(['firstname.is_null'=>false]);
```  
```sql
SELECT ... FROM user WHERE firstname IS NULL
SELECT ... FROM user WHERE NOT (firstname IS NULL)
```  


`in`  
Checks if the field value exists in an array, or a subquery (Handler). If a string is passed, it is splitted into an array of characters. Notice how a single column is `projected` in the Handler.

```php
User::items()->filter(['firstname.in'=>['paul', 'peter']]);

User::items()->filter(['id.in' => Comment::items()->project('user')]);
```  
```sql
SELECT ... FROM user WHERE firstname IN ('paul', 'peter')
SELECT ... FROM user WHERE id IN ( SELECT user_id FROM comment )
```  


`not_in`
This performs the reverse of `in`. Again, notice how a single column is `projected` in the Handler.
```php
User::items()->filter(['firstname.not_in'=>['paul', 'peter']]);
User::items()->filter(['id.not_in' => Comment::items()->project('user')]);
```  
```sql
SELECT ... FROM user WHERE firstname NOT IN ('paul', 'peter')
SELECT ... FROM user WHERE id NOT IN ( SELECT user_id FROM comment )
```  

**NOTE**: Terminal filters are case sensitive.  

## CONJUNCTION OPERATORS
These are **and, or , &&, ||**. They are case insensitive. They are used to join together different parts of the predicate ( WHERE CLAUSE ).

```php
User::items()->filter(['firstname.startswith'=>'p', 'and', 'id.gte'=>7, 'or', 'id.lte'=>10]);
```

```sql
SELECT ... FROM user WHERE firstname LIKE 'p%' AND id >= 7 OR id <= 10
```

## NON-TERMINAL OPERATORS
These are used to change the value of the field or extract a particular value from a field. They cannot end a filter, but can be chained multiple times.

`lower`  
This returns a lowercase copy of the value in a field. 
```php
User::items()->filter(['firstname.lower.eq'=>'paul']);
```  
```sql
SELECT ... FROM user WHERE LOWER(firstname) = 'paul'
```  

`upper`  
This returns an uppercase copy of the value in a field. 
```php
User::object()->filter(['firstname.upper.eq'=>'PAUL']);
```  
```sql
SELECT ... FROM user WHERE LOWER(firstname) = 'paul'
```  

`length`  
This returns the character length of the value in a field. 
```php
User::items()->filter(['firstname.length.gt'=>5]);
```  
```sql
SELECT ... FROM user WHERE LENGTH(firstname) > 5
```  

`trim`  
This returns a trimmed copy of the value in a field.
```php
User::items()->filter(['firstname.trim.eq'=>'paul']);
```  
```sql
SELECT ... FROM user WHERE TRIM(firstname) = 'paul'
```  

`ltrim`  
This returns a left trimmed copy of the value in a field.
```php
User::items()->filter(['firstname.ltrim.eq'=>'paul']);
```  
```sql
SELECT ... FROM user WHERE LTRIM(firstname) = 'paul'
```  

`rtrim`  
This returns a right trimmed copy of the value in a field.  
```php
User::items()->filter(['firstname.rtrim.eq'=>'paul']);
```  
```sql
SELECT ... FROM user WHERE RTRIM(firstname) = 'paul'
```  

`date`  
This returns date part of the value in a field. 
```php
User::items()->filter(['date_joined.date.gt'=>'2020-09-11']);
```  
```sql
SELECT ... FROM user WHERE DATE(date_joined) > '2020-09-11';
```  

`time`  
This returns time part of the value in a field. 
```php
User::items()->filter(['date_joined.time.eq'=>'20:34:03']);
```  
```sql
SELECT ... FROM user WHERE TIME(date_joined) = '20:34:03'
```  

`year`  
This returns year part of the value in a field. 
```php
User::items()->filter(['date_joined.year.lt'=>2020]);
```  
```sql
SELECT ... FROM user WHERE YEAR(date_joined) < 2020
```  

`month`  
This returns month part of the value in a field.  
```php
User::items()->filter(['date_joined.month.eq'=>'02']);
User::items()->filter(['date_joined.month.eq'=>10]);
```  
```sql
SELECT ... FROM user WHERE MONTH(date_joined) = '02'
SELECT ... FROM user WHERE MONTH(date_joined) = 10
```  

`day`  
This returns day part of the value in a field.  
```php
User::items()->filter(['date_joined.day.eq'=>14]);
User::items()->filter(['date_joined.day.eq'=>'09']);
```  
```sql
SELECT ... FROM user WHERE DAY(date_joined) = 14
SELECT ... FROM user WHERE DAY(date_joined) = '09'
```  

`hour`  
This returns hour part of the value in a field.  
```php
User::items()->filter(['date_joined.hour.eq'=>10]);
User::items()->filter(['date_joined.hour.eq'=>'09']);
```  
```sql
SELECT ... FROM user WHERE HOUR(date_joined) = 10
SELECT ... FROM user WHERE HOUR(date_joined) = '09'
```  

`minute`  
This returns minute part of the value in a field.  
```php
User::items()->filter(['date_joined.minute.eq'=>15]);
User::items()->filter(['date_joined.minute.eq'=>'05']);
```  
```sql
SELECT ... FROM user WHERE MINUTE(date_joined) = 15
SELECT ... FROM user WHERE MINUTE(date_joined) = '05'
```

`second`  
This returns seconds part of the value in a field.  
```php
User::items()->filter(['date_joined.second.eq'=>10]);
User::items()->filter(['date_joined.second.eq'=>'02']);
```  
```sql
SELECT ... FROM user WHERE SECOND(date_joined) = 10
SELECT ... FROM user WHERE SECOND(date_joined) = '02'
```  

### CHAINING NON TERMINAL OPERATORS
Non-terminal operators can be chained.

```php
User::items()->filter(['firstname.trim.lower.upper.eq'=>'PAUL']);
```

```php
User::items()->filter(['firstname.trim.length.gt'=>8]);
```

----
**[Previous : Making Queries](making_queries.md)**  |
**[Next Part : Model Relationships](relationships.md)**