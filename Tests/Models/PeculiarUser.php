<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;

class PeculiarUser extends Model
{
    public $name;
    public $peculiar;

    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function(Column $c) {
                $c->size = 255;
            }),
            'peculiar' => Field::Peculiar(),
        ];
    }
}
