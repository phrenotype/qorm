# Set Operations
**[ Table Of Contents](toc.md)**

`UNION`, `EXCEPT`, and `INTERSECTION` are fully supported, irrespective of sql dialect ( yes, even mysql or mariadb ).

It should be noted that every handler can be involved in set operations.

As usual, here's a quick (And quite useless) example.

```php
<?php

$h1 = User::items()->as('user1')->project('id', 'name', 'email');

$h2 = User::items()->as('user2')->project('id', 'name', 'email')->filter(['id.gt'=>4]);

$h3 = User::items()->as('user3')->project('id', 'name', 'email')->filter(['id.gt'=>2]);


$final = $h1->except($h3)->union($h2)->intersect($h3)->all();
```
If any dialect other than mysql, you will get the this  

```sql
SELECT DISTINCT id, name, email FROM user AS user1 

EXCEPT 

SELECT DISTINCT id, name, email FROM user AS user3 WHERE  user3.id > 2 

UNION 

SELECT id, name, email FROM user AS user2 WHERE  user2.id > 4 

INTERSECT 

SELECT DISTINCT id, name, email FROM user AS user3 WHERE  user3.id > 2
```  

If you are using mysql, you will end up with the code below

```sql
SELECT *
FROM
  (SELECT *
   FROM
     (SELECT DISTINCT user1.id,
                      user1.name,
                      user1.email
      FROM user AS user1) AS eeafefcdfdd
   WHERE NOT EXISTS
       (SELECT DISTINCT user3.id, user3.name, user3.email
        FROM user AS user3
        WHERE user3.id > 2
          AND eeafefcdfdd.id = user3.id
          AND eeafefcdfdd.name = user3.name
          AND eeafefcdfdd.email = user3.email)
   UNION
     (SELECT user2.id,
             user2.name,
             user2.email
      FROM user AS user2
      WHERE user2 .id > 4)) AS dafbedfdfebdc
WHERE EXISTS
    (SELECT DISTINCT user3.id,
                     user3.name,
                     user3.email
     FROM user AS user3
     WHERE user3.id > 2
       AND dafbedfdfebdc.id = user3.id
       AND dafbedfdfebdc.name = user3.name
       AND dafbedfdfebdc.email = user3.email)
```

## RULES / RECOMMENDATIONS
Aliases are required for all the handlers that will be directly involved in the set operations.


---
**[Previous Part : Grouping Aggregates](grouping.md)** | **[Next Part : SubQueries](subqueries.md)**