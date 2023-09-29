<?php

namespace Q\Orm\Migration;

use Q\Orm\Field;
use Q\Orm\SetUp;

class TableComparer
{

    private static function tableToOperationText(Table $table)
    {
        $text = "Schema::create('$table->name', function (SchemaBuilder \$tb) {" . PHP_EOL;
        foreach ($table->fields as $field) {

            $definition = var_export(array_filter(get_object_vars($field), function ($a) {
                return ($a !== null);
            }), true);

            $definition = preg_replace("#(?m)^#", "\t\t\t\t\t\t", $definition);
            $definition = ltrim($definition);

            if ($field->type === 'bigint') {
                $definition = str_replace("'type' => 'text'", "'type' => Field::INTEGER", $definition);
                $text .= "\t\t\t\t\t\$tb->integer('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'varchar') {
                $definition = str_replace("'type' => 'varchar'", "'type' => Field::CHAR", $definition);
                $text .= "\t\t\t\t\t\$tb->string('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'text') {
                $definition = str_replace("'type' => 'text'", "'type' => Field::TEXT", $definition);
                $text .= "\t\t\t\t\t\$tb->text('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'date') {
                $definition = str_replace("'type' => 'date'", "'type' => Field::DATE", $definition);
                $text .= "\t\t\t\t\t\$tb->date('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'datetime') {
                $definition = str_replace("'type' => 'datetime'", "'type' => Field::DATETIME", $definition);
                $text .= "\t\t\t\t\t\$tb->datetime('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'decimal') {
                $definition = str_replace("'type' => 'decimal'", "'type' => Field::DECIMAL", $definition);
                $text .= "\t\t\t\t\t\$tb->decimal('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'float') {
                $definition = str_replace("'type' => 'text'", "'float' => Field::FLOAT", $definition);
                $text .= "\t\t\t\t\t\$tb->float('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'double') {
                $definition = str_replace("'type' => 'double'", "'type' => Field::DOUBLE", $definition);
                $text .= "\t\t\t\t\t\$tb->double('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'numeric') {
                $definition = str_replace("'type' => 'numeric'", "'type' => Field::NUMERIC", $definition);
                $text .= "\t\t\t\t\t\$tb->numeric('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === 'enum') {
                $definition = str_replace("'type' => 'enum'", "'type' => Field::ENUM", $definition);
                $text .= "\t\t\t\t\t\$tb->enum('$field->name', $definition);" . PHP_EOL;
            } else if ($field->type === Field::BOOL) {
                $definition = str_replace("'type' => 'boolean'", "'type' => Field::BOOL", $definition);
                $text .= "\t\t\t\t\t\$tb->boolean('$field->name', $definition);" . PHP_EOL;
            }
        }
        foreach ($table->indexes as $index) {
            if ($index->type === Index::INDEX) {
                $text .= "\t\t\t\t\t\$tb->index('$index->field');" . PHP_EOL;
            } else if ($index->type === Index::PRIMARY_KEY) {
                $text .= "\t\t\t\t\t\$tb->primary('$index->field');" . PHP_EOL;
            } else if ($index->type === Index::UNIQUE) {
                $text .= "\t\t\t\t\t\$tb->unique('$index->field');" . PHP_EOL;
            }
        }
        foreach ($table->foreignKeys as $fk) {
            $text .= "\t\t\t\t\t\$tb->foreignKey('$fk->field', '$fk->refTable', '$fk->refField', '$fk->onDelete');" . PHP_EOL;
        }
        $text .= PHP_EOL . "\t\t\t\t})";
        return $text;
    }

    private static function columnToOperationText($table, Column $column)
    {
        $text = "Schema::addColumn('$table', function (Column \$column) {" . PHP_EOL;
        $fields = get_object_vars($column);
        foreach ($fields as $key => $value) {
            if (is_null($value)) continue;
            $k = "\$column->$key";
            if ($key == 'type') {
                $code = Field::textToCode($value);
                if ($code) {
                    $text .= "\t\t\t\t\t{$k} = Field::" . $code . ';' . PHP_EOL;
                }
            } else {
                $text .= "\t\t\t\t\t{$k} = " . $value = var_export($value, true) . ';' . PHP_EOL;
            }
        }
        $text .= "\t\t\t\t})";
        return $text;
    }

