<?php

namespace Q\Orm\Traits;

use Q\Orm\Engines\Functions;
use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Querier;
use Q\Orm\SetUp;


/**
 * Confers the ability to avoid race conditions on Handlers and Humans alike.
 */
trait CanRace
{
    /**
     * Build anti-race query
     * 
     * @param array $values
     * @param mixed $operator
     * @param bool $numeric
     * @param bool $append
     * 
     * @return void
     */
    private function conBuild(array $values, $operator, $numeric = true, $append = true): void
    {
        $pk = TableModelFinder::findModelPk($this->__model__);

        foreach ($values as $k => $value) {
            if (!is_numeric($value) && !is_string($value)) {
                throw new \Error("Can only perform arithmetic operations with strings or numbers.");
            }
            if (!in_array($k, Helpers::getModelProperties($this->__model__))) {
                throw new \Error("Unknown field $this->__model__.$k.");
            }
            if (Helpers::isRefField($k, $this->__model__)) {
                throw new \Error("Arithmetic or string operations cannot be performed on $this->__model__.$k because it's a reference field.");
            }
            if ($k == $pk) {
                throw new \Error("Cannot modify {$this->__model__}.$k because it's a primary key.");
            }
        }

        $table = $this->__table_name__;
        $sql = 'UPDATE ' . Helpers::ticks($table) . ' SET ';
        foreach ($values as $k => $v) {
            if ($numeric) {
                $v = sprintf("%d", $v);
                $default = 0;
            } else {
                $v = "'" . sprintf("%s", $v) . "'";
                $default = "''";
            }
            if ($v) {

                if ($operator == null) {
                    if ($append == true) {
                        $sql .= Helpers::ticks($k) . ' = CONCAT(' . Helpers::ticks($k) . ', ' . $v . ')';
                    } else if ($append == false) {
                        $sql .= Helpers::ticks($k) . ' = CONCAT(' . $v . ', ' . Helpers::ticks($k) . ')';
                    }
                } else {
                    if ($append == true) {
                        $sql .= Helpers::ticks($k) . ' = (COALESCE(' . Helpers::ticks($k) . ', ' . $default . ') ' . $operator . ' ' . $v . ')';
                    } else if ($append == false) {
                        $sql .= Helpers::ticks($k) . ' = (' . $v .  ' ' . $operator . ' COALESCE(' . Helpers::ticks($k) . ', ' . $default . '))';
                    }
                }
            }
        }

        $params = [];
        if (!empty($this->__filters__)) {

            $sql .= ' WHERE ';

            $preppedFilters = $this->prepFiltersForUpdateOrDelete();
            $predicate = $preppedFilters['query'];
            $params = $preppedFilters['placeholders'];

            $sql .= $predicate;
        }

        Querier::raw($sql, $params);
    }

    /**
     * Increment (add to) the value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function increment(array $values): Handler
    {
        $this->conBuild($values, '+');
        return $this;
    }

    /**
     * Decrement (subtract) from the value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function decrement(array $values): Handler
    {
        $this->conBuild($values, '-');
        return $this;
    }

    /**
     * Multiply the value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function multiply(array $values): Handler
    {
        $this->conBuild($values, '*');
        return $this;
    }

    /**
     * Divide the value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function divide(array $values): Handler
    {
        $this->conBuild($values, '/');
        return $this;
    }

    /**
     * Get a random object (row) from a Handler.
     * 
     * @return Q\Orm\Model | null
     */
    public function random()
    {
        $function = Functions::random(SetUp::$engine);
        return $this->order_by($function)->one();
    }

    /**
     * Get several random objects (rows) from a Handler.
     * 
     * @param int $limit
     * 
     * @return \Generator
     */
    public function sample(int $limit): \Generator
    {
        $function = Functions::random(SetUp::$engine);
        return $this->order_by($function)->limit($limit)->all();
    }

    /**
     * Append a value to the current value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function append(array $values): Handler
    {
        $this->conBuild($values, null, false, true);
        return $this;
    }

    /**
     * Prepend a value to the current value in a field.
     * 
     * @param array $values
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function prepend(array $values): Handler
    {
        $this->conBuild($values, null, false, false);
        return $this;
    }
}
