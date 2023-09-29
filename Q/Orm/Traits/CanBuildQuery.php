<?php

namespace Q\Orm\Traits;

use Q\Orm\Helpers;
use Q\Orm\SetUp;

trait CanBuildQuery
{
    public function buildSelect()
    {
        if ($this->__query__ == false) {
            throw new \Error("Cannot build select on an empty query.");
        }
        list($q, $p) = $this->resolveFilters();
        if ($q) {
            $this->__query__ .= $q;
            $this->__placeholders__ = array_merge($this->__placeholders__, $p);
        }

        if (!empty($this->grouped())) {
            $this->__query__ .= ' GROUP BY ' . implode(', ', $this->grouped());
            list($q, $p) = $this->resolveHaving();
            if ($q) {
                $this->__query__ .= $q;
                $this->__placeholders__ = array_merge($this->__placeholders__, $p);
            }
        }

        $this->__query__ .= $this->resolveOrderBy();

        $this->__query__ .= $this->resolveLimit();


        list($jq, $jp) = $this->resolveJoin();
        if ($jq) {
            $this->__query__ .= $jq;
            $this->__placeholders__ = array_merge($this->__placeholders__, $jp);
        }
    }

    public function buildJoin()
    {
        if ($this->__query__ == false) {
            throw new \Error("Cannot build join on empty query.");
        }
        if (!empty($this->__after_join_filters__)) {
            list($q, $p) = $this->resolveFilters(false, true);
            if ($q !== '') {
                $rnd = $this->randomStr();
                $this->__query__ = "SELECT * FROM ($this->__query__) AS $rnd $q";
                $this->__placeholders__ = array_merge($this->__placeholders__, $p);
            }
        }

        if (!empty($this->__after_join_group_by__)) {
            $this->__query__ .= " GROUP BY " . implode(', ', $this->__after_join_group_by__);
            if (!empty($this->__after_join_having__)) {
                list($q, $p) = $this->resolveHaving(false, true);
                if ($q) {
                    $this->__query__ .= $q;
                    $this->__placeholders__ = array_merge($this->__placeholders__, $p);
                }
            }
        }

        if (!empty($this->__after_join_order__)) {
            $this->__query__ .= $this->resolveOrderBy(false, true);
        }

        if (!empty($this->__after_join_limit__)) {
            $this->__query__ .= $this->resolveLimit(false, true);
        }
    }

    public function buildSetOperations()
    {
        if ($this->__query__ == false) {
            throw new \Error("Cannot build set query on an empty query.");
        }
        if (!empty($this->__set_operations__)) {

            if (SetUp::$engine === SetUp::MYSQL) {

                foreach ($this->__set_operations__ as $key => $pair) {
                    $rnd = $this->randomStr();
                    list($setOp, $setH) = $pair;

                    $b = $setH->buildQuery(true);
                    $setQ = $b['query'];
                    $setP = $b['placeholders'];

                    if ($setOp === 'union') {

                        $rnd = Helpers::ticks($rnd);
                        $this->__query__ .= " UNION ($setQ)";
                        $this->__placeholders__ = array_merge($this->__placeholders__, $setP);
                    } else if (in_array($setOp, ['except', 'intersect'])) {

                        $rnd = Helpers::ticks($rnd);
                        $this->__query__ = "SELECT * FROM ($this->__query__) AS $rnd";

                        $projected = explode(", ", $setH->resolveProjectedFields()[0]);

                        $existsSnippet = array_reduce($projected, function ($c, $i) use ($setH, $rnd) {
                            return $c . $rnd . '.' . Helpers::ticks($i) . ' = ' . Helpers::ticks($setH->as()) . '.' . Helpers::ticks($i) . ' AND ';
                        }, '');

                        $existsSnippet = trim($existsSnippet, ' AND ');

                        if ($setOp === 'except') {
                            $this->__query__ .= " WHERE NOT EXISTS";
                        } else if ($setOp === 'intersect') {
                            $this->__query__ .= " WHERE EXISTS";
                        }
                        if (!empty($setH->filtered())) {
                            $this->__query__ .= " (" . $setQ . " AND " . $existsSnippet . ")";
                        } else {
                            $this->__query__ .= " (" . $setQ . " WHERE " . $existsSnippet . ")";
                        }
                        $this->__placeholders__  = array_merge($this->__placeholders__, $setP);
                    } else {
                        throw new \Error(sprintf("Unknown set operation '%s'.", $setOp));
                    }
                }
            } else {
                foreach ($this->__set_operations__ as $pair) {

                    list($setOp, $setH) = $pair;

                    $b = $setH->buildQuery();
                    $setQ = $b['query'];
                    $setP = $b['placeholders'];

                    $this->__query__ .= " " . strtoupper($setOp) . " " . $setQ;
                    $this->__placeholders__ = array_merge($this->__placeholders__, $setP);
                }
            }
        }


        if (!empty($this->__after_set_filters__)) {
            list($q, $p) = $this->resolveFilters(true);
            if ($q !== '') {
                $rnd = $this->randomStr();
                $this->__query__ = "SELECT * FROM ($this->__query__) AS $rnd $q";
                $this->__placeholders__ = array_merge($this->__placeholders__, $p);
            }
        }

        if (!empty($this->__after_set_order__)) {
            $this->__query__ .= $this->resolveOrderBy(true);
        }

        if (!empty($this->__after_set_limit__)) {
            $this->__query__ .= $this->resolveLimit(true);
        }

        if (!empty($this->__after_set_joined__)) {
            list($jq, $jp) = $this->resolveJoin(true);
            if ($q) {
                $this->__query__ .= $jq;
                $this->__placeholders__ = array_merge($this->__placeholders__, $jp);
            }
        }
    }
}