    private static function modifyColumnToOperationText($table, Column $column)
    {
        $text = "Schema::modifyColumn('$table', function (Column \$column) {" . PHP_EOL;
        $fields = get_object_vars($column);
        foreach ($fields as $key => $value) {
            if (is_null($value)) continue;
            $k = "\$column->$key";
            if ($key == 'type') {
                $code = Field::textToCode($value);
                if ($code) {
                    $text .= "\t\t\t\t\t{$k} = Field::" . $code . ';' . PHP_EOL;
                }
            } else {
                $text .= "\t\t\t\t\t{$k} = " . $value = var_export($value, true) . ';' . PHP_EOL;
            }
        }
        $text .= "\t\t\t\t})";
        return $text;
    }

    private static function wrapInClosure(string $code)
    {
        $r = PHP_EOL . "\t\t\tfunction(){" . PHP_EOL;
        $r .= "\t\t\t\treturn $code;";
        $r .= PHP_EOL . "\t\t\t}";
        return $r;
    }

    private static function findTableByName(array $state, string $tableName)
    {
        foreach ($state as $table) {
            if (($table->name === $tableName) && !is_null($table->oldName)) {
                return $table;
            }
        }
        return null;
    }

    public static function compare()
    {

        $fromModels = Introspector::modelsToArrayOfTables();
        $fromSchema = StateBuilder::build();
        if(empty($fromSchema)){
            //Incase there are no migrations at all
            $fromSchema = Introspector::schemaToArrayOfTables();
        }
        $originalState = $fromSchema;

        $operationsText = [];

        /* Don't forget to add reverse */
        $reverseText = [];

        $tablesToCreate = self::tablesToCreate($fromSchema, $fromModels);        

        $state = StateBuilder::safeMerge($fromSchema, $tablesToCreate);

        //Topologically sort tables to create
        $state = Topology::sortTablesToCreate($state);

        $tablesToRename = self::tablesToRename($state, $fromModels);
        $state = StateBuilder::renameTables($state, $tablesToRename);


        $tablesToDrop = self::tablesToDrop($state, $fromModels);
        $state = StateBuilder::dropTables($state, $tablesToDrop);


        /* Table Level */
        $reverseRename = [];
        foreach ($tablesToRename as $pair) {
            $tableFrom = $pair['from'];
            $tableTo = $pair['to'];
            $operationsText[] = self::wrapInClosure("Schema::rename('$tableFrom', '$tableTo')");
            //We will add the reverse just before the indexes
            //$reverseText[] = self::wrapInClosure("Schema::rename('$tableTo', '$tableFrom')");
            $reverseRename[] = self::wrapInClosure("Schema::rename('$tableTo', '$tableFrom')");
        }

        foreach ($tablesToDrop as $table) {
            //Don't drop a table that was scheduled for renaming
            //It's a waste of query
            $wasRenamed = false;
            foreach ($tablesToRename as $pair) {
                if ($pair['from'] === $table->name) {
                    $wasRenamed = true;
                    break;
                }
            }
            if ($wasRenamed === false) {
                $operationsText[] = self::wrapInClosure("Schema::dropIfExists('$table->name')");
                $reverseText[] = self::wrapInClosure(self::tableToOperationText($table));
            }
        }

        /* $tablesToCreate is now $state*/
        foreach ($state as $table) {
            //Don't attempt to create a table that was scheduled to be renamed
            $wasRenamed = false;
            $existingTables = array_map(function ($t) {
                return $t->name;
            }, $originalState);
            foreach ($tablesToRename as $pair) {
                if ($pair['to'] === $table->name) {
                    $wasRenamed = true;
                }
            }
            if ($wasRenamed === false && !in_array($table->name, $existingTables)) {
                $operationsText[] = self::wrapInClosure(self::tableToOperationText($table));
                $reverseText[] = self::wrapInClosure("Schema::dropIfExists('$table->name')");
            }
        }


        /* Drop fks */
        $fksToDrop = self::fksToDrop($state, $fromModels);
        $state = StateBuilder::dropForeignKeys($state, $fksToDrop);
        foreach ($fksToDrop as $pair) {
            $table = $pair['table'];
            $fk = $pair['foreignKey'];

            $oldTable = self::findTableByName($state, $table);
            $oldTable = var_export($oldTable->oldName ?? $table, true);

            $operationsText[] = self::wrapInClosure("Schema::dropForeignKey('$table', '$fk->field', $oldTable)");
            $reverseText[] = self::wrapInClosure("Schema::addForeignKey($oldTable, '$fk->field', '$fk->refTable', '$fk->refField', '$fk->onDelete')");
        }


        /* To drop indexes */
        $indexesToDrop = self::indexesToDrop($state, $fromModels);
        $state = StateBuilder::dropIndexes($state, $indexesToDrop);

        foreach ($indexesToDrop as $pair) {

            $table = $pair['table'];
            $index = $pair['index'];

            $oldTable = self::findTableByName($state, $table);
            $newTable = var_export($oldTable->oldName ?? $table, true);

            if ($index->type === Index::INDEX) {
                $operationsText[] = self::wrapInClosure("Schema::dropIndex('$table', '$index->field', $newTable)");
                $reverseText[] = self::wrapInClosure("Schema::addIndex($newTable, '$index->field')");
            } else if ($index->type === Index::UNIQUE) {
                $operationsText[] = self::wrapInClosure("Schema::dropUnique('$table', '$index->field', $newTable)");
                $reverseText[] = self::wrapInClosure("Schema::addUnique($newTable, '$index->field')");
            } else if ($index->type === Index::PRIMARY_KEY) {
                //$newTable does not apply to primary keys
                $operationsText[] = self::wrapInClosure("Schema::dropPrimarykey('$table', '$index->field', $newTable)");
                $reverseText[] = self::wrapInClosure("Schema::addPrimarykey($newTable, '$index->field')");
            }
        }


        /* Column Level */

        $columnsToAdd = self::columnsToAdd($state, $fromModels);
        $state = StateBuilder::addColumns($state, $columnsToAdd);
        foreach ($columnsToAdd as $pair) {
            $column = $pair['column'];
            $table = $pair['table'];

            $oldTable = self::findTableByName($state, $table);
            $oldTable = var_export($oldTable->oldName ?? $table, true);

            $operationsText[] = self::wrapInClosure(self::columnToOperationText($table, $column));
            $reverseText[] = self::wrapInClosure("Schema::dropColumn($oldTable, '$column->name')");
        }



        $indexesToAdd = self::indexesToAdd($state, $fromModels);
        $state = StateBuilder::addIndexes($state, $indexesToAdd);
        foreach ($indexesToAdd as $pair) {
            $table = $pair['table'];
            $index = $pair['index'];

            $oldTable = self::findTableByName($state, $table);
            $oldTable = var_export($oldTable->oldName ?? $table, true);

            if ($index->type === Index::INDEX) {
                $operationsText[] = self::wrapInClosure("Schema::addIndex('$table', '$index->field')");
                $reverseText[] = self::wrapInClosure("Schema::dropIndex($oldTable, '$index->field', '$table')");
            } else if ($index->type === Index::UNIQUE) {
                $operationsText[] = self::wrapInClosure("Schema::addUnique('$table', '$index->field')");
                $reverseText[] = self::wrapInClosure("Schema::dropUnique($oldTable, '$index->field', '$table')");
            } else if ($index->type === Index::PRIMARY_KEY) {
                $operationsText[] = self::wrapInClosure("Schema::addPrimarykey('$table', '$index->field')");
                $reverseText[] = self::wrapInClosure("Schema::dropPrimarykey($oldTable, '$index->field', '$table')");
            }
        }



        $fksToAdd = self::fksToAdd($state, $fromModels);
        $state = StateBuilder::addForeignKeys($state, $fksToAdd);
        foreach ($fksToAdd as $pair) {
            $table = $pair['table'];
            $fk = $pair['foreignKey'];

            $oldTable = self::findTableByName($state, $table);
            $oldTable = var_export($oldTable->oldName ?? $table, true);

            $operationsText[] = self::wrapInClosure("Schema::addForeignKey('$table', '$fk->field', '$fk->refTable', '$fk->refField', '$fk->onDelete')");
            $reverseText[] = self::wrapInClosure("Schema::dropForeignKey($oldTable, '$fk->field', '$table')");
        }


        $columnsToModify = self::columnsToModify($state, $fromModels);
        $state = StateBuilder::modifyColumns($state, $columnsToModify);
        foreach ($columnsToModify as $triple) {
            $table = $triple['table'];
            $column = $triple['column'];
            $prev_col = $triple['previouscolumn'];
            /* Don't modify column that was scheduled to be added */
            $wasAdded = false;
            foreach ($columnsToAdd as $c) {
                if ($c['column']->name === $column->name) {
                    $wasAdded = true;
                }
            }
            if ($wasAdded === false) {
                $oldTable = self::findTableByName($state, $table);
                $oldTable = $oldTable->oldName ?? $table;
                $operationsText[] = self::wrapInClosure(self::modifyColumnToOperationText($table, $column));
                $reverseText[] = self::wrapInClosure(self::modifyColumnToOperationText($oldTable, $prev_col));
            }
        }

        $columnsToDrop = self::columnsToDrop($state, $fromModels);
        $state = StateBuilder::dropColumns($state, $columnsToDrop);
        foreach ($columnsToDrop as $pair) {
            $table = $pair['table'];
            $column = $pair['column'];

            $oldTable = self::findTableByName($state, $table);
            $oldTable = $oldTable->oldName ?? $table;

            $operationsText[] = self::wrapInClosure("Schema::dropColumn('$table', '$column->name')");
            $reverseText[] = self::wrapInClosure(self::columnToOperationText($oldTable, $column));
        }


        /* Reverse the reverseText array */
        $reverseText = array_reverse($reverseText, false);
        $reverseText = array_merge($reverseRename, $reverseText);
        return [$operationsText, $reverseText];
    }

