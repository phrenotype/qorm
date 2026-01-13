<?php

namespace Tests;

use Tests\Models\AllFieldsModel;
use Q\Orm\Connection;

class FieldTest extends QormTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $pdo = Connection::getInstance();
        $pdo->exec("DROP TABLE IF EXISTS all_fields_model");
        \Tests\Helpers\TestUtil::createTableFromModel(AllFieldsModel::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Connection::getInstance()->exec("DELETE FROM all_fields_model");
    }

    public function testAllFieldTypes()
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        AllFieldsModel::items()->create([
            'string_field' => 'Hello World',
            'text_field' => 'Long text content goes here...',
            'int_field' => 42,
            'float_field' => 3.14159,
            'bool_field' => true,
            'date_field' => $today,
            'datetime_field' => $now,
            'enum_field' => 'a'
        ]);

        $item = AllFieldsModel::items()->one();

        $this->assertEquals('Hello World', $item->string_field);
        $this->assertEquals('Long text content goes here...', $item->text_field);
        $this->assertEquals(42, $item->int_field);
        $this->assertEquals(3.14159, $item->float_field);
        $this->assertTrue((bool)$item->bool_field); // SQLite stores bool as 1/0
        $this->assertEquals($today, $item->date_field);
        $this->assertEquals($now, $item->datetime_field);
        $this->assertEquals('a', $item->enum_field);
    }
    
    public function testBooleanStorage()
    {
        AllFieldsModel::items()->create(['bool_field' => false]);
        $item = AllFieldsModel::items()->one();
        $this->assertFalse((bool)$item->bool_field);
    }

    public function testEnumConstraint() 
    {
        // Enums in QORM are typically validated at application level or DB level if supported.
        // SQLite doesn't strictly enforce ENUM types like MySQL, but we check if it saves.
        AllFieldsModel::items()->create(['enum_field' => 'b']);
        $item = AllFieldsModel::items()->one();
        $this->assertEquals('b', $item->enum_field);
    }
}
