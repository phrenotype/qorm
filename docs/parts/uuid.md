# UUID's
**[ Table Of Contents](toc.md)**

If you don't know what UUID's are, then this section is probably not for you.

Not everyone likes using auto incrementing integers, or integers in general as primary keys. Some prefer uuid's. And that's okay. Here's how to implement a model that generates a random uuid for a column anytime a record is created.

First either import a uuid library or write your own function ( at your own risk ) like so.
```php
<?php
function uuidv4()
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
```

Then create the model that will need a uuid column.

```php
<?php

namespace models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;


class User extends Model {

    public $uuid_field;
    public $name;
    public $date_joined;


    public static function schema() : array {
        return [
            'uuid_field' => FieldCharField(function(Column $column){
                $column->size = 255;
                $column->default = function(){ return uuidv4(); }
            }, INDEX::UNIQUE),
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

```

If you want `uuid_field` to be the primary key, then use `Index::PRIMARY_KEY` instead of `Index::UNIQUE`.

That's it. Everytime a record (row) is inserted, a uuid is generated and inserted along side.

---
**[Previous Part : Peculiar Ids ](peculiar.md)** | **[Next Part: Joins](joins.md)**
