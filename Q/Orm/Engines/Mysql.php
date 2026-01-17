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

class Mysql implements IEngine
{
    public static function createMigrationsTableQuery()
    {
        $table = Helpers::ticks('q_migration');

        $id = Helpers::ticks('id');
        $name = Helpers::ticks('name');
        $applied = Helpers::ticks('applied');

        $query = "CREATE TABLE IF NOT EXISTS $table(";
        $query .= "$id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, $name VARCHAR(255) NOT NULL, $applied DATETIME NULL, UNIQUE($name)";
        $query .= ")";

        return $query;
    }

    public static function tableToSql(Table $table)
    {

        $sql = '';

        /* Disable FK checks to allow table replacement */
        $sql .= 'SET FOREIGN_KEY_CHECKS=0;' . PHP_EOL;

        /*
         * DROP TABLE before CREATE is intentional:
         * The migration detector (makemigrations) never generates CREATE TABLE
         * for existing tables. If CREATE TABLE appears in a migration file,
         * it means the developer explicitly wants a fresh table.
         * This prevents "table already exists" errors during iterative development.
         */
        $sql .= 'DROP TABLE IF EXISTS ' . Helpers::ticks($table->name) . ';' . PHP_EOL;

        /* check indexes if primary key exists, if not create one */
        $primary_key_set = null;
        foreach ($table->indexes as $index) {
            if ($index->type === Index::PRIMARY_KEY) {
                $primary_key_set = true;
            }
        }

        if (!$primary_key_set) {
            array_unshift($table->fields, new Column('id', 'BIGINT', ['size' => 20, 'null' => false, 'unsigned' => true, 'auto_increment' => true]));
            array_unshift($table->indexes, new Index('id', Index::PRIMARY_KEY));
        } else {
            //array_unshift($table->fields, new Column('id', 'BIGINT', ['size' => 20, 'null' => false, 'unsigned' => true, 'auto_increment' => true]));
            //Index will be added in the column                
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

        $sql .= 'CREATE TABLE ' . Helpers::ticks($table->name) . '(';

        $sql .= array_reduce($table->fields, function ($c, $i) use ($isPrimaryKey) {
            $r = '';
            if ($i->auto_increment === true && !$isPrimaryKey($i->name)) {
                //This was done for 'id' as in above
                //$r .= $c . $i->toSql() . ' UNIQUE';
            } else if ($isPrimaryKey($i->name)) {
                $r .= $c . $i->toSql() . ' PRIMARY KEY';
            } else {
                $r .= $c . $i->toSql();
            }

            return $r . ', ';
        }, '');

        $sql = rtrim($sql, ', ');


        $sql .= ')';
        $sql .= self::endOfCreateTable();
        $sql .= ';' . PHP_EOL . PHP_EOL;


        /* Adding Indexes */
        foreach ($table->indexes as $index) {
            if ($index->type != Index::PRIMARY_KEY) {
                if ($index->type === Index::UNIQUE) {
                    $sql .= Schema::addUnique($table->name, $index->field)->sql . PHP_EOL;
                } else if ($index->type === Index::INDEX) {
                    $sql .= Schema::addIndex($table->name, $index->field)->sql . PHP_EOL;
                }
            }
        }

        foreach ($table->foreignKeys as $fk) {
            $sql .= Schema::addForeignKey($table->name, $fk->field, $fk->refTable, $fk->refField, $fk->onDelete)->sql . PHP_EOL;
        }

        /* Re-enable FK checks */
        $sql .= 'SET FOREIGN_KEY_CHECKS=1;' . PHP_EOL;

        $sql .= PHP_EOL;

        return $sql;
    }

    public static function columnToSql(Column $column)
    {

        /* Begining snippet */

        $snippet = Helpers::ticks($column->name) . ' ' . strtoupper($column->type);

        if ($column->size) {
            if (is_array($column->size)) {

                $snippet .= '(' . ltrim(array_reduce($column->size, function ($c, $i) {
                    $s = '';
                    if (gettype($i) === 'string') {
                        return $c . ", '" . $i . "'";
                    } else {
                        return $c . ', ' . $i;
                    }
                }, ''), ', ') . ')';
            } else {
                $snippet .= '(' . $column->size . ')';
            }
        }

        $snippet .= (($column->unsigned === true) ? ' UNSIGNED' : (($column->unsigned === false) ? ' SIGNED' : ''));

        if ($column->null === true) {
            $snippet .= ' NULL';
        } else if ($column->null === false) {
            $snippet .= ' NOT NULL';
        } else {
            $snippet .= ' NULL';
        }

        $snippet .= (($column->auto_increment === true) ? ' AUTO_INCREMENT' : '');

        if (!is_null($column->default)) {
            if (!is_string($column->default)) {
                $snippet .= ' DEFAULT ' . $column->default;
            } else if (is_string($column->default)) {
                $snippet .= " DEFAULT '$column->default'";
            }
        }

        return $snippet;
    }

    public static function endOfCreateTable()
    {
        return 'engine=InnoDB,charset=utf8mb4,collate=utf8mb4_unicode_ci';
    }


    public static function findSchemaFks($pdo, $table)
    {
        $constraints = [];
        $query = "SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME =  '$table'";
        $cts = $pdo->query($query)->fetchAll(\PDO::FETCH_OBJ);
        foreach ($cts as $ct) {
            if ($ct->REFERENCED_TABLE_NAME && $ct->REFERENCED_COLUMN_NAME) {
                $fkName = Schema::fkName($table, $ct->COLUMN_NAME);
                $on_del = $pdo->query("SELECT * FROM INFORMATION_SCHEMA.referential_constraints WHERE CONSTRAINT_NAME='$fkName'")->fetch(\PDO::FETCH_OBJ);
                if ($on_del) {
                    $deleteRule = $on_del->DELETE_RULE;
                    $constraints[] = new ForeignKey($ct->COLUMN_NAME, $ct->REFERENCED_TABLE_NAME, $ct->REFERENCED_COLUMN_NAME, $deleteRule);
                } else {
                    $constraints[] = new ForeignKey($ct->COLUMN_NAME, $ct->REFERENCED_TABLE_NAME, $ct->REFERENCED_COLUMN_NAME, ForeignKey::RESTRICT);
                }
            }
        }
        return $constraints;
    }

    public static function tableNameToTable(string $tableName, array $fks)
    {
        $pdo = Connection::getInstance();
        $columns = $pdo->query("DESCRIBE " . Helpers::ticks($tableName) . ";")->fetchAll(\PDO::FETCH_OBJ);

        $fields = [];
        $indexes = [];

        foreach ($columns as $column) {
            $col = new Column($column->Field);

            //Determine the type, size, and unsigned            

            $full_regex = "/^([a-z]+)(\([0-9,]+\))?( unsigned)?$/i";
            preg_match($full_regex, $column->Type, $type_parts);

            $col->type = strtolower($type_parts[1] ?? '');
            $size = $type_parts[2] ?? '';
            $size = preg_replace('#[()]#', '', $size);
            $col->size = $size;

            $unsigned = $type_parts[3] ?? null;
            if ($unsigned) {
                if ($unsigned == true) {
                    $col->unsigned = true;
                } else {
                    $col->unsigned = false;
                }
            } else {
                $col->unsigned = null;
            }

            if ($column->Null === 'NO') {
                $col->null = false;
            } else {
                $col->null = true;
            }

            if ($column->Default != false) {
                $col->default = trim($column->Default, "'");
            }

            if ($column->Extra === 'auto_increment') {
                $col->auto_increment = true;
            }

            $fields[] = $col;

            if ($column->Key != false) {
                $key = $column->Key;
                if ($key === 'PRI') {
                    $indexes[] = new Index($column->Field, Index::PRIMARY_KEY);
                } else if ($key === 'UNI') {
                    $indexes[] = new Index($column->Field, Index::UNIQUE);
                } else if ($key === 'MUL') {
                    $indexes[] = new Index($column->Field, Index::INDEX);
                }
            }
        }
        return new Table($tableName, $fields, $indexes, $fks);
    }

    public static function schemaToTables($pdo, $dbName)
    {
        $db_tables = $pdo->query("SHOW FULL TABLES;")->fetchAll(\PDO::FETCH_OBJ);
        $tables = [];
        foreach ($db_tables as $t) {
            $table_name = 'Tables_in_' . $dbName;
            if ($t->$table_name !== 'q_migration' && $t->Table_type === 'BASE TABLE') {
                $tables[] = self::tableNameToTable($t->$table_name, self::findSchemaFks($pdo, $t->$table_name));
            }
        }
        return $tables;
    }


    public static function tableFromModels(string $tableName)
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

                    /* Remove auto_increment, default, and reset name */
                    $newCol->name = $fieldObject->column->name;
                    $newCol->auto_increment = false;
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



    public static function addUniqueIndexQuery($table, $field, $indexName)
    {
        $tickedTable = Helpers::ticks($table);
        $tickedField = Helpers::ticks($field);
        $tickedIndex = Helpers::ticks($indexName);
        // Drop index first - error 1091 (index doesn't exist) is suppressed by the migration runner
        $drop = "DROP INDEX $tickedIndex ON $tickedTable;";
        $create = "CREATE UNIQUE INDEX $tickedIndex ON $tickedTable($tickedField);";
        return $drop . PHP_EOL . $create;
    }

    public static function addIndexQuery(string $table, string $field, string $indexName)
    {
        $tickedTable = Helpers::ticks($table);
        $tickedField = Helpers::ticks($field);
        $tickedIndex = Helpers::ticks($indexName);
        // Drop index first - error 1091 (index doesn't exist) is suppressed by the migration runner
        $drop = "DROP INDEX $tickedIndex ON $tickedTable;";
        $create = "CREATE INDEX $tickedIndex ON $tickedTable($tickedField);";
        return $drop . PHP_EOL . $create;
    }

    public static function dropIndexQuery($table, $indexName)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' DROP INDEX ' . Helpers::ticks($indexName) . ';';
    }

