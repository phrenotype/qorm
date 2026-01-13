<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;

class AllFieldsModel extends Model
{
    public $string_field;
    public $text_field;
    public $int_field;
    public $float_field;
    public $bool_field;
    public $date_field;
    public $datetime_field;
    public $enum_field;

    public static function schema(): array
    {
        return [
            'string_field' => Field::CharField(function (Column $c) {
                $c->size = 255;
                $c->default = 'default string';
            }),
            'text_field' => Field::TextField(function (Column $c) {
                $c->default = 'default text';
            }),
            'int_field' => Field::IntegerField(function (Column $c) {
                $c->default = 0;
            }),
            'float_field' => Field::FloatField(function (Column $c) {
                $c->default = 0.0;
            }),
            'bool_field' => Field::BooleanField(function (Column $c) {
                $c->default = false;
            }),
            'date_field' => Field::DateField(function (Column $c) {
                $c->default = function () {
                    return date('Y-m-d'); };
            }),
            'datetime_field' => Field::DateTimeField(function (Column $c) {
                $c->default = function () {
                    return date('Y-m-d H:i:s'); };
            }),
            'enum_field' => Field::EnumField(function (Column $c) {
                $c->size = ['a', 'b', 'c'];
                $c->default = 'a';
            }),
        ];
    }
}
