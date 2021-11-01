<?php

namespace Q\Orm\Traits;

use Q\Orm\Engines\Functions;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Querier;
use Q\Orm\SetUp;

trait CanRace
{
    private function conBuild(array $values, $operator, $numeric = true, $append = true)
    {
        $pk = TableModelFinder::findPk($this->__model__);

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

    public function increment(array $values)
    {        
        $this->conBuild($values, '+');
        return $this;
    }

    public function decrement(array $values)
    {        
        $this->conBuild($values, '-');
        return $this;
    }

    public function multiply(array $values)
    {        
        $this->conBuild($values, '*');
        $this;
    }

    public function divide(array $values)
    {        
        $this->conBuild($values, '/');
        return $this;
    }

    public function random()
    {        
        $function = Functions::random(SetUp::$engine);
        return $this->order_by($function)->one();
    }

    public function sample($limit)
    {        
        $function = Functions::random(SetUp::$engine);
        return $this->order_by($function)->limit($limit)->all();
    }

    public function append(array $values)
    {        
        $this->conBuild($values, null, false, true);
        return $this;
    }

    public function prepend(array $values)
    {        
        $this->conBuild($values, null, false, false);
        return $this;
    }
}
