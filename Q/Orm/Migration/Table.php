<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;

/**
 * Represents a database table.
 */
class Table
{

    /**
     * @var string
     */
    public $name;

    /**
     * @var string|null
     */
    public $oldName;


    /**
     * @var Q\Orm\Migration\Column[]
     */
    public $fields = [];


    /**
     * @var Q\Orm\Migration\Index[]
     */
    public $indexes = [];


    /**
     * @var Q\Orm\Migration\ForeignKey[]
     */
    public $foreignKeys = [];

    /**
     * The constructor.
     * 
     * @param string $name
     * @param array $fields
     * @param array $indexes
     * @param array $foreignKeys
     */
    public function __construct(string $name, array $fields = [], array $indexes = [], array $foreignKeys = [])
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->indexes = $indexes;
        $this->foreignKeys = $foreignKeys;
    }

    /**
     * Get SQL representation of a Q\Orm\Migration\Table.
     * 
     * @return string
     */
    public function toSql(): string
    {
        return CrossEngine::tableToSql($this);
    }

    public function __toString()
    {
        return $this->name;
    }

    public function __get($name)
    {
        return '';
    }
}
