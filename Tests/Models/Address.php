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


	public static function schema(): array
	{
		return [
			'zip' => Field::CharField(function (Column $column) {
				$column->size = 255;
				$column->null = true;
			}, Index::INDEX),
			'owner' => Field::OneToOneField(User::class, function (Column $c) {
				$c->null = false;
			}, Index::UNIQUE)
		];
	}
}
