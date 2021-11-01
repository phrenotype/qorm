<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;
use Q\Orm\SetUp;

class SchemaToModel
{
    private static function decideFieldMethod(Column $column)
    {
        $type = strtolower($column->type);
        if ($type === 'int' || preg_match("#^int#", $type) || preg_match("#int$#", $type)) {
            return "Field::IntegerField";
        } else if ($type === 'boolean' || ($type === 'tinyint' && ($column->size === null || $column->size == 1))) {
            return "Field::BooleanField";
        } else if ($type === 'char' || preg_match("#^char#", $type) || preg_match("#char$#", $type)) {
            return "Field::CharField";
        } else if ($type === 'text' || preg_match("#^text#", $type) || preg_match("#text$#", $type)) {
            return "Field::TextField";
        } else if ($type === 'date') {
            return "Field::DateField";
        } else if ($type === 'timestamp' || $type === 'datetime') {
            return "Field::DateTimeField";
        } else if ($type === 'float' || $type === 'real') {
            return "Field::FloatField";
        } else if ($type === 'double') {
            return "Field::DoubleField";
        } else if ($type === 'decimal') {
            return "Field::DecimalField";
        } else if ($type === 'numeric') {
            return "Field::NumericField";
        } else if ($type === 'enum') {
            return "Field::EnumField";
        } else {
            throw new \Error('Type of ' . $type . ' is unknown. Line ' . __LINE__);
        }
    }
    private static function columnToText(Table $table, Column $column)
    {

        $realColumnName = $column->name;

        $isFK = TableModelFinder::findTableFk($table, function ($table, $fk) use ($realColumnName) {
            return ($fk->field === $realColumnName);
        });


        $attrs = "";
        foreach (get_object_vars($column) as $key => $value) {
            if (($value == null && $key !== 'null') || $key === 'type') continue;
            $attrs .= "\t\t\t\t\$column->$key = " . var_export($value, true) . ";" . PHP_EOL;;
        }

        $index = '';

        foreach ($table->indexes as $ind) {
            if ($ind->field === $realColumnName) {
                if ($ind->type === Index::INDEX) {
                    $index = "Index::INDEX";
                } else if ($ind->type === Index::UNIQUE) {
                    $index = "Index::UNIQUE";
                } else if ($ind->type === Index::PRIMARY_KEY) {
                    $index = "Index::PRIMARY_KEY";
                }
            }
        }

        $tabs = "\t\t\t";

        if (!is_object($isFK)) {
            $fieldType = self::decideFieldMethod($column);
            $code = $tabs . "'$column->name' => $fieldType(function(Column \$column){\n$attrs\t\t\t}";
            if ($index) {
                $code .= ", $index)";
            } else {
                $code .= ")";
            }
            $code .= "," . PHP_EOL;
            return $code;
        } else {
            $className = Helpers::tableNameToModelName($isFK->refTable);
            $fkOnDelete = '';
            if ($isFK->onDelete === ForeignKey::CASCADE) {
                $fkOnDelete = "ForeignKey::CASCADE";
            } else if ($isFK->onDelete === ForeignKey::NULLIFY) {
                $fkOnDelete = "ForeignKey::NULLIFY";
            } else if ($isFK->onDelete === ForeignKey::RESTRICT) {
                $fkOnDelete = 'ForeignKey::RESTRICT';
            }

            //Just default to Many-to-one. The user can simply change it as sees fit
            $fieldType = 'Field::ManyToOneField';
            $code = $tabs . "'$column->name' => $fieldType($className::class, function(Column \$column){\n$attrs\t\t\t}";
            if ($index) {
                $code .= ", $index";
            }
            if ($fkOnDelete) {
                $code .= ", $fkOnDelete";
            }

            $code .= ")," . PHP_EOL;;
            return $code;
        }
    }
    private static function tableToModel(Table $table)
    {
        $tableName = Helpers::tableNameToModelName($table->name);
        $pk = TableModelFinder::findTableIndex($table, function ($table, Index $index) {
            return ($index->type === Index::PRIMARY_KEY);
        });

        $code = PHP_EOL . "class " . $tableName . " extends Model {" . PHP_EOL . PHP_EOL;

        $columns = array_filter($table->fields, function ($c) use ($pk) {
            return ($c->name !== 'id'  && is_object($pk) && $pk->field === 'id');
        });
        $columns = array_map(function (Column $column) use ($table) {
            return $column->name;
        }, $columns);
        foreach ($columns as $col) {
            $code .= "\tpublic \${$col};" . PHP_EOL;
        }
        $code .= PHP_EOL;


        $code .= "\tpublic static function schema(){" . PHP_EOL;
        $code .= "\t\treturn [" . PHP_EOL;
        foreach ($table->fields as $column) {
            if ($column->name === 'id' && is_object($pk) && $pk->field === 'id') continue;
            $code .= self::columnToText($table, $column);
        }
        $code .= "\t\t];";
        $code .= PHP_EOL . "\t}";


        $code .= PHP_EOL . "}";

        return $code;
    }
    private static function tablesToModels(array $tables)
    {
        $codes = [];
        foreach ($tables as $table) {
            $codes[Helpers::tableNameToModelName($table->name)] = self::tableToModel($table);
        }
        return $codes;
    }
    private static function modelFileHeading($path)
    {
        $dir = str_replace('.', '', dirname($path));
        $base = basename($path, '.php');

        if ($dir) {
            $namespace = str_replace("//", "\\", $dir . '\\' . $base);
        } else {
            $namespace = $base;
        }


        return '<?php' . PHP_EOL . PHP_EOL . "namespace $namespace;" .
            PHP_EOL . PHP_EOL . 'use Q\Orm\Model;' . PHP_EOL . 'use Q\Orm\Field;'
            . PHP_EOL . 'use Q\Orm\Migration\Column;' . PHP_EOL
            . 'use Q\Orm\Migration\ForeignKey;' . PHP_EOL . 'use Q\Orm\Migration\Index;' . PHP_EOL;
    }
    private static function makeModelFile(string $path, array $codes)
    {
        $contents = self::modelFileHeading($path);
        foreach ($codes as $class => $code) {
            $contents .= $code . PHP_EOL . PHP_EOL;
        }
        file_put_contents($path, $contents);
    }
    private static function makeModelsDir(string $path, array $codes)
    {
        $heading = self::modelFileHeading($path);
        foreach ($codes as $class => $code) {
            $contents = $heading . $code . PHP_EOL . PHP_EOL;
            file_put_contents($path . DIRECTORY_SEPARATOR . $class . '.php', $contents);
        }
    }
    public static function main(string $modelLocation = '')
    {
        $tables = Introspector::schemaToArrayOfTables();

        if (!empty($tables)) {
            $codes = self::tablesToModels($tables) ?? [];
            if ($modelLocation === '') {
                $modelLocation = SetUp::$modelsPath;
            }
            if (file_exists($modelLocation)) {
                if (is_file($modelLocation)) {
                    self::makeModelFile($modelLocation, $codes);
                } else if (is_dir($modelLocation)) {
                    self::makeModelsDir($modelLocation, $codes);
                }
            } else {
                throw new \Error("Model Location Provided Does Not Exist.");
            }
        } else {
            die('No tables found');
        }
    }
}
