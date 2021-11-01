<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class User extends Model
{

	public $name;
	public $votes;
	public $created;
	public $parent;

	public static function schema()
	{
		return [
			'name' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = false;
			}),
			'votes' => Field::IntegerField(function (Column $column) {
				$column->null = false;
				$column->default = 0;
			}),
			'created' => Field::DateTimeField(function (Column $column) {
				$column->null = false;
				$column->default = function () {
					return date("Y-m-d H:i:s");
				};
			}),
			'parent' => Field::OneToOneField(self::class, function (Column $c) {
				$c->null = true;
			}, Index::UNIQUE)
		];
	}
}
