# PECULIAR IDs
**[ Table Of Contents](toc.md)**

Peculiar Identifiers are a way to generate unique 64-bit ids to server as primary or unique keys.

```php
class Book extends Model {

    public $uid;
    public $title;

    public static function schema(){
        return [
            'uid' => Field::Peculiar(),

            'title' => Field::CharField(function(Column $column){
                $column->size = 255;
                $column->null = true;
            })
        ];
    }
}
```

---
**[Previous Part : Defaults](defaults.md)** | **[ Next Part : UUIDs ]( uuid.md )**
