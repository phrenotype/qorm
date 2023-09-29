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
	public $email;
	public $salary;
	public $sponsor;
	public $created;

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
				$column->null = false;
				$column->default = 0;
			}),
			'sponsor' => Field::OneToOneField(self::class, function (Column $column) {
				$column->null = true;
			}, Index::INDEX),
			'created' => Field::DateTimeField(function (Column $column) {
				$column->null = false;
				$column->default = function () {
					return date("Y-m-d H:i:s");
				};
			})
		];
	}
}
