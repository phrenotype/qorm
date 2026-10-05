<?php

namespace Tests\Migration\Fixtures;

use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;
use Q\Orm\Model;

/**
 * Fixture models for LiveRoundTripTest only.
 *
 * This file is NOT named *Test.php so PHPUnit never includes it during
 * suite discovery, and it is require_once'd from the test's setUp() —
 * which runs inside the isolated child process. The main PHPUnit
 * process must never declare these classes: Helpers::getDeclaredModels()
 * feeds production code paths and HelpersTest asserts an exact count.
 */
class RtHost extends Model
{
    public $code;
    public $name;

    public static function schema(): array
    {
        return [
            'code' => Field::Peculiar(),
            'name' => Field::CharField(function (Column $c) {
                $c->size = 100;
            }),
        ];
    }
}

class RtChild extends Model
{
    public $parent;
    public $title;
    public $active;

    public static function schema(): array
    {
        return [
            'parent' => Field::ManyToOneField(RtHost::class, function (Column $c) {
                $c->null = true;
            }, Index::INDEX),
            'title' => Field::CharField(function (Column $c) {
                $c->size = 50;
                $c->null = true;
                $c->default = 't';
            }),
            'active' => Field::BooleanField(function (Column $c) {
                $c->default = false;
            }),
        ];
    }
}

class RtKitchen extends Model
{
    public $big;
    public $kind;
    public $ratio;
    public $count;
    public $note;
    public $stamped;
    public $price;

    public static function schema(): array
    {
        return [
            'big' => Field::IntegerField(function (Column $c) {
                $c->size = 20;
                $c->unsigned = true;
            }),
            'kind' => Field::EnumField(function (Column $c) {
                $c->size = ['a', 'b'];
                $c->default = 'a';
            }),
            'ratio' => Field::FloatField(function (Column $c) {
                $c->default = 0.0;
            }),
            'count' => Field::IntegerField(function (Column $c) {
                $c->default = 0;
            }),
            'note' => Field::TextField(function (Column $c) {
                $c->null = true;
            }),
            'stamped' => Field::DateTimeNow(true),
            'price' => Field::DecimalField(function (Column $c) {
                $c->size = [12, 2];
            }),
        ];
    }
}
