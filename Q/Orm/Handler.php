<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Traits\CanAggregate;
use Q\Orm\Traits\CanBeASet;
use Q\Orm\Traits\CanCrud;
use Q\Orm\Traits\CanGroup;
use Q\Orm\Traits\CanJoin;
use Q\Orm\Traits\CanRace;
use Q\Orm\Traits\CanSelect;

class Handler
{

    use CanSelect, CanCrud, CanAggregate, CanRace, CanJoin, CanBeASet, CanGroup;

    const AGGRT_WITH_AS = '/^(\w+)\((\*|\w+)\)(\s*AS\s*(\w+))$/i';
    const AGGRT_WITH_AS_AND_TICKS = '/^(\w+)\((\*|`\w+`)\)(\s*AS\s*(`\w+`))$/i';
    const PLAIN_ALIASED_FIELD = "/(\w+)((?:\.)(\w+))?(\s*AS\s*(\w+))?/i";

    private $__table_name__ = '';
    private $__model__;

    private $__filters__ = [];
    private $__after_set_filters__ = [];
    private $__after_join_filters__ = [];

    private $__raw_filters__ =  [];

    private $__order_by__ = [];
    private $__after_set_order__ = [];
    private $__after_join_order__ = [];

    private $__limit__ = [];
    private $__after_set_limit__ = [];
    private $__after_join_limit__ = [];

    private $__projected_fields__ = [];

    private $__table_alias__;

    private $__distinct__ = false;

    /**
     * Whether this handler it to be grouped
     */
    private $__group_by__ = [];
    private $__after_join_group_by__ = [];
    private $__after_set_group_by__ = [];

    private $__having__ = [];
    private $__after_join_having__ = [];
    private $__after_set_having__ = [];

    /**
     * Aggregate holders
     */
    private $__count__;
    private $__min__;
    private $__max__;
    private $__sum__;
    private $__avg__;

    /**
     *  For priming aggregates
     */
    private $__primed_function;
    private $__primed_field;


    /**
     * For join support
     */
    private $__joined__ = [];
    private $__after_set_joined__ = [];

    /**
     * For set support
     */
    private $__set_operations__ = [];


    public function __construct($model)
    {
        $this->__model__ = $model;
        $this->__table_name__ = Helpers::modelNameToTableName(Helpers::getShortName($model));
    }

    public function tablename()
    {
        return $this->__table_name__;
    }

    public function tablenameWithAlias()
    {
        if ($this->as()) {
            return Helpers::ticks($this->tablename()) . ' AS ' . Helpers::ticks($this->as());
        }
    }

    private function randomStr()
    {
        return preg_replace("|[^a-zA-Z]|", "", bin2hex(random_bytes(12)));
    }

    public function model()
    {
        return $this->__model__;
    }

    public function projected()
    {
        return $this->__projected_fields__;
    }

    public function filtered()
    {
        return $this->__filters__;
    }

    public function whackyFiltered($key)
    {
        if ($this->filtered()) {
            return true;
        } else if (SetUp::$engine === SetUp::MYSQL) {
            if ($this->__set_operations__ && count($this->__set_operations__) > 1) {
                if ($key > 0) {
                    return true;
                }
            }
        }
    }