    public static function addForeignKeyQuery(string $table, string $fkName, ForeignKey $fk)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' ADD CONSTRAINT ' . Helpers::ticks($fkName) . ' FOREIGN KEY(' . Helpers::ticks($fk->field) . ') REFERENCES ' . Helpers::ticks($fk->refTable) . '(' . Helpers::ticks($fk->refField) . ') ON DELETE ' . $fk->onDelete . ';';
    }

    public static function dropForeignKeyQuery(string $table, string $fkName, string $field)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' DROP FOREIGN KEY ' . Helpers::ticks($fkName) . ';';
    }

    public static function addColumnQuery(string $table, Column $column)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' ADD COLUMN ' . $column->toSql() . ';';
    }

    public static function modifyColumnQuery($table, Column $column)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' MODIFY ' . $column->toSql() . ';';
    }

    public static function changeColumnQuery(string $table, string $oldName, Column $column)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' CHANGE COLUMN ' . Helpers::ticks($oldName) . ' ' . $column->toSql() . ';';
    }

    public static function dropColumnQuery(string $table, string $column)
    {
        return 'ALTER TABLE ' . Helpers::ticks($table) . ' DROP COLUMN ' . Helpers::ticks($column) . ";";
    }

    public static function addPrimaryKeyQuery(string $table, string $field)
    {
        if (CrossEngine::isRollback()) {
            $tableTable = self::tableNameToTable($table, self::findSchemaFks(Connection::getInstance(), $table));
        } else {
            $tableTable = self::tableFromModels($table);
        }

        $currentPkField = TableModelFinder::findTableIndex($tableTable, function ($tableTable, Index $inx) {
            return ($inx->type === Index::PRIMARY_KEY);
        }) ?? new Index('', '');

        if ($currentPkField->field === 'id') {
            $query = self::dropColumnQuery($table, 'id') . PHP_EOL;
        } else {
            $query = 'ALTER TABLE ' . Helpers::ticks($table) . ' DROP PRIMARY KEY;' . PHP_EOL;
        }

        $query .= 'ALTER TABLE ' . Helpers::ticks($table) . ' ADD PRIMARY KEY(' . Helpers::ticks($field) . ');';
        return $query;
    }

    public static function dropPrimaryKeyQuery(string $table)
    {
        if (CrossEngine::isRollback()) {
            $tableTable = self::tableNameToTable($table, self::findSchemaFks(Connection::getInstance(), $table));
        } else {
            $tableTable = self::tableFromModels($table);
        }

        $currentPkField = TableModelFinder::findTableIndex($tableTable, function ($tableTable, Index $inx) {
            return ($inx->type === Index::PRIMARY_KEY);
        }) ?? new Index('', '');

        $query = 'ALTER TABLE ' . Helpers::ticks($table) . ' DROP PRIMARY KEY;' . PHP_EOL;
        if ($currentPkField->field === 'id') {
            $query .= self::dropColumnQuery($table, 'id');
        } else {
            $query .= "ALTER TABLE " . Helpers::ticks($table) . " ADD COLUMN " . Helpers::ticks('id') . " BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;";
        }

        return $query;
    }
}
