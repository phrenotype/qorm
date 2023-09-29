<?php

namespace Q\Orm\Migration;

use Q\Orm\Field;

class SchemaBuilder
{

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var Q\Orm\Migration\Column[]
     */
    public $field_set = [];

    /**
     * @var Q\Orm\Migration\Index[]
     */
    public $index_set = [];

    /**
     * @var Q\Orm\Migration\ForeignKey[]
     */
    public $foreign_key_set = [];


    /**
     * The constructor.
     * 
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get a Q\Orm\Migration\Table representation of built schema.
     * 
     * @return Table
     */
    public function toTable(): Table
    {
        return new Table($this->name, $this->field_set, $this->index_set, $this->foreign_key_set);
    }

    /**
     * Add a column.
     * 
     * @param string $name
     * @param string $type
     * @param array $definition
     * 
     * @return Q\Orm\Migration\SchemaBuilder
     */
    public function column(string $name, string $type, array $definition): SchemaBuilder
    {
        $this->field_set[] = new Column($name, $type, $definition);
        return $this;
    }


    /**
     * Add an autoincrementing integer field called 'id' as primary key.
     * 
     * @return SchemaBuilder
     */
    public function id(): SchemaBuilder
    {
        return $this->column('id', 'bigint', ['null' => false, 'auto_increment' => true, 'unsigned' => true])
            ->primary('id');
    }

    /**
     * Add an integer column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function integer(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::INTEGER, $definition);
        return $this;
    }

    /**
     * Add a boolean column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function boolean(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::BOOL, $definition);
        return $this;
    }

    /**
     * Add a string (varchar) column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function string(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::CHAR, $definition);
        return $this;
    }

    /**
     * Add a text column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function text(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::TEXT, $definition);
        return $this;
    }

    /**
     * Add a date column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function date(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::DATE, $definition);
        return $this;
    }

    /**
     * Add a datetime column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function dateTime(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::DATETIME, $definition);
        return $this;
    }

    /**
     * Add a decimal column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function decimal(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::DECIMAL, $definition);
        return $this;
    }

    /**
     * Add a numeric column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function numeric(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::NUMERIC, $definition);
        return $this;
    }

    /**
     * Add a float column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function float(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::FLOAT, $definition);
        return $this;
    }

    /**
     * Add a double column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function double(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::DOUBLE, $definition);
        return $this;
    }

    /**
     * Add an enum column.
     * 
     * @param string $name
     * @param array $definition
     * 
     * @return SchemaBuilder
     */
    public function enum(string $name, array $definition): SchemaBuilder
    {
        $this->column($name, Field::ENUM, $definition);
        return $this;
    }

    /**
     * Add an index.
     * 
     * @param string $field
     * 
     * @return SchemaBuilder
     */
    public function index(string $field): SchemaBuilder
    {
        $this->index_set[] = new Index($field, Index::INDEX);
        return $this;
    }

    /**
     * Add a unique index.
     * 
     * @param string $field
     * 
     * @return SchemaBuilder
     */
    public function unique(string $field): SchemaBuilder
    {
        $this->index_set[] = new Index($field, Index::UNIQUE);
        return $this;
    }

    /**
     * Add a primary key.
     * 
     * @param string $field
     * 
     * @return SchemaBuilder
     */
    public function primary(string $field): SchemaBuilder
    {
        $this->index_set[] = new Index($field, Index::PRIMARY_KEY);
        return $this;
    }

    /**
     * Add a foreign key.
     * 
     * @param string $field
     * @param string $refTable
     * @param string $refField
     * @param string $onDelete
     * 
     * @return SchemaBuilder
     */
    public function foreignKey(string $field, string $refTable, string $refField, string $onDelete)
    {
        $this->foreign_key_set[] = new ForeignKey($field, $refTable, $refField, $onDelete);
        return $this;
    }
}
