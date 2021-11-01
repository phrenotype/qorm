<?php

namespace Q\Orm\Migration;

use Q\Orm\Migration\Models\Q_Migration;

class StateBuilder
{

    /* Entry point */
    public static function build()
    {

        $dbMigrations = Q_Migration::items()->order_by('id ASC')->all();
        $state = [];

        if ($dbMigrations) {
            foreach ($dbMigrations as $migration) {
                $state = self::operationsToTables($migration->name, $state);
            }
        }
        return $state;
    }

    public static function safeMerge($fromSchema, $tablesToCreate)
    {
        foreach ($fromSchema as $i => $fs) {
            foreach ($tablesToCreate as $j => $tc) {
                $fs_field_names = array_map(function ($t) {
                    return $t->name;
                }, $fs->fields);
                $tc_field_names = array_map(function ($t) {
                    return $t->name;
                }, $tc->fields);
                if ((count($fs_field_names) == count($tc_field_names)) && (sort($fs_field_names) == sort($tc_field_names))) {
                    unset($tablesToCreate[$j]);
                }
            }
        }
        return array_merge($fromSchema, $tablesToCreate);
    }

    private static function operationsToTables(string $migration, array $prevState)
    {
        $tables = $prevState;


        $obj = new $migration;
        $ops = $obj->operations;

        /* Extract new tables */
        foreach ($ops as $op) {
            $o = $op();
            if ($o->name === Operation::CREATE_TABLE) {
                $tables[] = $o->params['table'];
            } else {
                continue;
            }
        }

        foreach ($ops as $op) {
            $o = $op();
            if ($o->name === Operation::RENAME_TABLE) {
                foreach ($tables as $i => $table) {
                    if ($table->name === $o->params['from']) {
                        $table->name = $o->params['to'];
                    }
                }
            } else if (in_array($o->name, [Operation::DROP_TABLE, Operation::DROP_TABLE_IF_EXISTS])) {
                foreach ($tables as $index => $table) {
                    if ($table->name === $o->params['table']) {
                        unset($tables[$index]);
                    }
                }
            } else if ($o->name === Operation::ADD_COLUMN) {
                foreach ($tables as $table) {
                    $tableToAdd = $o->params['table'];
                    $columnToAdd = $o->params['column'];

                    if ($table->name === $tableToAdd) {
                        $columnExists = TableModelFinder::findTableColumn(
                            $table,
                            function (Table $t, Column $c) use ($columnToAdd) {
                                if ($c->name === $columnToAdd->name) {
                                    return true;
                                }
                                return false;
                            }
                        );
                        if (!$columnExists) {
                            $table->fields[] = $columnToAdd;
                        }
                    }
                }
            } else if ($o->name === Operation::DROP_COLUMN) {
                foreach ($tables as $table) {
                    if ($table->name === $o->params['table']) {
                        foreach ($table->fields as $index => $column) {
                            if ($column->name === $o->params['column']) {
                                unset($table->fields[$index]);
                            }
                        }
                    }
                }
            } else if ($o->name === Operation::MODIFY_COLUMN) {
                foreach ($tables as $table) {
                    if ($table->name === $o->params['table']) {
                        $fields = $table->fields;
                        foreach ($fields as $index => $column) {
                            if ($column->name === $o->params['column']->name) {
                                $colName = $column->name;
                                $fields[$index] = $o->params['column'];
                                $fields[$index]->name = $colName;
                            }
                        }
                        $table->fields = $fields;
                    }
                }
            } else if ($o->name === Operation::CHANGE_COLUMN) {
                foreach ($tables as $table) {
                    if ($table->name === $o->params['table']) {
                        $fields = $table->fields;
                        foreach ($table->fields as $index => $column) {
                            if ($column->name === $o->params['column']->name) {
                                $fields[$index] = $o->params['column'];
                            }
                        }
                    }
                }
            } else if ($o->name === Operation::ADD_FOREIGN_KEY) {

                foreach ($tables as $table) {

                    $field = $o->params['field'];
                    $refTable = $o->params['refTable'];
                    $refField = $o->params['refField'];
                    $onDelete = $o->params['onDelete'];

                    if ($table->name === $o->params['table']) {
                        $fkExists = TableModelFinder::findTableFk(
                            $table,
                            function (Table $t, ForeignKey $fk) use ($field) {
                                if ($fk->field === $field) {
                                    return true;
                                }
                                return false;
                            }
                        );
                        if (!$fkExists) {
                            $table->foreignKeys[] = new ForeignKey($field, $refTable, $refField, $onDelete);
                        }
                    }
                }
            } else if ($o->name === Operation::ADD_INDEX) {

                foreach ($tables as $table) {

                    $field = $o->params['field'];

                    if ($table->name === $o->params['table']) {
                        $indexExists = TableModelFinder::findTableIndex(
                            $table,
                            function (Table $t, Index $i) use ($field) {
                                if ($i->field === $field) {
                                    return true;
                                }
                                return false;
                            }
                        );
                        if (!$indexExists) {
                            $table->indexes[] = new Index($field, Index::INDEX);
                        }
                    }
                }
            } else if ($o->name === Operation::ADD_UNIQUE) {
                foreach ($tables as $table) {

                    $field = $o->params['field'];

                    if ($table->name === $o->params['table']) {
                        $indexExists = TableModelFinder::findTableIndex(
                            $table,
                            function (Table $t, Index $i) use ($field) {
                                if ($i->field === $field) {
                                    return true;
                                }
                                return false;
                            }
                        );
                        if (!$indexExists) {
                            $table->indexes[] = new Index($field, Index::UNIQUE);
                        }
                    }
                }
            } else if ($o->name === Operation::ADD_PRIMARY_KEY) {
                foreach ($tables as $table) {

                    $field = $o->params['field'];

                    if ($table->name === $o->params['table']) {
                        $indexExists = TableModelFinder::findTableIndex(
                            $table,
                            function (Table $t, Index $i) use ($field) {
                                if ($i->field === $field) {
                                    return true;
                                }
                                return false;
                            }
                        );
                        if (!$indexExists) {
                            $table->indexes[] = new Index($field, Index::PRIMARY_KEY);
                        }
                    }
                }
            } else if (in_array($o->name, [Operation::DROP_INDEX, Operation::DROP_UNIQUE, Operation::DROP_PRIMARY_KEY])) {
                foreach ($tables as $table) {
                    if ($table->name === $o->params['table']) {
                        foreach ($table->indexes as $i => $idx) {
                            if ($idx->field === $o->params['field']) {
                                unset($table->indexes[$i]);
                            }
                        }
                    }
                }
            } else if ($o->name === Operation::DROP_FOREIGN_KEY) {
                foreach ($tables as $table) {
                    if ($table->name === $o->params['table']) {
                        foreach ($table->foreignKeys as $i => $fk) {
                            if ($fk->field === $o->params['field']) {
                                unset($table->foreignKeys[$i]);
                            }
                        }
                    }
                }
            }
        }

        return $tables;
    }




