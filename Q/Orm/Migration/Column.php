<?php

namespace Q\Orm\Migration;

use Q\Orm\Engines\CrossEngine;

/**
 * Represents a database column.
 */
class Column
{
    /**
     * @var string|null
     */
    public $name;

    /**
     * @var string|null
     */
    public $type;

    /**
     * @var mixed
     */
    public $size;

    /**
     * @var bool|null
     */
    public $unsigned;

    /**
     * @var bool|null
     */
    public $null;

    /**
     * @var mixed
     */
    public $default;

    /**
     * @var bool
     */
    public $auto_increment;


    /**
     * The constructor.
     * 
     * @param string $name
     * @param string $type
     * @param array $definition
     */
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

    /**
     * Get SQL representation of a Q\Orm\Migration\Column.
     * 
     * @return string
     */
    public function toSql(): string
    {
        return CrossEngine::columnToSql($this);
    }

    public function __get($name)
    {
        return '';
    }
}
