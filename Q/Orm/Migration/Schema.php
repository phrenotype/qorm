<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;
use Q\Orm\SetUp;

class Schema
{

    public static function indexName(string $table, string $field)
    {
        return "idx_{$table}_{$field}";
    }

    public static function fkName($table, $field)
    {
        return "fk_{$table}_{$field}";
    }

    public static function query(string $sql)
    {
        return new Operation(Operation::QUERY, [], $sql);
    }

    public static function create($table, callable $mutator)
    {
        $table = new SchemaBuilder($table);
        $mutator($table);
        return new Operation(Operation::CREATE_TABLE, ['table' => $table->toTable()], $table->toTable()->toSql());
    }

    public static function rename(string $from, string $to)
    {
        $sql = 'ALTER TABLE ' . Helpers::ticks($from) . ' RENAME TO ' . Helpers::ticks($to) . ';';
        return new Operation(Operation::RENAME_TABLE, ['from' => $from, 'to' => $to], $sql);
    }

    public static function drop(string $table)
    {
        $sql = 'DROP TABLE ' . Helpers::ticks($table) . ';';
        return new Operation(Operation::DROP_TABLE, ['table' => $table], $sql);
    }

    public static function dropIfExists(string $table)
    {
        $sql = 'DROP TABLE IF EXISTS ' . Helpers::ticks($table) . ';';
        return new Operation(Operation::DROP_TABLE_IF_EXISTS, ['table' => $table], $sql);
    }

    public static function addColumn(string $table, callable $mutator)
    {
        $column = new Column;
        $mutator($column);        
        $sql = CrossEngine::addColumnQuery(SetUp::$engine, $table, $column);        
        return new Operation(Operation::ADD_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }

    public static function dropColumn(string $table, string $column)
    {
        $sql = CrossEngine::dropColumnQuery(SetUp::$engine, $table, $column);
        return new Operation(Operation::DROP_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }

    public static function modifyColumn(string $table, callable $mutator)
    {
        $column = new Column;
        $mutator($column);
        $sql = CrossEngine::modifyColumnQuery(SetUp::$engine, $table, $column);
        return new Operation(Operation::MODIFY_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }

    /* Can't detect this automatically. Will have to be written in by hand */
    public static function changeColumn($table, string $oldName, callable $mutator)
    {
        $column = new Column;
        $mutator($column);
        $sql = 'ALTER TABLE ' . Helpers::ticks($table) . ' CHANGE COLUMN ' . Helpers::ticks($oldName) . ' ' . $column->toSql() . ';';
        return new Operation(Operation::CHANGE_COLUMN, ['table' => $table, 'old_name' => $oldName, 'column' => $column], $sql);
    }

    public static function addUnique(string $table, string $field)
    {        
        $sql = CrossEngine::addUniqueIndexQuery(SetUp::$engine, $table, $field, self::indexName($table, $field));
        return new Operation(Operation::ADD_UNIQUE, ['table' => $table, 'field' => $field], $sql);
    }

    public static function addIndex(string $table, string $field)
    {
        $sql = CrossEngine::addIndexQuery(SetUp::$engine, $table, $field, self::indexName($table, $field));
        return new Operation(Operation::ADD_INDEX, ['table' => $table, 'field' => $field], $sql);
    }

    public static function addPrimarykey(string $table, string $field)
    {
        $sql = CrossEngine::addPrimaryKeyQuery(SetUp::$engine, $table, $field);
        return new Operation(Operation::ADD_PRIMARY_KEY, ['table' => $table, 'field' => $field], $sql);
    }

    public static function dropPrimarykey(string $table)
    {
        $sql = CrossEngine::dropPrimaryKeyQuery(SetUp::$engine, $table);
        return new Operation(Operation::DROP_PRIMARY_KEY, ['table' => $table], $sql);
    }

    public static function dropUnique($table, $field, $indexTableName = null)
    {
        $sql = CrossEngine::dropIndexQuery(SetUp::$engine, $table, self::indexName(($indexTableName ?? $table), $field));
        return new Operation(Operation::DROP_UNIQUE, ['table' => $table, 'field' => $field], $sql);
    }

    public static function dropIndex($table, $field, $indexTableName = null)
    {
        $sql = CrossEngine::dropIndexQuery(SetUp::$engine, $table, self::indexName(($indexTableName ?? $table), $field));
        return new Operation(Operation::DROP_INDEX, ['table' => $table, 'field' => $field], $sql);
    }

    public static function addForeignKey($table, $field, $refTable, $refField, $onDelete)
    {
        $fk = new ForeignKey($field, $refTable, $refField, $onDelete);
        $sql = CrossEngine::addForeignKeyQuery(SetUp::$engine, $table, self::fkName($table, $field), $fk);
        return new Operation(Operation::ADD_FOREIGN_KEY, ['table' => $table, 'field' => $field, 'refTable' => $refTable, 'refField' => $refField, 'onDelete' => $onDelete], $sql);
    }

    public static function dropForeignKey($table, $field, $fkTableName = null)
    {
        $sql = CrossEngine::dropForeignKeyQuery(SetUp::$engine, $table, self::fkName(($fkTableName ?? $table), $field), $field);
        return new Operation(Operation::DROP_FOREIGN_KEY, ['table' => $table, 'field' => $field], $sql);
    }
}