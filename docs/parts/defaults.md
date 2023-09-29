# DEFAULTS
**[ Table Of Contents](toc.md)**

Defaults are a a way to provide initial values for fields in a table when non is specified by a user (developer).

In Q Orm, the `default` attribute of the `Q\Orm\Migration\Column` object is used to define default values.

Note, though, that if a static value is provided, all rows inserted with have that same static value.

If, however, you want a dynamic value generated, for instance, the current date or time or some random key, then you need to set it's value to a closure, that returns the value. The closure will be invoked each time a new row will be inserted.

```php

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class Book extends Model {

    public $title;
    public $likes;
    public $date_added;

    public static function schema(){
        return [
            'title' => Field::CharField(function(Column $column){
                $column->size = 255;
                $column->null = true;
            }),
            'likes' => Field::IntegerField(function(Column $column){
                $column->default = 0;
                $column->null = true;
            }),
            'date_added' => Field::DateTimeField(function(Column $column){
                $column->null = false;
                $column->default = function(){ return date("Y-m-d H:i:s"); };
            }),
        ];
    }
}
```

---
**[Previous Part : Migration Commands](cli.md)** | **[ Next Part : Peculiar Ids ]( peculiar.md )**
