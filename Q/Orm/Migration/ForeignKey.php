<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;

class ForeignKey
{

    const RESTRICT = 'RESTRICT';
    const CASCADE = 'CASCADE';
    const NULLIFY  = 'SET NULL';

    public $field;
    public $refTable;
    public $refField;

    public $onDelete;

    public function __construct($field, $refTable, $refField, $onDelete = self::RESTRICT)
    {
        $this->field = $field;
        $this->refTable = $refTable;
        $this->refField = $refField;
        $this->onDelete = $onDelete;
    }

    public function sql()
    {
        return 'FOREIGN KEY(' . Helpers::ticks($this->field) . ') REFERENCES ' . Helpers::ticks($this->refTable) . '(' . Helpers::ticks($this->refField) . ') ON DELETE ' . $this->onDelete;
    }
}