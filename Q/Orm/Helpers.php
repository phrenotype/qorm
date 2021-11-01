<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;

class Helpers
{

    public static function filterableTerminals()
    {
        return [
            'eq',
            'neq',
            'lt',
            'lte',
            'gt',
            'gte',

            'contains',
            'icontains',

            'regex',
            'iregex',

            'startswith',
            'istartswith',

            'endswith',
            'iendswith',

            'is_null',

            'in',
            'not_in'
        ];
    }

    public static function filterableMutators()
    {
        return [
            'lower',
            'upper',
            'length',
            'trim',
            'ltrim',
            'rtrim',

            'date',
            'time',
            'year',
            'day',
            'month',
            'hour',
            'minute',
            'second'
        ];
    }

    public static function isModelEmpty(Model $object)
    {
        //At least one defined attribute has to be set
        $class = Helpers::getClassName($object);
        $props = Helpers::getModelProperties($class);
        $empty = true;
        foreach ($props as $p) {
            if (!is_null($object->$p)) {
                $empty = false;
            }
        }
        return $empty;
    }

    public static function getModelRefFields(string $model)
    {
        $schema = $model::schema();
        $r = [];
        foreach ($schema as $prop => $field) {
            $name = $prop;
            if (!empty($field->column->name)) {
                $name =  ($prop === $field->column->name) ? $prop : $field->column->name;
            }
            if (Helpers::isRefField($name, $model)) {
                $r[] = [
                    'model' => $field->model,
                    'field' => $prop
                ];
            }
        }
        return $r;
    }

    public static function isRefField(string $field, string $model)
    {

        $returnValue = false;

        // $cols = Helpers::getModelColumns($model);
        // $props = Helpers::getModelProperties($model);

        // $inCols = in_array($field, $cols);
        // $inProps = in_array($field, $props);

        // $onlyProps = $inProps && !$inCols;
        // $onlyCols = !$inProps && $inCols;
        // $inBoth = $inProps && $inCols;

        // /* Doing this because fk check only uses the 'property' name of the field */
        // $colName = TableModelFinder::findModelColumnName($model, $field);

        // $isFk = TableModelFinder::findModelFK($model, function ($table, $fk) use ($colName) {
        //     return ($fk->field == $colName);
        // });


        // if (($inBoth || $onlyProps) && is_object($isFk)) {
        //     $returnValue = true;
        // }

        if (!$returnValue) {
            $schema = $model::schema();
            foreach ($schema as $k => $obj) {
                if ($obj->isFk()) {
                    $name = TableModelFinder::findModelColumnName($model, $k);
                    if ($name === $field || $k === $field) {
                        $returnValue = true;
                    }
                }
            }
        }

        return $returnValue;
    }

    public static function remove($value, array $assoc)
    {

        $key = array_search($value, $assoc);
        if ($key !== false) {
            unset($assoc[$key]);
        }
        return $assoc;
    }

    public static function ticks(string $value)
    {
        if (preg_match('#(?mi)^`.*`$#', $value)) {
            return $value;
        } else {
            return '`' . $value . '`';
        }
    }

    public static function getClassName(object $object)
    {
        return (new \ReflectionObject($object))->name;
    }

    public static function getShortName($class)
    {
        return (new \ReflectionClass($class))->getShortName();
    }

    public static function getModelProperties($class)
    {
        $schema = $class::schema();
        $fields = [];
        foreach ($schema as $field => $fieldObject) {

            $fields[] = $field;
        }
        return $fields;
    }

    public static function getModelColumns($class)
    {
        $schema = $class::schema();
        $fields = [];
        foreach ($schema as $field => $fieldObject) {
            $fields[] = TableModelFinder::findModelColumnName($class, $field);
        }
        return $fields;
    }

    public static function getDeclaredModels()
    {
        $parent = Model::class;
        return array_filter(get_declared_classes(), function ($class) use ($parent) {
            return (is_subclass_of($class, $parent) && self::getShortName($class) !== 'Q_Migration');
        });
    }

    public static function tableNameToModelName(string $tableName)
    {
        $tableName = trim($tableName, "_");
        return preg_replace_callback(
            '#_[a-zA-Z]#',
            function ($m) {
                return strtoupper(str_replace('_', '', $m[0]));
            },
            ucwords($tableName)
        );
    }

    public static function modelNameToTableName(string $modelName)
    {
        $modelName = trim($modelName, "_");
        $replaced = preg_replace_callback(
            '#(?<!^|_)[A-Z]#',
            function ($m) {
                $concat = '_' . $m[0];
                $concat = strtolower($concat);
                return $concat;
            },
            $modelName
        );
        return strtolower($replaced);
    }

    public static function runAsTransaction(string $largeQuery)
    {
        $pdo = Connection::getInstance();
        try {

            fwrite(STDOUT, $largeQuery . PHP_EOL);

            $pdo->beginTransaction();
            $pdo->exec($largeQuery);
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollback();
            throw $e;
        }
    }

    public static function files($folder)
    {
        //Get all the files in a folder. It includes files in child folders
        $files = scandir($folder);
        $modified_files = array_filter($files, function ($path) {
            return ($path !== '.') && ($path !== '..');
        });

        $tmp = [];
        foreach ($modified_files as $file) {
            $file = $folder . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file)) {
                $tmp = array_merge($tmp, self::files($file));
            } else {
                $tmp[] = $file;
            }
        }
        return $tmp;
    }
}
