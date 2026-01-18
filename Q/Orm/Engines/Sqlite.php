<?php

namespace Q\Orm\Engines;

use Q\Orm\Connection;
use Q\Orm\Helpers;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\Schema;
use Q\Orm\Migration\Table;
use Q\Orm\Migration\TableModelFinder;

class Sqlite implements IEngine
{
    private static $altered = false;
    private static $alteredTables = [];

    public static function createMigrationsTableQuery()
    {
        $table = Helpers::ticks('q_migration');

        $id = Helpers::ticks('id');
        $name = Helpers::ticks('name');
        $applied = Helpers::ticks('applied');

        $query = "CREATE TABLE IF NOT EXISTS $table(";
        $query .= "$id INTEGER PRIMARY KEY, $name VARCHAR(255) NOT NULL, $applied DATETIME NULL, UNIQUE($name)";
        $query .= ")";

        return $query;
    }


    public static function tableToSql(Table $table)
    {
        $sql = '';

        /* check indexes if primary key exists, if not create one */
        $primary_key_set = null;
        foreach ($table->indexes as $index) {
            if ($index->type === Index::PRIMARY_KEY) {
                $primary_key_set = true;
            }
        }

        if (!$primary_key_set) {
            array_unshift($table->fields, new Column('id', 'INTEGER', ['null' => false, 'auto_increment' => true]));
            array_unshift($table->indexes, new Index('id', Index::PRIMARY_KEY));
        } else {
            //Cannnot Add an autoincrementing non pk id field in sqlite. The User will have to rely on rowid or whatever his/her pk is
        }


        /* Addings Fields */
        $isPrimaryKey = function ($fieldName) use ($table) {
            $yes = false;
            foreach ($table->indexes as $index) {
                if ($index->field === $fieldName && $index->type === Index::PRIMARY_KEY) {
                    $yes = true;
                    break;
                }
            }
            return $yes;
        };

        $isForeignKey = function ($fieldName) use ($table) {
            $yes = false;
            foreach ($table->foreignKeys as $fk) {
                if ($fk->field === $fieldName) {
                    $yes = $fk;
                    break;
                }
            }
            return $yes;
        };

        $sql .= 'CREATE TABLE IF NOT EXISTS ' . Helpers::ticks($table->name) . '(';

        $sql .= array_reduce($table->fields, function ($c, $i) use ($isPrimaryKey, $isForeignKey) {
            $r = '';
            if ($isPrimaryKey($i->name) && $i->name !== 'id') {
                $toSql = str_ireplace('AUTOINCREMENT', '', $i->toSql());
                $r .= $c . rtrim($toSql) . ' PRIMARY KEY';
            } else if ($isPrimaryKey($i->name) && $i->name === 'id') {
                $toSql = str_ireplace('AUTOINCREMENT', '', $i->toSql());
                $r .= $c . rtrim($toSql) . ' PRIMARY KEY AUTOINCREMENT';
            } else {
                $r .= $c . $i->toSql();
            }

            if ($fk = $isForeignKey($i->name)) {
                $r .= ' REFERENCES ' . Helpers::ticks($fk->refTable) . '(' . Helpers::ticks($fk->refField) . ') ON DELETE ' . $fk->onDelete;
            }

            return $r . ', ';
        }, '');

        $sql = rtrim($sql, ', ');


        $sql .= ')';
        $sql .= self::endOfCreateTable();
        $sql .= ';' . PHP_EOL . PHP_EOL;


        /* Adding Indexes */
        foreach ($table->indexes as $index) {
            if ($index->type !== Index::PRIMARY_KEY) {
                if ($index->type === Index::UNIQUE) {
                    $sql .= Schema::addUnique($table->name, $index->field)->sql . PHP_EOL;
                } else if ($index->type === Index::INDEX) {
                    $sql .= Schema::addIndex($table->name, $index->field)->sql . PHP_EOL;
                }
            }
        }


        $sql .= PHP_EOL;

        return $sql;
    }