    private static function tablesToRename(array $fromSchema, array $fromModels)
    {
        $tables = [];
        foreach ($fromSchema as $schemaTable) {
            $schema_table_fields = array_map(function ($column) {
                return $column->name;
            }, $schemaTable->fields);

            //Remove 'id' from the fields
            foreach ($schema_table_fields as $k => $v) {
                if ($v === 'id') {
                    unset($schema_table_fields[$k]);
                }
            }

            sort($schema_table_fields);
            $rename = false;
            foreach ($fromModels as $modelTable) {
                $model_table_fields = array_map(function ($column) {
                    return $column->name;
                }, $modelTable->fields);

                sort($model_table_fields);
                if ($schemaTable->name != $modelTable->name && count($model_table_fields) === count($schema_table_fields) && $model_table_fields === $schema_table_fields) {
                    $rename = true;
                    break;
                }
            }
            if ($rename === true) {
                $tables[] = ['from' => $schemaTable->name, 'to' => $modelTable->name];
            }
        }
        return $tables;
    }

    private static function tablesToDrop(array $fromSchema, array $fromModels)
    {
        $tables = [];
        foreach ($fromSchema as $schemaTable) {
            $drop = true;
            foreach ($fromModels as $modelTable) {
                if ($schemaTable->name === $modelTable->name) {
                    $drop = false;
                    break;
                }
            }
            if ($drop === true) {
                $tables[] = $schemaTable;
            }
        }
        return $tables;
    }

