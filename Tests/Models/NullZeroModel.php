<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;

class NullZeroModel extends Model
{
    public $name;
    public $price;
    public $active;

    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function (Column $c) {
                $c->size = 255;
                $c->null = true;
            }),
            'price' => Field::DecimalField(function (Column $c) {
                $c->null = true;
            }),
            'active' => Field::BooleanField(function (Column $c) {
                $c->null = true;
            }),
        ];
    }
}
