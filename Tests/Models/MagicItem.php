<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;

class MagicItem extends Model
{
    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function (Column $c) { $c->size = 255; $c->null = true; }),
            'price' => Field::DecimalField(function (Column $c) { $c->null = true; }),
            'payment_status' => Field::CharField(function (Column $c) { $c->size = 50; $c->null = true; }),
        ];
    }
}
