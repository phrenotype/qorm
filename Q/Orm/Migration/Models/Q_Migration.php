<?php

namespace Q\Orm\Migration\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\Column;


class Q_Migration extends Model
{

	public $name;
	public $applied;

	public static function schema(): array
	{
		return [
			'name' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = false;
			}, Index::UNIQUE),
			'applied' => Field::DateTimeField(function (Column $column) {
				$column->null = true;
			}),
		];
	}
}