    public static function columnToSql(Column $column)
    {

        //remove column size for sqlite
        if (!is_array($column->size) || $column->type !== 'enum') {
            $column->size = null;
        }


        //remove unsigned too
        $column->unsigned = null;

        $isEnum = false;
        if (strtolower($column->type) === 'enum') {
            $column->type = 'text';
            $isEnum = true;
        }

        $snippet = Helpers::ticks($column->name) . ' ' . strtoupper($column->type);

        //In sqlite make autoincrement come immediately after the type and size
        $snippet .= (($column->auto_increment === true) ? ' AUTOINCREMENT' : '');

        if ($column->null === true) {
            $snippet .= ' NULL';
        } else if ($column->null === false) {
            $snippet .= ' NOT NULL';
        } else {
            $snippet .= ' NULL';
        }

        if (!is_null($column->default)) {
            if (!is_string($column->default)) {
                $snippet .= ' DEFAULT ' . $column->default;
            } else if (is_string($column->default)) {
                $snippet .= " DEFAULT '$column->default'";
            }
        }

        if ($isEnum && is_array($column->size)) {
            $options = array_map(function ($o) {
                return "'$o'";
            }, $column->size);
            $options = join(",", $options);
            $snippet .= ' CHECK (' . $column->name . " IN ($options))";
        }


        return $snippet;
    }


    public static function endOfCreateTable()
    {
        return '';
    }

    public static function findSchemaFks($table)
    {
        $pdo = Connection::getInstance();
        $constraints = [];
        $query = "SELECT m.name, p.* FROM sqlite_master m JOIN pragma_foreign_key_list(m.name)
        p ON m.name != p.\"table\" WHERE m.type = 'table' AND m.name='$table' ORDER BY m.name";
        $cts = $pdo->query($query)->fetchAll(\PDO::FETCH_OBJ);
        foreach ($cts as $ct) {
            //Sqlite also shows on delete and on update
            $constraints[] = new ForeignKey($ct->from, $ct->table, $ct->to, $ct->on_delete);
        }
        return $constraints;
    }

    public static function tableNameToTable(string $tableName, array $fks): Table
    {
        $pdo = Connection::getInstance();
        $columns = $pdo->query("SELECT * FROM pragma_table_info('$tableName')")->fetchAll(\PDO::FETCH_OBJ);

        $fields = [];
        $indexes = [];


        $idx = $pdo->query("SELECT DISTINCT m.name as table_name, ii.name as column_name, il.[unique] as is_unique
        FROM sqlite_master AS m,
             pragma_index_list(m.name) AS il,
             pragma_index_info(il.name) AS ii
       WHERE m.type='table' AND table_name='$tableName'")->fetchAll(\PDO::FETCH_OBJ);

        ($idx == false) && ($idx = []);

        foreach ($columns as $column) {

            $col = new Column($column->name);


            //Determine the type, size, and unsigned            

            $full_regex = "/^(unsigned )?([a-z]+)(\([0-9,]+\))?$/i";
            preg_match($full_regex, $column->type, $type_parts);

            $col->type = strtolower($type_parts[2] ?? '');

            if (strtolower($col->type) === 'enum') {
                $col->type = 'varchar';
            }
            /*
            $size = $type_parts[3] ?? '';
            $size = preg_replace('#[()]#', '', $size);
            $col->size = $size;
            */
            //Ignore the size for sqlite
            //$col->size = null;

            //Ignore Unsigned as well
            //$col->unsigned = null;



            if ($column->notnull) {
                $col->null = false;
            } else {
                $col->null = true;
            }

            if ($column->dflt_value != false) {
                $col->default = trim($column->dflt_value, "'");
            }

            if ($col->type === 'INTEGER' && $col->name === 'id') {
                $col->auto_increment = true;
            }

            $fields[] = $col;

            if ($column->pk) {
                $indexes[] = new Index($column->name, Index::PRIMARY_KEY);
            }


            foreach ($idx as $ind) {
                //Sqlite treats foreign keys as unique indexes
                if ($ind->column_name === $col->name && !$column->pk) {
                    if ((int) $ind->is_unique === 1) {
                        $indexes[] = new Index($ind->column_name, Index::UNIQUE);
                    } else if ((int) $ind->is_unique === 0) {
                        $indexes[] = new Index($ind->column_name, Index::INDEX);
                    }
                }
            }
        }
        return new Table($tableName, $fields, $indexes, $fks);
    }

    public static function schemaToTables($pdo, $dbName)
    {
        $tables = [];
        $db_tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_OBJ);
        foreach ($db_tables as $table) {
            if ($table->name !== 'q_migration') {
                $tables[] = self::tableNameToTable($table->name, self::findSchemaFks($table->name));
            }
        }
        return $tables;
    }

