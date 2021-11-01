<?php

namespace Q\Orm;

use Q\Orm\Engines\CastTypes;
use Q\Orm\Engines\Functions;

class ModelFilterable implements Filterable
{

    private $key;
    private $value;

    private $expression;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /* Final methods */
    public function eq()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' = (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' = ?';
        }

        return $this;
    }

    public function neq()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' <> (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' <> ?';
        }
        return $this;
    }

    public function lt()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' < (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' < ?';
        }
        return $this;
    }

    public function lte()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' <= (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' <= ?';
        }
        return $this;
    }

    public function gt()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' > (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' > ?';
        }
        return $this;
    }

    public function gte()
    {
        if ($this->value instanceof Handler) {
            list($q, $placeholders) = $this->value->buildAggregateQuery();
            $this->expression = $this->key . ' >= (' . $q . ')';
            $this->value = null;
        } else {
            $this->expression = $this->key . ' >= ?';
        }
        return $this;
    }


    public function contains()
    {
        $this->expression = $this->key . ' LIKE ?';
        $this->value = "%$this->value%";
        return $this;
    }

    public function icontains()
    {
        $this->lower();
        $this->expression = $this->key . ' LIKE ?';
        $compVal = strtolower($this->value);
        $this->value = "%$compVal%";
        return $this;
    }

    public function regex()
    {
        $this->expression = $this->key . ' REGEXP ?';
        return $this;
    }

    public function iregex()
    {
        $this->lower();
        $this->value = strtolower($this->value);
        $this->expression = $this->key . ' REGEXP ?';
        return $this;
    }

    public function startswith()
    {
        $this->expression = $this->key . ' LIKE ?';
        $this->value = "$this->value%";
        return $this;
    }

    public function endswith()
    {
        $this->expression = $this->key . ' LIKE ?';
        $this->value = "%$this->value";
        return $this;
    }

    public function istartswith()
    {
        $this->lower();
        $this->expression = $this->key . ' LIKE ?';
        $compVal = strtolower($this->value);
        $this->value = "$compVal%";
        return $this;
    }

    public function iendswith()
    {
        $this->lower();
        $this->expression = $this->key . ' LIKE ?';
        $compVal = strtolower($this->value);
        $this->value = "%$compVal";
        return $this;
    }

    public function is_null()
    {
        if ($this->value === true) {
            $this->expression = $this->key . ' IS NULL';
        } else if ($this->value === false) {
            $this->expression = "NOT ($this->key IS NULL)";
        }
        $this->value = null;
        return $this;
    }

    public function in()
    {

        $values = $this->value;

        if (!is_array($values) && !($values instanceof Handler)) {
            throw new \Error(sprintf("'%s' has to be an array or an instance of \Q\Orm\Handler.", $this->key));
        }

        $placeholders = '';

        if (is_array($values)) {
            $count = count($values);
            $placeholders = implode(",", array_fill(0, $count, "?"));
            $this->expression = $this->key . " IN ( $placeholders )";
            $this->value = $values;
        } else if ($values instanceof Handler) {
            $items = $values->buildQuery();
            $query = $items['query'];
            $placeholders = $items['placeholders'];
            $this->expression = $this->key . " IN ( $query )";
            $this->value = $placeholders;
        }

        return $this;
    }

    public function not_in()
    {
        $values = $this->value;

        if (!is_array($values) && !($values instanceof Handler)) {
            throw new \Error(sprintf("'%s' has to be an array or an instance of \Q\Orm\Handler.", $this->key));
        }

        $placeholders = '';


        if (is_array($values)) {
            $count = count($values);
            $placeholders = implode(",", array_fill(0, $count, "?"));
            $this->expression = $this->key . " NOT IN ( $placeholders )";
            $this->value = $values;
        } else if ($values instanceof Handler) {
            $items = $values->buildQuery();
            $query = $items['query'];
            $placeholders = $items['placeholders'];
            $this->expression = $this->key . " NOT IN ( $query )";
            $this->value = $placeholders;
        }

        return $this;
    }



    /* Key changing methods */

    public function lower()
    {
        $this->key = "LOWER($this->key)";
        return $this;
    }

    public function upper()
    {
        $this->key = "UPPER($this->key)";
        return $this;
    }

    public function length()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(LENGTH($this->key) AS $castType)";
        return $this;
    }

    public function trim()
    {
        $this->key = "TRIM($this->key)";
        return $this;
    }

    public function rtrim()
    {
        $this->key = "RTRIM($this->key)";
        return $this;
    }

    public function ltrim()
    {
        $this->key = "LTRIM($this->key)";
        return $this;
    }

    public function date()
    {
        $this->key = Functions::date(Setup::$engine, $this->key);
        return $this;
    }

    public function time()
    {
        $this->key = Functions::time(SetUp::$engine, $this->key);
        return $this;
    }

    public function year()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::year(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }

    public function day()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::day(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }

    public function month()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::month(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }

    public function hour()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::hour(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }

    public function minute()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::minute(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }

    public function second()
    {
        $castType = CastTypes::integer(Setup::$engine);
        $this->key = "CAST(" . Functions::second(Setup::$engine, $this->key) . " AS $castType)";
        return $this;
    }



    /* Extract expression and replacement */
    public function extract()
    {
        if ($this->value !== null) {
            return [$this->expression, $this->value];
        } else {
            return $this->expression;
        }
    }

    public function __get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        }
    }
}
