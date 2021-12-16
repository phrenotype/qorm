<?php

use PHPUnit\Framework\TestCase;
use Q\Orm\Helpers;
use Tests\Models\Comment;

class HelpersTest extends TestCase
{

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }


    public function isRefFieldDataProvider()
    {
        return [
            ['user_id', Comment::class, true],
            ['user', Comment::class, true],
            ['text', Comment::class, false]
        ];
    }

    /**
     * @dataProvider isRefFieldDataProvider
     */
    public function testIsRefField($field, $class, $expected)
    {
        $actual = Helpers::isRefField($field, $class);
        $this->assertEquals($expected, $actual);
    }

    public function removeDataProvider()
    {
        $arr = ['a', 'b', 'c'];
        unset($arr[0]);
        return [
            ['a', ['a', 'b', 'c'], $arr],
            ['d', ['a', 'b', 'c'], ['a', 'b', 'c']],
        ];
    }

    /**
     * @dataProvider removeDataProvider
    */
    public function testRemove($value, $assoc, $expected)
    {
        $actual = Helpers::remove($value, $assoc);
        $this->assertEquals($expected, $actual);
    }

    public function modelNameToTableNameDataProvider(){
        return [
            ['ThriftUser', 'thrift_user'],
            ['_Thrift_99', 'thrift_99'],
            ['Thrift_99_', 'thrift_99'],
            ['Q_Migration', 'q_migration'],
        ];
    }

    /**
     * @dataProvider modelNameToTableNameDataProvider
     */
    public function testModelNameToTableName($modelName, $expected){
        $actual = Helpers::modelNameToTableName($modelName);
        $this->assertEquals($expected, $actual);
    }
    

    public function tableNameToModelNameDataProvider(){
        return [
            ['thrift_user', 'ThriftUser'],
            ['_thrift_user_', 'ThriftUser'],
            ['user_home_address', 'UserHomeAddress']
        ];
    }

    /**
     * @dataProvider tableNameToModelNameDataProvider
     */
    public function testTableNameToModelName($tableName, $expected){
        $actual = Helpers::tableNameToModelName($tableName);
        $this->assertEquals($expected, $actual);
    }

}
