<?php

namespace Q\Orm;

use Q\Orm\Migration\Column;
use Q\Orm\Migration\ForeignKey;


/**
 * Represents a model field.
 */
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


    /**
     * @var Q\Orm\Migration\Column|null
     */
    public $column;

    /**
     * @var string|null
     */
    public $index;

    /**
     * @var string|null
     */
    public $model;


    /**
     * @var string|null
     */
    public $onDelete;

    /**
     * @var bool
     */
    private $isKey = false;


    /**
     * @var bool
     */
    private $isFk = false;

    /**
     * Checks if a field has a key defined on it.
     * 
     * @return bool
     */
    public function isKey(): bool
    {
        return $this->isKey;
    }

    /**
     * Checks if a field is a foreign key.
     * @return bool
     */
    public function isFk(): bool
    {
        return $this->isFk;
    }

    /**
     * Creates a new Field object.
     * 
     * @param string $type
     * @param callable|null $mutator
     * @param string|null $model
     * @param string|null $index
     * @param string|null $onDelete
     * 
     * @return Field
     */
    private static function generic(string $type, callable $mutator = null, string $model = null, string $index = null, string $onDelete = null): Field
    {

        $column = new Column;
        if (!is_null($mutator)) {
            $mutator($column);
        } else {
            //Set default size for char fields
            if ($type === 'varchar') {
                $column->size = 255;
            }
            $column->null = false;
        }
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

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function BooleanField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::BOOL, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function CharField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::CHAR, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function TextField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::TEXT, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function IntegerField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::INTEGER, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function FloatField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::FLOAT, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function DoubleField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::DOUBLE, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function DecimalField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::DECIMAL, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function NumericField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::NUMERIC, $mutator, null, $index);
    }

    /**
     * @param callable $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function EnumField(callable $mutator, $index = null): Field
    {
        return self::generic(self::ENUM, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function DateField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::DATE, $mutator, null, $index);
    }

    /**
     * @param callable|null $mutator
     * @param string|null $index
     * 
     * @return Field
     */
    public static function DateTimeField(callable $mutator = null, $index = null): Field
    {
        return self::generic(self::DATETIME, $mutator, null, $index);
    }



    /**
     * @param string $model
     * @param callable $mutator
     * @param string $index
     * @param string $onDelete
     * 
     * @return Field
     */
    public static function ManyToOneField(string $model, callable $mutator, string $index, string $onDelete = ForeignKey::RESTRICT): Field
    {
        return self::generic('many_to_one', $mutator, $model, $index, $onDelete);
    }

    /**
     * @param string $model
     * @param callable $mutator
     * @param string $index
     * @param string $onDelete
     * 
     * @return Field
     */
    public static function OneToOneField(string $model, callable $mutator, string $index, string $onDelete = ForeignKey::RESTRICT): Field
    {
        return self::generic('one_to_one', $mutator, $model, $index, $onDelete);
    }

    /**
     * @param string $text
     * 
     * @return string
     */
    public static function textToCode(string $text): string
    {
        $rf = new \ReflectionClass(__CLASS__);
        $constants = $rf->getConstants();
        foreach ($constants as $c => $v) {
            if ($v === $text) {
                return $c;
            }
        }
        return null;
    }
}
