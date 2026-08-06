<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;

class StockItem extends Model
{
    public $name;
    public $qty;
    public $cost;

    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function (Column $column) {
                $column->size = 255;
                $column->null = false;
            }),
            'qty' => Field::DecimalField(function (Column $column) {
                $column->null = false;
                $column->default = 0;
            }),
            'cost' => Field::DecimalField(function (Column $column) {
                $column->null = false;
                $column->default = 0;
            }),
        ];
    }
}
