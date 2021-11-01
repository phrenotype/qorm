<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Traits\CanAggregate;
use Q\Orm\Traits\CanBeASet;
use Q\Orm\Traits\CanCrud;
use Q\Orm\Traits\CanJoin;
use Q\Orm\Traits\CanRace;

class Handler
{

    use CanCrud, CanAggregate, CanRace, CanJoin, CanBeASet;

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

    public function order_by(...$fields)
    {
        foreach ($fields as $f) {
            if (!preg_match("#^\w+(\s+(?:desc|asc))?$#", strtolower($f))) {
                throw new \Error(sprintf("Invalid syntax for order by."));
            }
        }

        foreach ($fields as $index => $field) {
            /* Add backticks to identifiers */
            $field = str_replace(',', '', $field);

            $items = explode(' ', $field);

            if (!empty($items)) {
                foreach ($items as $i => $v) {
                    /* Ignore asc, desc or anything that ends with ')', most likely a function */
                    if (!in_array(strtolower($v), ['desc', 'asc']) && strpos($v, ')') !== mb_strlen($v) - 1) {
                        $v = trim($v);
                        $fields[$index] = str_replace($v, Helpers::ticks($v), $fields[$index]);
                    }
                }
            }
        }

        if (!empty($this->__set_operations__)) {
            $this->__after_set_order__ = array_merge($this->__after_set_order__, $fields);
        } else if (!empty($this->__joined__)) {
            $this->__after_join_order__ = array_merge($this->__after_join_order__, $fields);
        } else {
            $this->__order_by__ = array_merge($this->__order_by__, $fields);
        }

        return $this;
    }

    public function limit(int $limit, int $offset = 0)
    {
        if (!empty($this->__set_operations__)) {
            $this->__after_set_limit__ = [$limit, $offset];
        } else if (!empty($this->__joined__)) {
            $this->__after_join_limit__ = [$limit, $offset];
        } else {
            $this->__limit__ = [$limit, $offset];
        }
        return $this;
    }

    public function page(int $page, int $ipp = 20)
    {
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $ipp;
        $this->limit($ipp, $offset);
        return $this;
    }

    public function distinct()
    {
        $this->__distinct__ = true;
    }

    public function filter(array $assoc)
    {
        Filter::validate($assoc);

        $this->__raw_filters__ = array_merge($this->__raw_filters__, $assoc);

        $joined = !empty($this->__joined__);

        $assoc = Filter::objectify($assoc, $this->model(), $joined);

        if (!empty($this->__set_operations__)) {
            $this->__after_set_filters__[] = Filter::filter($assoc);
        } else if (!empty($this->__joined__)) {
            $this->__after_join_filters__[] = Filter::filter($assoc);
        } else {
            $this->__filters__[] = Filter::filter($assoc);
        }


        //Reset aggregates
        $this->__count__ = null;
        $this->__avg__ = null;
        $this->__min__ = null;
        $this->__max__ = null;
        $this->__sum__ = null;
        return $this;
    }

    public function project(...$fields)
    {
        $this->__projected_fields__ = array_merge($this->__projected_fields__, $fields);
        return $this;
    }


    public function resolveProjectedFields($includePk = true, $prefixtable = false): array
    {

        $projected = $this->__projected_fields__ ?? [];
        $newProjected = [];
        $defered = [];

        if (!empty($projected)) {

            $cols = Helpers::getModelColumns($this->__model__);
            $props = Helpers::getModelProperties($this->__model__);

            foreach ($projected as $p) {

                /* If we are in a join and it does not follow a pattern, throw error */
                if (!empty($this->__joined__)) {
                    if (!preg_match('|(\w+\.\w+)(\s+as\s+\w+)?|i', $p)) {
                        throw new \Error(sprintf("Projected fields in joins must always be prefixed with Handler alias. Prefix '%s' with an alias.", $p));
                    }
                }

                $inCols = in_array($p, $cols);
                $inProps = in_array($p, $props);

                $doesNotExist = !$inCols && !$inProps;
                $onlyCols = $inCols && !$inProps;
                $onlyProps = $inProps && !$inCols;
                $inBoth = $inProps && $inCols;

                if (preg_match("|(\w+)((?:\.)(\w+))? as (\w+)|i", $p)) {
                    //$l = preg_replace("|(\w+)((?<=\.)\w+) as (\w+)|i", "`\\1`.`\\3` AS `\\4`", $p);                    
                    $newProjected[] = $p;
                    continue;
                }

                //So that id may or may not come back
                if ($p === 'id') {
                    $newProjected[] = $p;
                    continue;
                }

                if ($inBoth) {
                    //Either it's a scalar field or it was manually renamed by the user.
                    $newProjected[] = $p;
                } else if ($onlyProps) {
                    //It's only in the properties. Probably an fk
                    //However, still project

                    //Figure out the real name
                    $realName = TableModelFinder::findModelColumnName($this->__model__, $p);
                    if ($realName) {
                        $newProjected[] = $realName;
                    }
                    $defered[] = $p;
                } else if (strpos($p, '.') !== false) {
                    $e = explode('.', $p);
                    $p = implode('.', array_map(function ($i) {
                        return Helpers::ticks($i);
                    }, $e));
                    $newProjected[] = $p;
                } else {

                    throw new \Error(sprintf("Uknown field %s.%s", $this->model(), $p));
                }
            }
        }

        $pk = TableModelFinder::findPk($this->__model__);

        $cols = Helpers::getModelColumns($this->__model__);

        $defaultFields = $cols;
        if (!in_array($pk, $cols) && $includePk) {
            $defaultFields = array_merge([$pk], $defaultFields);
        }

        if (empty($newProjected)) {
            $newProjected = $defaultFields;
        } else {
            /*
            if (!in_array($pk, $newProjected) && $includePk) {
                $newProjected = array_merge([$pk], $newProjected);
            }
            */
        }


        $ticked = array_map(function ($c) {

            if (strpos(strtolower($c), ' as ') !== false) {
                return $c;
            } else {
                return Helpers::ticks($c);
            }
        }, $newProjected);

        if ($prefixtable) {
            $tablenameAppended = array_map(function ($c) {
                if (strpos($c, '.') === false) {
                    return Helpers::ticks($this->tablename()) . '.' . $c;
                } else {
                    return $c;
                }
            }, $ticked);
        } else {
            $tablenameAppended = $ticked;
        }


        $projected = join(',', $tablenameAppended);
        return [$projected, $defered];
    }

