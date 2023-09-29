<?php

namespace Q\Orm\Engines;

use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Table;
use Q\Orm\SetUp;

class CrossEngine
{
    private static $pdo;
    private static $rollback;

    public static function isRollback(bool $rollback = null)
    {
        if ($rollback !== null) {
            //We are setting
            self::$rollback = $rollback;
        } else {
            //We are getting
            return self::$rollback;
        }
    }

    public static function setPDO(\PDO $pdo)
    {
        self::$pdo = $pdo;
    }

    public static function createMigrationsTableQuery($engine)
    {
        return Decider::decide($engine,  function () {
            return Mysql::createMigrationsTableQuery();
        },  function () {
            return Sqlite::createMigrationsTableQuery();
        });
    }

    public static function columnToSql(Column $column)
    {
        return Decider::decide(
            SetUp::$engine,
            function () use ($column) {
                return Mysql::columnToSql($column);
            },
            function () use ($column) {
                return Sqlite::columnToSql($column);
            }
        );
    }

    public static function tableFromModels(string $table)
    {
        return Decider::decide(
            Setup::$engine,
            function () use ($table) {
                return Mysql::tableFromModels($table);
            },
            function () use ($table) {
                return Sqlite::tableFromModels($table);
            }
        );
    }

    public static function tableToSql(Table $table)
    {
        return Decider::decide(
            Setup::$engine,
            function () use ($table) {
                return Mysql::tableToSql($table);
            },
            function () use ($table) {
                return Sqlite::tableToSql($table);
            }
        );
    }

    public static function endOfCreateTable($engine)
    {
        return Decider::decide(
            $engine,
            function () {
                return Mysql::endOfCreateTable();
            },
            function () {
                return Sqlite::endOfCreateTable();
            }
        );
    }

    public static function schemaToTables($engine, $dbName)
    {
        return Decider::decide(
            $engine,
            function () use ($engine, $dbName) {
                return Mysql::schemaToTables(self::$pdo, $dbName);
            },
            function () use ($engine, $dbName) {
                return Sqlite::schemaToTables(self::$pdo, $dbName);
            }
        );
    }

    public static function modelsToTables(array $models): array
    {
        return Decider::decide(
            Setup::$engine,
            function () use ($models) {
                return Mysql::modelsToTables($models);
            },
            function () use ($models) {
                return Sqlite::modelsToTables($models);
            },
            []
        );
    }

    public static function addUniqueIndexQuery($engine, $table, $field, $indexName)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $field, $indexName) {
                return Mysql::addUniqueIndexQuery($table, $field, $indexName);
            },
            function () use ($table, $field, $indexName) {
                return Sqlite::addUniqueIndexQuery($table, $field, $indexName);
            },
            []
        );
    }

    public static function addIndexQuery($engine, $table, $field, $indexName)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $field, $indexName) {
                return Mysql::addIndexQuery($table, $field, $indexName);
            },
            function () use ($table, $field, $indexName) {
                return Sqlite::addIndexQuery($table, $field, $indexName);
            }
        );
    }

    public static function dropIndexQuery($engine, $table, $indexName)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $indexName) {
                return Mysql::dropIndexQuery($table, $indexName);
            },
            function () use ($table, $indexName) {
                return Sqlite::dropIndexQuery($table, $indexName);
            }
        );
    }

    public static function addForeignKeyQuery($engine, $table, $fkName, ForeignKey $fk)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $fkName, $fk) {
                return Mysql::addForeignKeyQuery($table, $fkName, $fk);
            },
            function () use ($table, $fkName, $fk) {
                return Sqlite::addForeignKeyQuery($table, $fkName, $fk);
            }
        );
    }

    public static function dropForeignKeyQuery($engine, $table, $fkName, $field)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $fkName, $field) {
                return Mysql::dropForeignKeyQuery($table, $fkName, $field);
            },
            function () use ($table, $fkName, $field) {
                return Sqlite::dropForeignKeyQuery($table, $fkName, $field);
            }
        );
    }

    public static function addColumnQuery($engine, string $table, Column $column)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $column) {
                return Mysql::addColumnQuery($table, $column);
            },
            function () use ($table, $column) {
                return Sqlite::addColumnQuery($table, $column);
            }
        );
    }

    public static function modifyColumnQuery($engine, string $table, Column $column)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $column) {
                return Mysql::modifyColumnQuery($table, $column);
            },
            function () use ($table, $column) {
                return Sqlite::modifyColumnQuery($table, $column);
            }
        );
    }

    public static function changeColumnQuery($engine, string $table, string $oldName, Column $column)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $column, $oldName) {
                return Mysql::changeColumnQuery($table, $oldName, $column);
            },
            function () use ($table, $column, $oldName) {
                return Sqlite::changeColumnQuery($table, $oldName, $column);
            }
        );
    }

    public static function dropColumnQuery($engine, string $table, string $column)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $column) {
                return Mysql::dropColumnQuery($table, $column);
            },
            function () use ($table, $column) {
                return Sqlite::dropColumnQuery($table, $column);
            }
        );
    }

    public static function addPrimaryKeyQuery($engine, $table, $field)
    {
        return Decider::decide(
            $engine,
            function () use ($table, $field) {
                return Mysql::addPrimaryKeyQuery($table, $field);
            },
            function () use ($table, $field) {
                return Sqlite::addPrimaryKeyQuery($table, $field);
            }
        );
    }

    public static function dropPrimaryKeyQuery($engine, $table)
    {
        return Decider::decide(
            $engine,
            function () use ($table) {
                return Mysql::dropPrimaryKeyQuery($table);
            },
            function () use ($table) {
                return Sqlite::dropPrimaryKeyQuery($table);
            }
        );
    }
}
