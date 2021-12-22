<?php

use \Q\Orm\Field;
use \Q\Orm\Migration\Migration;
use \Q\Orm\Migration\SchemaBuilder;
use \Q\Orm\Migration\Schema;
use \Q\Orm\Migration\Column;

class Migration0001 extends Migration {

	public function __construct(){

		$this->operations = [
			
			function(){
				return Schema::create('user', function (SchemaBuilder $tb) {
					$tb->string('name', array (
						  'name' => 'name',
						  'type' => Field::CHAR,
						  'size' => 255,
						  'null' => false,
						));
					$tb->string('email', array (
						  'name' => 'email',
						  'type' => Field::CHAR,
						  'size' => 255,
						  'null' => false,
						));
					$tb->integer('salary', array (
						  'name' => 'salary',
						  'type' => 'bigint',
						  'null' => false,
						  'default' => 0,
						));
					$tb->integer('sponsor', array (
						  'name' => 'sponsor',
						  'type' => 'bigint',
						  'size' => 20,
						  'unsigned' => true,
						  'null' => true,
						));
					$tb->datetime('created', array (
						  'name' => 'created',
						  'type' => Field::DATETIME,
						  'null' => false,
						));
					$tb->unique('email');
					$tb->index('sponsor');
					$tb->foreignKey('sponsor', 'user', 'id', 'RESTRICT');

				});
			},
			
			function(){
				return Schema::create('address', function (SchemaBuilder $tb) {
					$tb->string('zip', array (
						  'name' => 'zip',
						  'type' => Field::CHAR,
						  'size' => 255,
						  'null' => true,
						));
					$tb->integer('owner', array (
						  'name' => 'owner',
						  'type' => 'bigint',
						  'size' => 20,
						  'unsigned' => true,
						  'null' => false,
						));
					$tb->index('zip');
					$tb->unique('owner');
					$tb->foreignKey('owner', 'user', 'id', 'RESTRICT');

				});
			},
			
			function(){
				return Schema::create('post', function (SchemaBuilder $tb) {
					$tb->string('title', array (
						  'name' => 'title',
						  'type' => Field::CHAR,
						  'size' => 255,
						  'null' => false,
						));
					$tb->text('body', array (
						  'name' => 'body',
						  'type' => Field::TEXT,
						  'null' => false,
						));
					$tb->integer('user_id', array (
						  'name' => 'user_id',
						  'type' => 'bigint',
						  'size' => 20,
						  'unsigned' => true,
						  'null' => false,
						));
					$tb->index('user_id');
					$tb->foreignKey('user_id', 'user', 'id', 'RESTRICT');

				});
			},
			
			function(){
				return Schema::create('comment', function (SchemaBuilder $tb) {
					$tb->text('text', array (
						  'name' => 'text',
						  'type' => Field::TEXT,
						  'null' => false,
						));
					$tb->integer('user_id', array (
						  'name' => 'user_id',
						  'type' => 'bigint',
						  'size' => 20,
						  'unsigned' => true,
						  'null' => false,
						));
					$tb->integer('post_id', array (
						  'name' => 'post_id',
						  'type' => 'bigint',
						  'size' => 20,
						  'unsigned' => true,
						  'null' => false,
						));
					$tb->index('user_id');
					$tb->index('post_id');
					$tb->foreignKey('user_id', 'user', 'id', 'RESTRICT');
					$tb->foreignKey('post_id', 'post', 'id', 'RESTRICT');

				});
			},
		];

		$this->reverse = [
			
			function(){
				return Schema::dropIfExists('comment');
			},
			
			function(){
				return Schema::dropIfExists('post');
			},
			
			function(){
				return Schema::dropIfExists('address');
			},
			
			function(){
				return Schema::dropIfExists('user');
			},
		];
	}
}