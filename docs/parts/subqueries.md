# NESTED QUERIES

In the book `Beginning SQL queries`, Clare Churcher classified subqueries into three(3). I will replicate it here.  

1. Those that return a single value
1. Those that return a set of values
1. Those that check existence

## SubQueries That Return A Single Value
This are often used with aggregates to compare values.  
The operators involved here are `eq`, `lt`, `gt`, `eq`, `lte`, and `gte`, to which you **must** pass an **aggregated Handler**.

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


## SubQueries That Return A Set Of Values  
This is usually used to check if a certain value is in a set of values. The operators involved here are the `in` and `not_in` operators. 
It is **compulsory** that the nested Handler **projects a single field**. 

```php
User::items()->filter(['id.in' => Comment::items()->project('user')]);
```  
```sql
SELECT ... FROM user WHERE id IN ( SELECT user_id FROM comment )
```  
**Notice how a single field is projected in the nested Handler.**

## SubQueries That Check For Existence
For this, the `.exists` operator is used. Here, the nested Handler **does not have to project any field at all**, since it is an existence check.

```php
User::items()->filter(['.exists' => Comment::items()]);
```  
```sql
SELECT ... FROM user WHERE EXISTS ( SELECT .... FROM comment )
```  