    private static function tablesToCreate(array $fromSchema, array $fromModels)
    {
        $tables = [];
        foreach ($fromModels as $modelTable) {
            $found = false;
            foreach ($fromSchema as $schemaTable) {
                if ($modelTable->name === $schemaTable->name) {
                    $found = true;
                    break;
                }
            }
            if ($found === false) {
                $tables[] = $modelTable;
            }
        }
        return $tables;
    }

    private static function tableCartesian(array $table1, array $table2, callable $function)
    {
        foreach ($table1 as $t1) {
            foreach ($table2 as $t2) {
                $t1Name = $t1->oldName ?? $t1->name;
                $t2Name = $t2->oldName ?? $t2->name;
                if (($t1Name === $t2->name) || ($t1->name === $t2Name) || ($t1->name === $t2->name)) {
                    $function($t1, $t2);
                }
            }
        }
    }

    private static function columnsToAdd(array $fromSchema, array $fromModels)
    {
        $columnsToAdd = [];
        self::tableCartesian($fromModels, $fromSchema, function (Table $modelTable, Table $schemaTable) use (&$columnsToAdd) {

            foreach ($modelTable->fields as $mf) {
                $found = false;
                foreach ($schemaTable->fields as $sf) {

                    if ($mf->name === $sf->name) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false && $mf->name !== 'id') {
                    $columnsToAdd[] = ['table' => $modelTable->name, 'column' => $mf];
                }
            }
        });
        return $columnsToAdd;
    }