    private function resolveFilters($afterSet = false, $afterJoin = false): array
    {
        if ($afterSet) {
            $filters = $this->__after_set_filters__;
        } else if ($afterJoin) {
            $filters = $this->__after_join_filters__;
        } else {
            $filters = $this->__filters__;
        }
        $query = '';
        $placeholders = [];
        if (!empty($filters)) {
            $query .= ' WHERE ';
            foreach ($filters as $filter) {
                $query .= $filter['query'] . ' AND ';
                $placeholders = array_merge($placeholders, $filter['placeholders']);
            }
            $query = rtrim($query, ' AND ');
        }



        /*
        $holders = [];

        foreach ($this->__set_operations__ as $pair) {
            $thisPk = TableModelFinder::findPk($this->model());
            list($op, $h) = $pair;
            if ($op === 'union') {
                continue;
            }
            $hPk = TableModelFinder::findPk($h->model());
            $h->project($hPk);

            $holders = $h->buildQuery(true)['placeholders'];

            if ($query == '') {
                $query .= ' WHERE ';
            } else {
                $query .= ' AND ';
            }

            if ($op === 'except') {
                $query .= $thisPk . " NOT IN (" . $h->buildQuery()['query'] . ')';
            } else if ($op === 'intersect') {
                $query .= $thisPk . ' IN (' . $h->buildQuery()['query'] . ')';
            }

            $placeholders = array_merge($placeholders, $holders);
        }

        */


        return [$query, $placeholders];
    }

    private function resolveJoin($afterSet = false)
    {
        if ($afterSet) {
            $joined = $this->__after_set_joined__;
        } else {
            $joined = $this->__joined__;
        }


        $join = '';
        $placeholders = [];

        if ($joined) {
            foreach ($joined as $j) {
                list($handler, $field, $ref, $type) = $j;
                $join .= sprintf(
                    " $type %s ON %s.%s = %s.%s",
                    $handler->tablenameWithAlias(),
                    Helpers::ticks($handler->as()),
                    Helpers::ticks($field),
                    Helpers::ticks($this->as()),
                    Helpers::ticks($ref)
                );
            }
        }
        return [$join, $placeholders];
    }

    private function resolveSet()
    {
        $ops = $this->__set_operations__;

        $set = '';
        $setPlc = [];
        if ($ops) {
            foreach ($ops as $items) {
                list($op, $h) = $items;
                $q = $h->buildQuery();
                $set .= strtoupper($op) . ' ' . $q['query'] . ' ';
                $setPlc = array_merge($setPlc, $q['placeholders']);
            }
        }
        return [trim($set), $setPlc];
    }

    private function resolveOrderBy($afterSet = false, $afterJoin = false): string
    {
        $order = $this->__order_by__;
        if ($afterSet) {
            $order = $this->__after_set_order__;
        } else if ($afterJoin) {
            $order = $this->__after_join_order__;
        }
        if (!empty($order)) {
            return ' ORDER BY ' . join(', ', $order);
        }
        return '';
    }

    private function resolveLimit($afterSet = false, $afterJoin = false): string
    {
        $limit = $this->__limit__;
        if ($afterSet) {
            $limit = $this->__after_set_limit__;
        } else if ($afterJoin) {
            $limit = $this->__after_join_limit__;
        }
        if (!empty($limit)) {
            if (count($limit) === 1) {
                return ' LIMIT ' . $limit[1] . ', ' . $limit[0];
            } else if (count($limit) === 2) {
                return ' LIMIT ' . $limit[1] . ', ' . $limit[0];
            }
        }
        return '';
    }

    public function buildQuery()
    {

        list($projected, $defered) = $this->resolveProjectedFields(true);

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


        /**
         * Resolving filters
         */
        list($q, $p) = $this->resolveFilters();
        if ($q) {
            $query .= $q;
            $placeholders = array_merge($placeholders, $p);
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

        if (!empty($this->__after_join_order__)) {
            $query .= $this->resolveOrderBy(false, true);
        }

        if (!empty($this->__after_join_limit__)) {
            $query .= $this->resolveLimit(false, true);
        }





        /* END OF BUILDING NORMAL QUERY */


        list($setq, $setp) = $this->resolveSet();
        if ($setq) {
            //$query  = "($query)";
            $query .= ' ' . $setq;
            $placeholders = array_merge($placeholders, $setp);
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
