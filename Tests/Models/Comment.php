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
	public $post;

	public static function schema(): array
	{
		return [
			'text' => Field::TextField(function (Column $column) {
				$column->null = false;
			}),
			'user' => Field::ManyToOneField(User::class, function (Column $column) {
				$column->null = false;
			}, Index::INDEX),
			'post' => Field::ManyToOneField(Post::class, function (Column $c) {
				$c->null = false;
			}, Index::INDEX),
		];
	}
}