    public function buildQuery($prefixTable = false)
    {

        list($projected, $defered) = $this->resolveProjectedFields(true, $prefixTable);
        if (!empty($this->__set_operations__) && SetUp::$engine === SetUp::MYSQL) {
            list($projected, $defered) = $this->resolveProjectedFields(true, true);
        }

        $tablename = Helpers::ticks($this->tablename());
        if ($this->as()) {
            $tablename = $this->tablenameWithAlias();
        }

        if ($this->__distinct__ || !empty($this->__set_operations__)) {
            $query = 'SELECT DISTINCT ' . $projected . ' FROM ' . $tablename;
        } else {
            $query = 'SELECT ' . $projected . ' FROM ' . $tablename;
        }


        $placeholders = [];


        list($q, $p) = $this->resolveFilters();
        if ($q) {
            $query .= $q;
            $placeholders = array_merge($placeholders, $p);
        }

        if (!empty($this->__group_by__)) {
            $query .= ' GROUP BY ' . implode(', ', $this->__group_by__);
            list($q, $p) = $this->resolveHaving();
            if ($q) {
                $query .= $q;
                $placeholders = array_merge($placeholders, $p);
            }
        }

        $query .= $this->resolveOrderBy();

        $query .= $this->resolveLimit();


        list($jq, $jp) = $this->resolveJoin();
        if ($jq) {
            $query .= $jq;
            $placeholders = array_merge($placeholders, $jp);
        }


        if (!empty($this->__after_join_filters__)) {
            list($q, $p) = $this->resolveFilters(false, true);
            if ($q !== '') {
                $rnd = $this->randomStr();
                $query = "SELECT * FROM ($query) AS $rnd $q";
                $placeholders = array_merge($placeholders, $p);
            }
        }

        if (!empty($this->__after_join_group_by__)) {
            $query .= " GROUP BY " . implode(', ', $this->__after_join_group_by__);
            if (!empty($this->__after_join_having__)) {
                list($q, $p) = $this->resolveHaving(false, true);
                if ($q) {
                    $query .= $q;
                    $placeholders = array_merge($placeholders, $p);
                }
            }
        }

        if (!empty($this->__after_join_order__)) {
            $query .= $this->resolveOrderBy(false, true);
        }

        if (!empty($this->__after_join_limit__)) {
            $query .= $this->resolveLimit(false, true);
        }



        /* END OF BUILDING NORMAL QUERY */

        if (!empty($this->__set_operations__)) {

            if (SetUp::$engine === SetUp::MYSQL) {

                foreach ($this->__set_operations__ as $key => $pair) {
                    $rnd = $this->randomStr();
                    list($setOp, $setH) = $pair;

                    $b = $setH->buildQuery(true);
                    $setQ = $b['query'];
                    $setP = $b['placeholders'];

                    if ($setOp === 'union') {
                        //$unionSnippet = '';
                        //foreach ($setH->filtered() as $fpair) {
                        //$unionSnippet .= ' ' . $fpair['query'];
                        //$placeholders = array_merge($placeholders, $fpair['placeholders']);
                        //}
                        //$unionSnippet = trim($unionSnippet);
                        //$setAlias = $setH->as();
                        //$unionSnippet = preg_replace("/$setAlias/", $this->as(), $unionSnippet);

                        //$unionSnippet = preg_replace("/$setAlias/", $rnd, $unionSnippet);

                        /*
                        if ($this->whackyFiltered($key)) {
                            $query .= ' OR (' . $unionSnippet . ')';
                        } else {
                            $query .= ' WHERE (1 OR (' . $unionSnippet . '))';
                        }
                        */

                        $rnd = Helpers::ticks($rnd);
                        $query .= " UNION ($setQ)";
                        $placeholders = array_merge($placeholders, $setP);
                    } else if (in_array($setOp, ['except', 'intersect'])) {

                        //$query = $query; //"SELECT * FROM ($query)";
                        $rnd = Helpers::ticks($rnd);
                        $query = "SELECT * FROM ($query) AS $rnd";

                        $projected = explode(", ", $setH->resolveProjectedFields()[0]);

                        // $existsSnippet = array_reduce($projected, function ($c, $i) use ($setH) {
                        //     return $c . Helpers::ticks($this->as()) . '.' . Helpers::ticks($i) . ' = ' . Helpers::ticks($setH->as()) . '.' . Helpers::ticks($i) . ' AND ';
                        // }, '');

                        $existsSnippet = array_reduce($projected, function ($c, $i) use ($setH, $rnd) {
                            return $c . $rnd . '.' . Helpers::ticks($i) . ' = ' . Helpers::ticks($setH->as()) . '.' . Helpers::ticks($i) . ' AND ';
                        }, '');

                        $existsSnippet = trim($existsSnippet, ' AND ');

                        if ($setOp === 'except') {
                            $query .= " WHERE NOT EXISTS";
                            /*
                            if (!$this->whackyFiltered($key)) {
                                $query .= " WHERE NOT EXISTS";
                            } else {
                                $query .= " AND NOT EXISTS";
                            }
                            */
                        } else if ($setOp === 'intersect') {
                            $query .= " WHERE EXISTS";
                            /*
                            if (!$this->whackyFiltered($key)) {
                                $query .= " WHERE EXISTS";
                            } else {
                                $query .= " AND EXISTS";
                            }
                            */
                        }
                        if (!empty($setH->filtered())) {
                            $query .= " (" . $setQ . " AND " . $existsSnippet . ")";
                        } else {
                            $query .= " (" . $setQ . " WHERE " . $existsSnippet . ")";
                        }
                        $placeholders  = array_merge($placeholders, $setP);
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

                    $query .= " " . strtoupper($setOp) . " " . $setQ;
                    $placeholders = array_merge($placeholders, $setP);
                }
            }
        }


        if (!empty($this->__after_set_filters__)) {
            list($q, $p) = $this->resolveFilters(true);
            if ($q !== '') {
                $rnd = $this->randomStr();
                $query = "SELECT * FROM ($query) AS $rnd $q";
                $placeholders = array_merge($placeholders, $p);
            }
        }

        if (!empty($this->__after_set_order__)) {
            $query .= $this->resolveOrderBy(true);
        }

        if (!empty($this->__after_set_limit__)) {
            $query .= $this->resolveLimit(true);
        }

        if (!empty($this->__after_set_joined__)) {
            list($jq, $jp) = $this->resolveJoin(true);
            if ($q) {
                $query .= $jq;
                $placeholders = array_merge($placeholders, $jp);
            }
        }


        return ['query' => $query, 'placeholders' => $placeholders ?? [], 'project' => $defered];
    }


    public function one()
    {
        $assoc = $this->buildQuery();
        $query = $assoc['query'] ?? '';
        $placeholders = $assoc['placeholders'] ?? [];
        $project = $assoc['project'];


        $fromQuerier = Querier::queryOne($query, $placeholders, $this->__model__, $project);

        return $fromQuerier;
    }


    public function all()
    {
        $assoc = $this->buildQuery();
        $query = $assoc['query'] ?? '';
        $placeholders = $assoc['placeholders'] ?? [];
        $project = $assoc['project'];

        $fromQuerier = Querier::queryAll($query, $placeholders, $this->__model__, $project);

        foreach ($fromQuerier() as $obj) {
            yield $obj;
        }
    }

    public function array()
    {
        return iterator_to_array($this->all());
    }

    public function map(callable $f)
    {
        $all = $this->all();
        foreach ($all as $obj) {
            yield $f($obj);
        }
    }

    public function pick(callable $f)
    {
        $all = $this->all();
        foreach ($all as $obj) {
            if ($f($obj) === true) {
                yield $obj;
            }
        }
    }

    public function raw($query, array $params = [])
    {
        if (strpos(strtolower($query), 'select') === 0) {
            return (Querier::queryAll($query, $params, $this->__model__, []))();
        } else if (preg_match('#^(create|insert|update)#', strtolower($query))) {
            Querier::raw($query, $params);
            return true;
        } else {
            throw new \Error(sprintf('Only SELECT, CREATE, INSERT and UPDATE queries are allowed via %s::raw().', self::class));
        }
    }
}
