<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class Address extends Model
{

	public $zip;
	public $owner;
	public $date;


	public static function schema()
	{
		return [
			'zip' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = true;
			}, Index::INDEX),
			'date' => Field::DateTimeField(function (Column $column) {
				$column->null = true;
				$column->default = function () {
					return date('Y-m-d H:i:s');
				};
			}),
			'owner' => Field::OneToOneField(User::class, function (Column $column) {
				$column->null = false;
			}, Index::UNIQUE)
		];
	}
}