    private static function columnsToDrop(array $fromSchema, array $fromModels)
    {
        $columnsToDrop = [];
        self::tableCartesian($fromSchema, $fromModels, function (Table $schemaTable, Table $modelTable) use (&$columnsToDrop) {

            foreach ($schemaTable->fields as $sf) {
                $found = false;
                foreach ($modelTable->fields as $mf) {

                    if ($sf->name === $mf->name) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false && $sf->name !== 'id') {
                    $columnsToDrop[] = ['table' => $schemaTable->name, 'column' => $sf];
                }
            }
        });
        return $columnsToDrop;
    }

    private static function columnsToModify(array $fromSchema, array $fromModels)
    {
        $columnsToModify = [];
        self::tableCartesian($fromModels, $fromSchema, function (Table $modelTable, Table $schemaTable) use (&$columnsToModify) {

            foreach ($modelTable->fields as $mf) {

                $found = false;

                foreach ($schemaTable->fields as $sf) {

                    /* This is where we remove unsigned and null for sqlite */
                    $mf_size = $mf->size;
                    $mf_unsigned = $mf->unsigned;
                    $sf_size = $sf->size;
                    $sf_unsigned = $sf->unsigned;

                    $mf_type = $mf->type;
                    $sf_type = $sf->type;

                    /* Trying to normalize enum fields */
                    if (SetUp::$engine === SetUp::SQLITE) {
                        $mf->size = null;
                        $mf->unsigned = null;
                        $sf->size = null;
                        $sf->unsigned = null;
                        if (
                            (strtolower($mf_type) === 'enum' && strtolower($sf_type) === 'text')
                            || (strtolower($mf_type) === 'text' && strtolower($sf_type) === 'enum')
                        ) {
                            $mf->type = null;
                            $sf->type = null;
                        }
                    }

                    if ($mf->name === $sf->name && $mf != $sf) {
                        $found = true;
                        break;
                    }
                }
                /* Ignore fields called 'id' */
                if ($found === true && $mf->name !== 'id') {
                    $mf->size = $mf_size;
                    $mf->unsigned = $mf_unsigned;
                    $mf->type = $mf_type;
                    $sf->size = $sf_size;
                    $sf->unsigned = $sf_unsigned;
                    $sf->type = $sf_type;
                    $columnsToModify[] = ['table' => $modelTable->name, 'column' => $mf, 'previouscolumn' => $sf];
                }
            }
        });
        return $columnsToModify;
    }


    private static function indexesToAdd(array $fromSchema, array $fromModels)
    {
        $indexesToAdd = [];
        $tablesToRename = self::tablesToRename($fromSchema, $fromModels);
        self::tableCartesian($fromModels, $fromSchema, function (Table $modelTable, Table $schemaTable) use (&$indexesToAdd, $tablesToRename, $fromSchema) {

            foreach ($modelTable->indexes as $mi) {
                $found = false;
                foreach ($schemaTable->indexes as $si) {
                    /* If the names are the same and the types match, then No need to add */
                    if ($mi->field === $si->field && $mi == $si) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false && $mi->field !== 'id') {
                    $indexesToAdd[] = ['table' => $modelTable->name, 'index' => $mi];
                }
            }


            //If we will rename a table, then we re-add all it's indexes because the remover would have removed them.
            foreach ($tablesToRename as $tbl) {
                foreach ($fromSchema as $fs) {
                    if ($fs->name === $tbl['from']) {

                        foreach ($fs->indexes as $idx) {

                            $newIdx = ['table' => $tbl['to'], 'index' => $idx, 'oldTable' => $tbl['from']];
                            $added = in_array($newIdx, $indexesToAdd);
                            $isPrimaryKey = $idx->type === Index::PRIMARY_KEY;


                            if (!$added && !$isPrimaryKey) {

                                $indexesToAdd[] = $newIdx;
                            }
                        }
                    }
                }
            }
        });

        return $indexesToAdd;
    }