    public static function renameTables(array $state, array $toFrom): array
    {
        foreach ($toFrom as $tf) {
            foreach ($state as $table) {
                if ($table->name === $tf['from']) {
                    $table->name = $tf['to'];
                    $table->oldName = $tf['from'];
                }
            }
        }
        return $state;
    }

    public static function dropTables(array $state, array $tablesToRename): array
    {
        foreach ($tablesToRename as $ttr) {
            foreach ($state as $i => $table) {
                if ($table->name === $ttr) {
                    unset($state[$i]);
                }
            }
        }
        return $state;
    }




    public static function addColumns(array $state, array $columnsToAdd): array
    {
        foreach ($columnsToAdd as $cta) {
            foreach ($state as $table) {
                $tableToAdd = $cta['table'];
                $columnToAdd = $cta['column'];

                if ($table->name === $tableToAdd) {
                    $columnExists = TableModelFinder::findTableColumn(
                        $table,
                        function (Table $t, Column $c) use ($columnToAdd) {
                            if ($c->name === $columnToAdd->name) {
                                return true;
                            }
                            return false;
                        }
                    );
                    if (!$columnExists) {
                        $table->fields[] = $columnToAdd;
                    }
                }
            }
        }
        return $state;
    }

    public static function dropColumns(array $state, array $columnsToDrop): array
    {
        foreach ($columnsToDrop as $ctd) {
            foreach ($state as $table) {
                if ($table->name === $ctd['table']) {
                    foreach ($table->fields as $index => $column) {
                        if ($column->name === $ctd['column']) {
                            unset($table->fields[$index]);
                        }
                    }
                }
            }
        }
        return $state;
    }

    public static function modifyColumns(array $state, array $columnsToModify): array
    {
        foreach ($columnsToModify as $ctm) {
            foreach ($state as $table) {
                if ($table->name === $ctm['table']) {
                    $fields = $table->fields;
                    foreach ($fields as $index => $column) {
                        if ($column->name === $ctm['column']->name) {
                            $colName = $column->name;
                            $fields[$index] = $ctm['column'];
                            $fields[$index]->name = $colName;
                        }
                    }
                    $table->fields = $fields;
                }
            }
        }
        return $state;
    }

