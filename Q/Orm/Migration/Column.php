<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;

class Column
{
    public $name;
    public $type;
    public $size;
    public $unsigned;
    public $null;
    public $default;
    public $auto_increment;


    public function __construct($name = '', $type = '', array $definition = [])
    {
        $this->name = $name;
        $this->type = $type;
        foreach ($definition as $key => $value) {
            if (array_key_exists($key, get_class_vars(self::class)) && !in_array($key, ['name', 'type'])) {
                $this->$key = $value;
            }
        }
    }

    public function toSql()
    {
        return CrossEngine::columnToSql($this);
    }

    public function __get($name)
    {
        return '';
    }
}
