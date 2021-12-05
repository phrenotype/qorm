# GROUPING AGGREGATES
**[ Table Of Contents](toc.md)**

Again, if you do not know what it means to group aggregates, you may not follow along.

Just like joins, grouping aggregates it quite straight forward here. Two methods are made available `Q\Orm\Handler::group_by(...$fields)` and `Q\Orm\Handler::having(array $filters)`.

Here's a quick example.

```php
<?php

User::items()->project('votes', 'count(id) as n')
    ->group_by('votes')
    ->having(['count(id).gt' => 1])
    ->all();
```

```sql
SELECT votes, COUNT(id) AS n FROM user GROUP BY votes HAVING  COUNT(id) > 1
```

Note how the aggregate is filtered in the `having` method. Only terminal operators can be used in having filters.

The result comes back as objects of the `User` model, with the projected fields as attributes on them.

## RECOMMENDATIONS
When projecting for a grouping, try to alias the aggregate, in order to make it easier for you to access the value.


---
**[Previous Part : Joins](joins.md)**  |  **[Next Part : Set Operations](sets.md)** 