    public static function changeColumns(array $state, array $columnsToChange): array
    {
        foreach ($columnsToChange as $ctc) {
            foreach ($state as $table) {
                if ($table->name === $ctc['table']) {
                    $fields = $table->fields;
                    foreach ($table->fields as $index => $column) {
                        if ($column->name === $ctc['column']->name) {
                            $fields[$index] = $ctc['column'];
                        }
                    }
                }
            }
        }
        return $state;
    }


    public static function addForeignKeys(array $state, array $fksToAdd): array
    {
        foreach ($fksToAdd as $fkToAdd) {
            foreach ($state as $table) {

                $field = $fkToAdd['foreignKey']->field;
                $refTable = $fkToAdd['foreignKey']->refTable;
                $refField = $fkToAdd['foreignKey']->refField;
                $onDelete = $fkToAdd['foreignKey']->onDelete;

                if ($table->name === $fkToAdd['table']) {
                    $fkExists = TableModelFinder::findTableFk(
                        $table,
                        function (Table $t, ForeignKey $fk) use ($field) {
                            if ($fk->field === $field) {
                                return true;
                            }
                            return false;
                        }
                    );
                    if (!$fkExists) {
                        $table->foreignKeys[] = new ForeignKey($field, $refTable, $refField, $onDelete);
                    }
                }
            }
        }
        return $state;
    }

    public static function addIndexes(array $state, array $indexesToAdd): array
    {
        foreach ($indexesToAdd as $idxToAdd) {
            foreach ($state as $table) {

                $field = $idxToAdd['index']->field;

                $tbName = ($table->oldName) ? $table->oldName : $table->name;

                if ($tbName === $idxToAdd['table']) {
                    $indexExists = TableModelFinder::findTableIndex(
                        $table,
                        function (Table $t, Index $i) use ($field) {
                            if ($i->field === $field) {
                                return true;
                            }
                            return false;
                        }
                    );
                    if (!$indexExists) {
                        $table->indexes[] = new Index($field, Index::INDEX);
                    }
                }
            }
        }
        return $state;
    }

    public static function addUniques(array $state, array $uniquesToAdd): array
    {
        foreach ($uniquesToAdd as $uta) {
            foreach ($state as $table) {

                $field = $uta['field'];

                if ($table->name === $uta['table']) {
                    $indexExists = TableModelFinder::findTableIndex(
                        $table,
                        function (Table $t, Index $i) use ($field) {
                            if ($i->field === $field) {
                                return true;
                            }
                            return false;
                        }
                    );
                    if (!$indexExists) {
                        $table->indexes[] = new Index($field, Index::UNIQUE);
                    }
                }
            }
        }
        return $state;
    }

    public static function addPrimaryKeys(array $state, array $pksToAdd): array
    {
        foreach ($pksToAdd as $pk) {
            foreach ($state as $table) {

                $field = $pk['field'];

                if ($table->name === $pk['table']) {
                    $indexExists = TableModelFinder::findTableIndex(
                        $table,
                        function (Table $t, Index $i) use ($field) {
                            if ($i->field === $field) {
                                return true;
                            }
                            return false;
                        }
                    );
                    if (!$indexExists) {
                        $table->indexes[] = new Index($field, Index::PRIMARY_KEY);
                    }
                }
            }
        }
        return $state;
    }


    public static function dropIndexes(array $state, array $indexesToDrop): array
    {
        foreach ($indexesToDrop as $itd) {
            foreach ($state as $table) {
                $tbName = $table->oldName ?? $table->name;
                if ($tbName === $itd['table']) {
                    foreach ($table->indexes as $i => $idx) {
                        if ($idx->field === $itd['index']->field) {
                            unset($table->indexes[$i]);
                        }
                    }
                }
            }
        }
        return $state;
    }

    public static function dropForeignKeys(array $state, array $fksToDrop): array
    {
        foreach ($fksToDrop as $fkToDrop) {
            foreach ($state as $table) {
                if ($table->name === $fkToDrop['table']) {
                    foreach ($table->foreignKeys as $i => $fk) {

                        if ($fk->field === $fkToDrop['foreignKey']->field) {
                            unset($table->foreignKeys[$i]);
                        }
                    }
                }
            }
        }
        return $state;
    }
}