    private static function indexesToDrop(array $fromSchema, array $fromModels)
    {

        $indexesToDrop = [];
        $tablesToRename = self::tablesToRename($fromSchema, $fromModels);
        self::tableCartesian($fromSchema, $fromModels, function (Table $schemaTable, Table $modelTable) use (&$indexesToDrop, $tablesToRename, $fromSchema) {

            foreach ($schemaTable->indexes as $si) {

                $found = false;

                foreach ($modelTable->indexes as $mi) {
                    /* Drop only if the fields don't match in every sense */
                    if ($si->field === $mi->field && $si == $mi) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false) {
                    if ($si->field !== 'id') {
                        $indexesToDrop[] = ['table' => $schemaTable->name, 'index' => $si];
                    }
                }
            }


            //If we will rename a table, then remove all it's indexes. The adder will re-add them with the new proper names.
            foreach ($tablesToRename as $tbl) {
                foreach ($fromSchema as $fs) {
                    if ($fs->name === $tbl['from']) {

                        foreach ($fs->indexes as $idx) {
                            $newIdx = ['table' => $fs->name, 'index' => $idx, 'newTable' => $tbl['to']];
                            $added = in_array($newIdx, $indexesToDrop);
                            $isPrimaryKey = $idx->type === Index::PRIMARY_KEY;


                            if (!$added && !$isPrimaryKey) {

                                $indexesToDrop[] = $newIdx;
                            }
                        }
                    }
                }
            }
        });

        return $indexesToDrop;
    }

    private static function fksToAdd(array $fromSchema, array $fromModels)
    {
        $fksToAdd = [];
        $indexesToAdd = self::indexesToAdd($fromSchema, $fromModels);
        self::tableCartesian($fromModels, $fromSchema, function (Table $modelTable, Table $schemaTable) use (&$fksToAdd, $indexesToAdd, $fromModels) {

            foreach ($modelTable->foreignKeys as $mfk) {
                $found = false;
                foreach ($schemaTable->foreignKeys as $sfk) {

                    if ($mfk->field === $sfk->field && $mfk == $sfk) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false && $mfk->field !== 'id') {
                    $fksToAdd[] = ['table' => $modelTable->name, 'foreignKey' => $mfk];
                }
            }

            /* Now let's look at which indexes will be added, 
            and add the fk as well, this is done to restore the dropped fks 
            */

            $fkFieldNames = array_map(function ($pair) {
                return $pair['foreignKey']->field;
            }, $fksToAdd);


            foreach ($indexesToAdd as $indexPair) {

                foreach ($fromModels as $schemaT) {
                    $tablesMatch = ($indexPair['table'] === $schemaT->name);

                    if ($tablesMatch) {

                        foreach ($schemaT->foreignKeys as $fk) {
                            $fieldsMatch = ($indexPair['index']->field === $fk->field);
                            if ($fieldsMatch) {
                                if (!in_array($fk->field, $fkFieldNames)) {

                                    if ($indexPair['oldTable'] ?? false) {
                                        $fksToAdd[] = ['table' => $schemaT->name, 'foreignKey' => $fk, 'formerTable' => $indexPair['oldTable']];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });
        return $fksToAdd;
    }

    private static function fksToDrop(array $fromSchema, array $fromModels)
    {
        $fksToDrop = [];
        $indexesToDrop = self::indexesToDrop($fromSchema, $fromModels);
        self::tableCartesian($fromSchema, $fromModels, function (Table $schemaTable, Table $modelTable) use (&$fksToDrop, $indexesToDrop, $fromSchema) {

            foreach ($schemaTable->foreignKeys as $sfk) {
                $found = false;
                foreach ($modelTable->foreignKeys as $mfk) {
                    if ($sfk->field === $mfk->field && $sfk == $mfk) {
                        $found = true;
                        break;
                    }
                }

                if ($found === false && $sfk->field !== 'id') {
                    $fksToDrop[] = ['table' => $schemaTable->name, 'foreignKey' => $sfk];
                }
            }

            /* Now let's look at which indexes will be dropped, and drop the fk as well, because indexes cannot be dropped which fks are on them, at least in mysql */
            $fkFieldNames = array_map(function ($pair) {
                return $pair['foreignKey']->field;
            }, $fksToDrop);


            foreach ($indexesToDrop as $indexPair) {

                foreach ($fromSchema as $schemaT) {
                    $tablesMatch = ($indexPair['table'] === $schemaT->name);


                    if ($tablesMatch) {

                        foreach ($schemaT->foreignKeys as $fk) {
                            $fieldsMatch = ($indexPair['index']->field === $fk->field);
                            if ($fieldsMatch) {
                                if (!in_array($fk->field, $fkFieldNames)) {

                                    if ($indexPair['newTable'] ?? false) {
                                        $fksToDrop[] = ['table' => $schemaT->name, 'foreignKey' => $fk, 'currentTable' => $indexPair['newTable']];
                                    } else {
                                        $fksToDrop[] = ['table' => $schemaT->name, 'foreignKey' => $fk];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });

        return $fksToDrop;
    }
}
