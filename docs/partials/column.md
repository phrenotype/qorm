# Column
This class is found at `Q\Orm\Migration\Column`. It represents a database table column definition.

## Attributes

`name`  
The name of the column.

`type`  
This is the type (data type or domain) of the column.

`size`  
This is the size/precision of the column. It can be a string, a number or an array ( as in the case of `enums` ).  

If the field is an `enum` field, the size **must** be an array.

`unsigned`  
A boolean that indicates if this column contains an unsigned integer.

`null`  
If the value is set to `true`, it results in `NULL` in SQL. `false` results in `NOT NULL`. The default is `false`.

`default`  
Can be a static value or a callable. It is used to set the default value when inserting a new record. If set to a callable, the return value of the callable is used each time a new record is inserted.

`auto_increment`  
A boolean that indicates if this column should be autoincremented. There is no default.