<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;

class TableModelFinder
{
    public static function findPk(string $model)
    {
        $schema = $model::schema();
        $pk = 'id';
        foreach ($schema as $fieldName => $fieldObject) {
            if ($fieldObject->isKey() && $fieldObject->index === Index::PRIMARY_KEY) {
                $pk = $fieldName;
            }
        }
        return $pk;
    }

    public static function findModelColumnName(string $model, string $field)
    {
        $schema = $model::schema();
        $columnName = null;
        foreach ($schema as $fieldName => $fieldObject) {
            if ($field === $fieldName || $field === $fieldObject->column->name) {


                if ($fieldObject->isFk()) {
                    if ($fieldObject->column->name != false) {
                        $columnName = $fieldObject->column->name;
                    } else {
                        $parentClass = $fieldObject->model;
                        $parentPk = TableModelFinder::findPk($parentClass);
                        $parentClassShortName = Helpers::getShortName($parentClass);
                        if (strtolower($fieldName) === strtolower($parentClassShortName)) {
                            $fieldModelPk = self::findPk($fieldObject->model);
                            $columnName = Helpers::modelNameToTableName(Helpers::getShortName($fieldObject->model)) . '_' . $fieldModelPk;
                        } else {
                            $columnName = $fieldName;
                        }
                    }
                } else {
                    if ($fieldObject->column->name != false) {
                        $columnName = $fieldObject->column->name;
                    } else {
                        $columnName = $fieldName;
                    }
                }
                break;
            }
        }
        return $columnName;
    }

    public static function findModelColumn(string $model, callable $predicate)
    {
        $schema = $model::schema();
        $column = null;
        foreach ($schema as $fieldName => $fieldObject) {
            if ($predicate($fieldName, $fieldObject) === true) {
                $column = $fieldObject->column;
                break;
            }
        }
        return $column;
    }

    public static function findModelFK(string $model, callable $predicate)
    {
        $table = CrossEngine::tableFromModels(Helpers::modelNameToTableName(Helpers::getShortName($model)));
        if (is_object($table)) {
            foreach ($table->foreignKeys as $fk) {
                if ($predicate($table, $fk) === true) {
                    return $fk;
                }
            }
        }
        return null;
    }

    public static function findTableColumn(Table $table, callable $predicate)
    {
        $col = null;
        foreach ($table->fields as $column) {
            if ($predicate($table, $column) === true) {
                $col = $column;
                break;
            }
        }
        return $col;
    }

    public static function findTableIndex(Table $table, callable $predicate)
    {
        $idx = null;
        foreach ($table->indexes as $index) {
            if ($predicate($table, $index) === true) {
                $idx = $index;
                break;
            }
        }
        return $idx;
    }

    public static function findTableFk(Table $table, callable $predicate)
    {
        $fk = null;
        foreach ($table->foreignKeys as $foreignKey) {
            if ($predicate($table, $foreignKey) === true) {
                $fk = $foreignKey;
                break;
            }
        }
        return $fk;
    }
}
