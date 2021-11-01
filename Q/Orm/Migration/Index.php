<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;

class Index
{

    const PRIMARY_KEY = 'PRIMARY KEY';
    const INDEX = 'INDEX';
    const UNIQUE = 'UNIQUE';

    public $type;
    public $field;

    public function __construct($field, $type)
    {
        $this->field = $field;
        $this->type = $type;
    }

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
