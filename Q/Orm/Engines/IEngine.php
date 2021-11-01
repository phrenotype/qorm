<?php

namespace Q\Orm\Engines;

use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Table;

interface IEngine{
    public static function createMigrationsTableQuery();
    public static function tableToSql(Table $table);
    public static function columnToSql(Column $column);
    public static function tableFromModels(string $table);
    public static function endOfCreateTable();

    public static function schemaToTables(\PDO $pdo, string $dbName);
    public static function modelsToTables(array $models);

    public static function addUniqueIndexQuery(string $table, string $field, string $indexName);
    public static function addIndexQuery(string $table, string $field, string $indexName);
    public static function dropIndexQuery(string $table, string $indexName);

    public static function addForeignKeyQuery(string $table, string $fkName, ForeignKey $fk);
    public static function dropForeignKeyQuery(string $table, string $fkName, string $field);

    public static function addColumnQuery(string $table, Column $column);
    public static function dropColumnQuery(string $table, string $column);
    public static function modifyColumnQuery(string $table, Column $column);
    public static function changeColumnQuery(string $table, string $oldName, Column $column);

    public static function addPrimaryKeyQuery(string $table, string $field);
    public static function dropPrimaryKeyQuery(string $table);

}