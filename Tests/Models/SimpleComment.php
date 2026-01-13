<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class SimpleComment extends Model
{
    public $text;
    public $user;
    public $simple_post;

    public static function schema(): array
    {
        return [
            'text' => Field::TextField(function (Column $column) {
                $column->null = false;
            }),
            'user' => Field::ManyToOneField(User::class, function (Column $column) {
                $column->null = false;
            }, Index::INDEX),
            // Snake case field name for multi-word model SimplePost
            'simple_post' => Field::ManyToOneField(SimplePost::class, function (Column $c) {
                $c->name = 'simple_post_id';
                $c->null = false;
            }, Index::INDEX),
        ];
    }
}