    public static function tableFromModels(string $tableName): Table
    {

        $allModels = Helpers::getDeclaredModels();
        foreach ($allModels as $model) {
            $short = Helpers::modelNameToTableName(Helpers::getShortName($model));
            if ($short === $tableName) {
                break;
            }
        }

        $fields = [];
        $indexes = [];
        $fks = [];

        $schema = $model::schema();

        foreach ($schema as $fieldName => $fieldObject) {

            if ($fieldObject->isFk()) {
                $parentClass = $fieldObject->model;
                $parentPk = TableModelFinder::findModelPk($parentClass);
                $parentClassShortName = Helpers::getShortName($parentClass);
            }


            $fieldObject->column->name = TableModelFinder::findModelColumnName($model, $fieldName);

            /* This is under the assumption that fetching tables from models as an array will be used for comparison */
            if (($fieldObject->column->default instanceof \Closure)) {
                $fieldObject->column->default = null;
            }

            /* Figuring out the column type for foreign key */
            /* Clone and Modify the parent pk column */
            if ($fieldObject->isFk()) {

                if ($parentPk !== 'id') {
                    /* Clone parent PK column and preserve user's null setting */
                    $userNull = $fieldObject->column->null;
                    $newCol = clone TableModelFinder::findModelColumn($parentClass, function ($fieldName, $fieldObject) use ($parentPk) {
                        return ($fieldName === $parentPk);
                    });

                    /* Remove auto_increment, default, and reset name. */
                    $newCol->name = $fieldObject->column->name;
                    $newCol->auto_increment = null;
                    $newCol->default = null;
                    $newCol->null = $userNull;

                    $fieldObject->column = $newCol;
                } else if ($parentPk === 'id') {
                    //The line below caused a subtle bug by not allowing
                    //The consumer to set an fk field to null
                    //$fieldObject->column->null = false;
                    $fieldObject->column->type = 'bigint';
                    $fieldObject->column->size = 20;
                    $fieldObject->column->unsigned = true;
                }
                $fks[] = new ForeignKey($fieldObject->column->name, Helpers::modelNameToTableName($parentClassShortName), $parentPk, $fieldObject->onDelete);
            }


            if ($fieldObject->index != false) {

                //Add index to indexes
                $indexes[] = new Index($fieldObject->column->name, $fieldObject->index);
            }


            //Remove field sizes
            //$fieldObject->column->size = null;

            //Remove unsigned
            //$fieldObject->column->unsigned = null;

            //Set type to lowercase
            $fieldObject->column->type = strtolower($fieldObject->column->type);

            $fields[] = $fieldObject->column;
        }

        return new Table($tableName, $fields, $indexes, $fks);
    }

    public static function modelsToTables(array $models)
    {
        $tables = [];
        foreach ($models as $model) {
            $tableName = Helpers::modelNameToTableName(Helpers::getShortName($model));
            $tables[] = self::tableFromModels($tableName);
        }
        return $tables;
    }


