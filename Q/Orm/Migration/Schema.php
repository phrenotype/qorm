<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;
use Q\Orm\SetUp;

class Schema
{

    /**
     * Generate an index name.
     * 
     * @param string $table
     * @param string $field
     * 
     * @return string
     */
    public static function indexName(string $table, string $field): string
    {
        return "idx_{$table}_{$field}";
    }

    /**
     * Generate a foreign key name
     * @param mixed $table
     * @param mixed $field
     * 
     * @return string
     */
    public static function fkName($table, $field): string
    {
        return "fk_{$table}_{$field}";
    }

    /**
     * Write raw sql query.
     * 
     * @param string $sql
     * 
     * @return Operation
     */
    public static function query(string $sql): Operation
    {
        return new Operation(Operation::QUERY, [], $sql);
    }

    /**
     * Create a table.
     * 
     * @param mixed $table
     * @param callable $mutator
     * 
     * @return Operation
     */
    public static function create($table, callable $mutator): Operation
    {
        $table = new SchemaBuilder($table);
        $mutator($table);
        return new Operation(Operation::CREATE_TABLE, ['table' => $table->toTable()], $table->toTable()->toSql());
    }

    /**
     * Rename a table.
     * 
     * @param string $from
     * @param string $to
     * 
     * @return Operation
     */
    public static function rename(string $from, string $to): Operation
    {
        $sql = 'ALTER TABLE ' . Helpers::ticks($from) . ' RENAME TO ' . Helpers::ticks($to) . ';';
        return new Operation(Operation::RENAME_TABLE, ['from' => $from, 'to' => $to], $sql);
    }

    /**
     * Drop a table.
     * 
     * @param string $table
     * 
     * @return Operation
     */
    public static function drop(string $table): Operation
    {
        $sql = 'DROP TABLE ' . Helpers::ticks($table) . ';';
        return new Operation(Operation::DROP_TABLE, ['table' => $table], $sql);
    }

    /**
     * Drop a table only if it exists.
     * 
     * @param string $table
     * 
     * @return Operation
     */
    public static function dropIfExists(string $table): Operation
    {
        $sql = 'DROP TABLE IF EXISTS ' . Helpers::ticks($table) . ';';
        return new Operation(Operation::DROP_TABLE_IF_EXISTS, ['table' => $table], $sql);
    }

