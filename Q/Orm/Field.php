<?php

namespace Q\Orm;

use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;

class Field
{

    const BOOL = 'boolean';
    const CHAR = 'varchar';
    const TEXT = 'text';
    const INTEGER = 'bigint';
    const FLOAT = 'float';
    const DOUBLE = 'double';
    const DECIMAL = 'decimal';
    const NUMERIC = 'numeric';
    const ENUM = 'enum';
    const DATE = 'date';
    const DATETIME = 'datetime';


    public $column;
    public $index;

    public $model;
    public $onDelete;

    private $isKey;
    private $isFk;

    public function isKey()
    {
        return $this->isKey;
    }

    public function isFk()
    {
        return $this->isFk;
    }

    private static function generic($type, callable $mutator, $model = null, $index = null, $onDelete = null)
    {

        $column = new Column;
        $mutator($column);
        if ($column->type !== $type) {
            $column->type = $type;
        }

        if (!isset($column->null)) {
            $column->null = false;
        }

        $field = new self;
        $field->model = $model;
        $field->index = $index;
        $field->onDelete = $onDelete;

        if (($column->type == 'varchar') && ($column->size == null)) {
            throw new \Error(sprintf("Please ensure all 'CharField' attributes have a size specified.", $column->name));
        }

        if ($column->type === 'enum' && ($column->size == null || !is_array($column->size))) {
            throw new \Error('An EnumField must have a size attribute (an array).');
        }

        if ($model != null) {
            $field->isFk = true;
        }

        if ($index != null) {
            $field->isKey = true;
        }

        /* If field is fk, remove all column attributes except name and null and type */
        if ($field->isFk) {
            $attrs = get_object_vars($column);
            foreach ($attrs as $k => $value) {
                if (!in_array($column->type, ['one_to_one', 'many_to_one'])) {
                    $column->type = null;
                }
                if (!in_array($k, ['null', 'name', 'type'])) {
                    $column->$k = null;
                }
            }
        }

        $field->column = $column;

        return $field;
    }

    public static function BooleanField(callable $mutator, $index = null)
    {
        return self::generic(self::BOOL, $mutator, null, $index);
    }

    public static function CharField(callable $mutator, $index = null)
    {
        return self::generic(self::CHAR, $mutator, null, $index);
    }

    public static function TextField(callable $mutator, $index = null)
    {
        return self::generic(self::TEXT, $mutator, null, $index);
    }

    public static function IntegerField(callable $mutator, $index = null)
    {
        return self::generic(self::INTEGER, $mutator, null, $index);
    }

    public static function FloatField(callable $mutator, $index = null)
    {
        return self::generic(self::FLOAT, $mutator, null, $index);
    }

    public static function DoubleField(callable $mutator, $index = null)
    {
        return self::generic(self::DOUBLE, $mutator, null, $index);
    }

    public static function DecimalField(callable $mutator, $index = null)
    {
        return self::generic(self::DECIMAL, $mutator, null, $index);
    }

    public static function NumericField(callable $mutator, $index = null)
    {
        return self::generic(self::NUMERIC, $mutator, null, $index);
    }

    public static function EnumField(callable $mutator, $index = null)
    {
        return self::generic(self::ENUM, $mutator, null, $index);
    }

    public static function DateField(callable $mutator, $index = null)
    {
        return self::generic(self::DATE, $mutator, null, $index);
    }

    public static function DateTimeField(callable $mutator, $index = null)
    {
        return self::generic(self::DATETIME, $mutator, null, $index);
    }

    public static function ManyToOneField($model, callable $mutator, $index, $onDelete = ForeignKey::RESTRICT)
    {
        return self::generic('many_to_one', $mutator, $model, $index, $onDelete);
    }

    public static function OneToOneField($model, callable $mutator, $index, $onDelete = ForeignKey::RESTRICT)
    {
        return self::generic('one_to_one', $mutator, $model, $index, $onDelete);
    }

    public static function textToCode(string $text){
        $rf = new \ReflectionClass(__CLASS__);
        $constants = $rf->getConstants();
        foreach($constants as $c=>$v){
            if($v === $text){
                return $c;
            }
        }
        return null;
    }
}