    public static function decideTable(string $tableName): Table
    {
        $fromModels = self::tableFromModels($tableName);
        $fromSchema = self::tableNameToTable($tableName, self::findSchemaFks($tableName));

        // if (CrossEngine::isRollback() == true) {
        //     return $fromModels;
        // } else {
        //     return $fromSchema;
        // }

        // if(self::$altered == true || CrossEngine::isRollback() == true){            
        //     return $fromSchema;
        // }else{            
        //     return $fromModels;
        // }
        if ((self::$alteredTables[$tableName] ?? false)) {
            return self::$alteredTables[$tableName];
        } else {
            if (CrossEngine::isRollback() == true) {
                return $fromSchema;
            } else {
                return $fromModels;
            }
        }
    }

    public static function addUniqueIndexQuery(string $table, string $field, string $indexName)
    {
        //Drop Index First, Then Add
        $table = Helpers::ticks($table);
        $field = Helpers::ticks($field);
        $indexName = Helpers::ticks($indexName);
        $query = "DROP INDEX IF EXISTS $indexName;" . PHP_EOL;
        $query .= "CREATE UNIQUE INDEX IF NOT EXISTS " . $indexName . " ON $table($field);";
        return $query;
    }

    public static function addIndexQuery(string $table, string $field, string $indexName)
    {
        $indexName = Helpers::ticks($indexName);
        $table = Helpers::ticks($table);
        $field = Helpers::ticks($field);

        //Drop Index First, Then Add
        $query = "DROP INDEX IF EXISTS $indexName;" . PHP_EOL;
        $query .= "CREATE INDEX IF NOT EXISTS " . $indexName . " ON $table($field);";
        return $query;
    }

    public static function dropIndexQuery(string $table, string $indexName)
    {
        return 'DROP INDEX IF EXISTS ' . Helpers::ticks($indexName) . ';';
    }


    private static function alterTable(Table $table, string $excludeColumn = null)
    {

        $tempName = Helpers::ticks('q_orm_' . bin2hex(random_bytes(5)));

        $newCols = array_filter($table->fields, function ($col) use ($excludeColumn) {
            if ($col->name == $excludeColumn) {
                return false;
            } else {
                return true;
            }
        });

        $joined = join(
            ',',
            array_map(
                function ($n) {
                    return Helpers::ticks($n);
                },
                array_map(
                    function (Column $col) {
                        return $col->name;
                    },
                    $newCols
                )
            )
        );



        $query = PHP_EOL . PHP_EOL . "PRAGMA foreign_keys=off;" . PHP_EOL;
        $query .= "ALTER TABLE " . Helpers::ticks($table->name) . " RENAME TO " . $tempName . ";" . PHP_EOL;
        $query .= $table->toSql() . PHP_EOL;
        $query .= "INSERT INTO " . Helpers::ticks($table->name) . "($joined) SELECT $joined FROM $tempName;" . PHP_EOL;
        $query .= "DROP TABLE $tempName;" . PHP_EOL;
        $query .= "PRAGMA foreign_keys=on;" . PHP_EOL . PHP_EOL;

        //self::$altered = true;

        self::$alteredTables[$table->name] = $table;

        return $query;
    }


    public static function addForeignKeyQuery(string $table, string $fkName, ForeignKey $foreignKey)
    {

        $tableTable = self::decideTable($table);

        $newFks = [];

        foreach ($tableTable->foreignKeys as $fk) {
            if ($fk->field === $foreignKey->field) {
                continue;
            } else {
                $newFks[] = $fk;
            }
        }

        $newFks[] = $foreignKey;

        $tableTable->foreignKeys = $newFks;

        $query = self::alterTable($tableTable);

        return $query;
    }

    public static function dropForeignKeyQuery(string $table, string $fkName, string $field)
    {

        $tableTable = self::decideTable($table);

        $newFks = [];

        foreach ($tableTable->foreignKeys as $fk) {
            if ($fk->field === $field) {
                continue;
            } else {
                $newFks[] = $fk;
            }
        }

        $tableTable->foreignKeys = $newFks;

        $query = self::alterTable($tableTable);

        return $query;
    }

