<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class Comment extends Model
{

	public $text;
	public $user;

	public static function schema()
	{
		return [
			'text' => Field::TextField(function (Column $column) {
				$column->null = false;
			}),
			'user' => Field::ManyToOneField(User::class, function (Column $column) {
				$column->null = false;
			}, Index::INDEX)
		];
	}
}
