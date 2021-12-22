<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;

/**
 * Represents an Index
 */
class Index
{

    const PRIMARY_KEY = 'PRIMARY KEY';
    const INDEX = 'INDEX';
    const UNIQUE = 'UNIQUE';

    /**
     * @var string
     */
    public $type;


    /**
     * @var string
     */
    public $field;

    /**
     * The constructor.
     * @param mixed $field
     * @param mixed $type
     */
    public function __construct($field, $type)
    {
        $this->field = $field;
        $this->type = $type;
    }

    /**
     * Get an SQL representation of an Q\Orm\Migration\Index.
     * @return string
     */
    public function sql()
    {
        if ($this->type === self::UNIQUE) {
            return 'UNIQUE(' . Helpers::ticks($this->field) . ')';
        } else if ($this->type === self::INDEX) {
            return 'INDEX(' . Helpers::ticks($this->field) . ')';
        } else if ($this->type === self::PRIMARY_KEY) {
            return 'PRIMARY KEY(' . Helpers::ticks($this->field) . ')';
        }
        return '';
    }
}
