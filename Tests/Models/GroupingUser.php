<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;

class GroupingUser extends Model
{
    public $name;
    public $email;
    public $salary;
    public $status;

    public static function schema(): array
    {
        return [
            'name' => Field::CharField(function (Column $column) {
                $column->size = 255;
                $column->null = false;
            }),
            'email' => Field::CharField(function (Column $column) {
                $column->size = 255;
                $column->null = false;
            }, Index::UNIQUE),
            'salary' => Field::IntegerField(function (Column $column) {
                $column->default = 0;
            }),
            'status' => Field::CharField(function (Column $column) {
                $column->size = 50;
                $column->default = 'active';
            }, Index::INDEX),
        ];
    }
}
