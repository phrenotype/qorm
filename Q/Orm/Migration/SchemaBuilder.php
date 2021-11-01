<?php

namespace Q\Orm\Migration;

use Q\Orm\Field;

class SchemaBuilder
{

    public $name = '';
    public $field_set = [];
    public $index_set = [];
    public $foreign_key_set = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function toTable()
    {
        return new Table($this->name, $this->field_set, $this->index_set, $this->foreign_key_set);
    }

    public function column($name, $type, $definition)
    {
        $this->field_set[] = new Column($name, $type, $definition);
        return $this;
    }

    public function id()
    {
        return $this->column('id', 'bigint', ['null' => false, 'auto_increment' => true, 'unsigned' => true])
            ->primary('id');
    }

    public function integer($name, $definition)
    {
        return $this->column($name, Field::INTEGER, $definition);
    }

    public function boolean($name, $definition){
        return $this->column($name, Field::BOOL, $definition);
    }

    public function string($name, $definition)
    {
        $this->column($name, Field::CHAR, $definition);
    }

    public function text($name, $definition)
    {
        $this->column($name, Field::TEXT, $definition);
    }

    public function date($name, $definition)
    {
        $this->column($name, Field::DATE, $definition);
    }

    public function dateTime($name, $definition)
    {
        $this->column($name, Field::DATETIME, $definition);
    }

    public function decimal($name, $definition)
    {
        $this->column($name, Field::DECIMAL, $definition);
    }

    public function numeric($name, $definition)
    {
        $this->column($name, Field::NUMERIC, $definition);
    }

    public function float($name, $definition)
    {
        $this->column($name, Field::FLOAT, $definition);
    }

    public function double($name, $definition)
    {
        $this->column($name, Field::DOUBLE, $definition);
    }

    public function enum($name, $definition)
    {
        $this->column($name, Field::ENUM, $definition);
    }

    public function index($field)
    {
        $this->index_set[] = new Index($field, Index::INDEX);
        return $this;
    }

    public function unique($field)
    {
        $this->index_set[] = new Index($field, Index::UNIQUE);
        return $this;
    }

    public function primary($field)
    {
        $this->index_set[] = new Index($field, Index::PRIMARY_KEY);
        return $this;
    }

    public function foreignKey($field, $refTable, $refField, $onDelete)
    {
        $this->foreign_key_set[] = new ForeignKey($field, $refTable, $refField, $onDelete);
        return $this;
    }
}