    /**
     * Add a column to a table.
     * 
     * @param string $table
     * @param callable $mutator
     * 
     * @return Operation
     */
    public static function addColumn(string $table, callable $mutator): Operation
    {
        $column = new Column;
        $mutator($column);
        $sql = CrossEngine::addColumnQuery(SetUp::$engine, $table, $column);
        return new Operation(Operation::ADD_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }

    /**
     * Drop a column from a table.
     * 
     * @param string $table
     * @param string $column
     * 
     * @return Operation
     */
    public static function dropColumn(string $table, string $column): Operation
    {
        $sql = CrossEngine::dropColumnQuery(SetUp::$engine, $table, $column);
        return new Operation(Operation::DROP_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }

    /**
     * Modify column definition.
     * 
     * @param string $table
     * @param callable $mutator
     * 
     * @return Operation
     */
    public static function modifyColumn(string $table, callable $mutator): Operation
    {
        $column = new Column;
        $mutator($column);
        $sql = CrossEngine::modifyColumnQuery(SetUp::$engine, $table, $column);
        return new Operation(Operation::MODIFY_COLUMN, ['table' => $table, 'column' => $column], $sql);
    }


    /**
     * Change a column, both name and definition. Can't detect this automatically. Will have to be written by hand.
     * 
     * @param mixed $table
     * @param string $oldName
     * @param callable $mutator
     * 
     * @return Operation
     */
    public static function changeColumn($table, string $oldName, callable $mutator): Operation
    {
        $column = new Column;
        $mutator($column);
        $sql = 'ALTER TABLE ' . Helpers::ticks($table) . ' CHANGE COLUMN ' . Helpers::ticks($oldName) . ' ' . $column->toSql() . ';';
        return new Operation(Operation::CHANGE_COLUMN, ['table' => $table, 'old_name' => $oldName, 'column' => $column], $sql);
    }

    /**
     * Add a unique index to a table.
     * 
     * @param string $table
     * @param string $field
     * 
     * @return Operation
     */
    public static function addUnique(string $table, string $field): Operation
    {
        $sql = CrossEngine::addUniqueIndexQuery(SetUp::$engine, $table, $field, self::indexName($table, $field));
        return new Operation(Operation::ADD_UNIQUE, ['table' => $table, 'field' => $field], $sql);
    }

    /**
     * Add an index to a table.
     * 
     * @param string $table
     * @param string $field
     * 
     * @return Operation
     */
    public static function addIndex(string $table, string $field): Operation
    {
        $sql = CrossEngine::addIndexQuery(SetUp::$engine, $table, $field, self::indexName($table, $field));
        return new Operation(Operation::ADD_INDEX, ['table' => $table, 'field' => $field], $sql);
    }

    /**
     * Add a primary key. Call this only once on a table in a migration.
     * 
     * @param string $table
     * @param string $field
     * 
     * @return Operation
     */
    public static function addPrimarykey(string $table, string $field): Operation
    {
        $sql = CrossEngine::addPrimaryKeyQuery(SetUp::$engine, $table, $field);
        return new Operation(Operation::ADD_PRIMARY_KEY, ['table' => $table, 'field' => $field], $sql);
    }

    /**
     * Drop a primary key from a table. Call this only once on a table in a migration.
     * 
     * @param string $table
     * 
     * @return Operation
     */
    public static function dropPrimarykey(string $table): Operation
    {
        $sql = CrossEngine::dropPrimaryKeyQuery(SetUp::$engine, $table);
        return new Operation(Operation::DROP_PRIMARY_KEY, ['table' => $table], $sql);
    }

    /**
     * Drop a unique index from a table.
     * 
     * @param mixed $table
     * @param mixed $field
     * @param null $indexTableName
     * 
     * @return Operation
     */
    public static function dropUnique($table, $field, $indexTableName = null): Operation
    {
        $sql = CrossEngine::dropIndexQuery(SetUp::$engine, $table, self::indexName(($indexTableName ?? $table), $field));
        return new Operation(Operation::DROP_UNIQUE, ['table' => $table, 'field' => $field], $sql);
    }

    /**
     * Drop index from a table.
     * 
     * @param mixed $table
     * @param mixed $field
     * @param null $indexTableName
     * 
     * @return Operation
     */
    public static function dropIndex($table, $field, $indexTableName = null): Operation
    {
        $sql = CrossEngine::dropIndexQuery(SetUp::$engine, $table, self::indexName(($indexTableName ?? $table), $field));
        return new Operation(Operation::DROP_INDEX, ['table' => $table, 'field' => $field], $sql);
    }

    /**
     * Add a foreign key to a table.
     * 
     * @param mixed $table
     * @param mixed $field
     * @param mixed $refTable
     * @param mixed $refField
     * @param mixed $onDelete
     * 
     * @return Operation
     */
    public static function addForeignKey($table, $field, $refTable, $refField, $onDelete): Operation
    {
        $fk = new ForeignKey($field, $refTable, $refField, $onDelete);
        $sql = CrossEngine::addForeignKeyQuery(SetUp::$engine, $table, self::fkName($table, $field), $fk);
        return new Operation(Operation::ADD_FOREIGN_KEY, ['table' => $table, 'field' => $field, 'refTable' => $refTable, 'refField' => $refField, 'onDelete' => $onDelete], $sql);
    }

    /**
     * Drop a foreign key from a table.
     * 
     * @param mixed $table
     * @param mixed $field
     * @param null $fkTableName
     * 
     * @return Operation
     */
    public static function dropForeignKey($table, $field, $fkTableName = null): Operation
    {
        $sql = CrossEngine::dropForeignKeyQuery(SetUp::$engine, $table, self::fkName(($fkTableName ?? $table), $field), $field);
        return new Operation(Operation::DROP_FOREIGN_KEY, ['table' => $table, 'field' => $field], $sql);
    }
}