    /*
    public static function addColumnQuery(string $table, Column $column)
    {
        $decidedTable = self::decideTable($table);
        $col = TableModelFinder::findTableColumn($decidedTable, function (Table $table, Column $c) use ($column) {
            if ($column == $c) {
                return true;
            } else {
                return false;
            }
        });

        //if ($col == null) {
        //Remove the not null, it causes issues on 'add column'
        $column->null = null;
        unset($column->null);
        return 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $column->toSql() . ';';
        // } else {
        //     return '';
        // }
    }
    */
    public static function addColumnQuery(string $table, Column $column)
    {
        $decidedTable = self::decideTable($table);

        $cols = [];
        foreach ($decidedTable->fields as $col) {
            if ($col->name === $column->name) {
                continue;
            } else {
                $cols[] = $col;
            }
        }

        $cols[] = $column;

        $decidedTable->fields = $cols;

        return self::alterTable($decidedTable, $column->name);
    }

    public static function modifyColumnQuery(string $table, Column $column)
    {

        $tableTable = self::decideTable($table);

        $newCols = [];

        foreach ($tableTable->fields as $col) {
            if ($col->name === $column->name) {
                continue;
            } else {
                $newCols[] = $col;
            }
        }

        $newCols[] = $column;

        $tableTable->fields = $newCols;

        $query = self::alterTable($tableTable);

        return $query;
    }

    public static function changeColumnQuery(string $table, string $oldName, Column $column)
    {

        $tableTable = self::decideTable($table);

        $newCols = [];

        foreach ($tableTable->fields as $col) {
            if ($col->name === $oldName) {
                continue;
            } else {
                $newCols[] = $col;
            }
        }

        $newCols[] = $column;

        $tableTable->fields = $newCols;

        $query = self::alterTable($tableTable);

        return $query;
    }

    public static function dropColumnQuery(string $table, string $column)
    {

        $tableTable = self::decideTable($table);

        $newCols = [];

        foreach ($tableTable->fields as $col) {
            if ($col->name === $column) {
                continue;
            } else {
                $newCols[] = $col;
            }
        }

        $tableTable->fields = $newCols;

        $query = self::alterTable($tableTable);

        return $query;
    }


    public static function addPrimaryKeyQuery(string $table, $field)
    {
        $tableTable = self::decideTable($table);

        $newIndexes = [];

        foreach ($tableTable->indexes as $index) {
            if ($index->type === Index::PRIMARY_KEY) {
                continue;
            } else {
                $newIndexes[] = $index;
            }
        }

        $newIndexes[] = new Index($field, Index::PRIMARY_KEY);

        $tableTable->indexes = $newIndexes;


        // If the current pk is 'id', drop the entire column. Else, just drop the pk index
        $currentPkField = TableModelFinder::findTableIndex($tableTable, function ($tableTable, Index $inx) {
            return ($inx->type === Index::PRIMARY_KEY);
        }) ?? new Index('', '');

        if ($currentPkField->field === 'id') {
            $query = self::dropColumnQuery($table, 'id') . PHP_EOL;
        } else {
            $query = self::dropPrimarykeyQuery($table) . PHP_EOL;
        }
        // End of dropping 'id' 


        $query .= self::alterTable($tableTable);

        return $query;
    }

    public static function dropPrimaryKeyQuery(string $table)
    {
        $tableTable = self::decideTable($table);

        $newIndexes = [];

        foreach ($tableTable->indexes as $index) {
            if ($index->type === Index::PRIMARY_KEY) {
                continue;
            } else {
                $newIndexes[] = $index;
            }
        }

        $tableTable->indexes = $newIndexes;

        $query = self::alterTable($tableTable);

        /* 
        $currentPkField  = TableModelFinder::findTableIndex($tableTable, function ($tableTable, Index $inx) {
            return ($inx->type === Index::PRIMARY_KEY);
        }) ?? new Index('', '');

        if ($currentPkField->field === 'id') {
            $query .= self::dropColumnQuery($table, 'id');
        } else {
            $query .= "ALTER TABLE $table ADD COLUMN id INTEGER AUTOINCREMENT PRIMARY KEY FIRST;";
        }
        */

        return $query;
    }
}
