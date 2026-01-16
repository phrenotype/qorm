<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;

class ChildNullable extends Model
{
    public $name;
    public $parent;

    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function(Column $c) {
                $c->size = 255;
            }),
            'parent' => Field::ManyToOneField(PeculiarUser::class, function(Column $c) {
                $c->null = true;
            }, Index::INDEX),
        ];
    }
}
