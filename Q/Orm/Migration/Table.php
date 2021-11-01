<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;

class Table
{

    public $name;
    public $oldName;

    /*
        * An array of Q\Orm\Migration\Column
    */
    public $fields = [];

    /* An array of Index */
    public $indexes = [];

    /* An array of foreign keys */
    public $foreignKeys = [];

    public function __construct($name, array $fields = [], array $indexes = [], array $foreignKeys = [])
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->indexes = $indexes;
        $this->foreignKeys = $foreignKeys;
    }

    public function toSql()
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