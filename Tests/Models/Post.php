<?php

namespace Tests\Models;

use Q\Orm\Model;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;

class Post extends Model
{

	public $title;
	public $body;
	public $user;

	public static function schema(): array
	{
		return [
			'title' => Field::CharField(function (Column $column) {
				$column->name = 'realtitle';
				$column->size = 255;
				$column->null = false;
			}),
			'body' => Field::TextField(function (Column $column) {
				$column->null = false;
			}),
			'user' => Field::ManyToOneField(
				User::class,
				function (Column $column) {
					$column->null = false;
				},
				Index::INDEX
			),
		];
	}
}
