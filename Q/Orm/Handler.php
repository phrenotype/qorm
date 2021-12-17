<?php

namespace Q\Orm;

use Q\Orm\Traits\CanAggregate;
use Q\Orm\Traits\CanBeASet;
use Q\Orm\Traits\CanCud;
use Q\Orm\Traits\CanGroup;
use Q\Orm\Traits\CanJoin;
use Q\Orm\Traits\CanRace;
use Q\Orm\Traits\CanSelect;

/**
 * This is the class at the center of all querying. Think of it as the database table.  
 */
class Handler
{

    use CanSelect, CanCud, CanAggregate, CanRace, CanJoin, CanBeASet, CanGroup;

    const AGGRT_WITH_AS = '/^(\w+)\((\*|\w+)\)(\s*AS\s*(\w+))$/i';
    const AGGRT_WITH_AS_AND_TICKS = '/^(\w+)\((\*|("|`)\w+\3)\)(\s*AS\s*(\3\w+\3))$/i';
    const PLAIN_ALIASED_FIELD = "/(\w+)((?:\.)(\w+))?(\s*AS\s*(\w+))?/i";

    /**
     * @var string Table name of the current Handler.
     */
    private $__table_name__ = '';

    /**
     * @var string Model bound to Handler.
     */
    private $__model__;

    /**
     * @var array Filters to be applied.
     */
    private $__filters__ = [];

    /**
     * @var array Filters to be applied after set operations.
     */
    private $__after_set_filters__ = [];

    /**
     * @var array Filters to be applied after join operations.
     */
    private $__after_join_filters__ = [];

    /**
     * @var array Raw, unprocessed filters passed by consumer.
     */
    private $__raw_filters__ =  [];

    /**
     * @var array Order by clause to be applied.
     */
    private $__order_by__ = [];

    /**
     * @var array Order by clause to be applied after set operations.
     */
    private $__after_set_order__ = [];

    /**
     * @var array Order by clause to be applied after join operations.
     */
    private $__after_join_order__ = [];


    /**
     * @var array Limit clause to be applied.
     */
    private $__limit__ = [];

    /**
     * @var array Limit clause to be applied after set operations.
     */
    private $__after_set_limit__ = [];

    /**
     * @var array Limit clause to be applied after join operations.
     */
    private $__after_join_limit__ = [];


    /**
     * @var array Holds an array of fields to be projected.
     */
    private $__projected_fields__ = [];

    /**
     * @var string Holds the table alias for a Handler.
     */
    private $__table_alias__;

    /**
     * @var bool Whether 'select distinct' should be used when projecting fields.
     */
    private $__distinct__ = false;

    /**
     * @var array Group by clause to be applied.
     */

    private $__group_by__ = [];
    /**
     * @var array Group by clause to be applied after join operations.
     */

    private $__after_join_group_by__ = [];
    /**
     * @var array Group by clause to be applied after set operations.
     */
    private $__after_set_group_by__ = [];


    /**
     * @var array Having filters to be applied.
     */
    private $__having__ = [];

    /**
     * @var array Having filters to be applied after a join operations.
     */
    private $__after_join_having__ = [];

    /**
     * @var array Having filters to be applied after a set operations.
     */
    private $__after_set_having__ = [];


    /**
     * @var int Cache for aggregate COUNT.
     */
    private $__count__;

    /**
     * @var mixed Cache for aggregate MIN.
     */
    private $__min__;

    /**
     * @var mixed Cache for aggregate MAX.
     */
    private $__max__;

    /**
     * @var int|float Cache for aggregate SUM.
     */
    private $__sum__;

    /**
     * @var int|float Cache for aggregate AVG.
     */
    private $__avg__;


    /**
     * @var string Aggregate function to call on Handler.
     */
    private $__primed_function;

    /**
     * @var string Field to call aggregate function on.
     */
    private $__primed_field;



    /**
     * @var array An array of Handlers to be joined.
     */
    private $__joined__ = [];

    /**
     * @var array Joined Handlers after set operations.
     */
    private $__after_set_joined__ = [];



    /**
     * @var array Set operations to be performed.
     */
    private $__set_operations__ = [];


    /**
     * The constructor. It binds to a Handler to a model class.
     * 
     * @param string $model The fully qualified classname of the model to bind to.
     */
    public function __construct(string $model)
    {
        $this->__model__ = $model;
        $this->__table_name__ = Helpers::modelNameToTableName(Helpers::getShortName($model));
    }

    /**
     * Get the tablename of the model bound to a Handler as it is in the database.
     * 
     * @return string Returns tablename.
     */
    public function tablename(): string
    {
        return $this->__table_name__;
    }

    /**
     * Get the tablename with alias, of the model bound to a Handler as it is in the database.
     * 
     * @param bool $addTicks Add ticks or escaping if database dialect supports it.
     * @return string Returns tablename.
     */
    public function tablenameWithAlias($addTicks = true): string
    {
        if ($this->as()) {
            if ($addTicks) {
                return Helpers::ticks($this->tablename()) . ' AS ' . Helpers::ticks($this->as());
            } else {
                return $this->tablename() . ' AS ' . $this->as();
            }
        }
    }

    private function randomStr()
    {
        return preg_replace("|[^a-zA-Z]|", "", bin2hex(random_bytes(12)));
    }

    /**
     * Get the model class bound to a Handler.
     * 
     * @return string Returns than model class.
     */
    public function model(): string
    {
        return $this->__model__;
    }


    /**
     * Get the user projected fields.
     * @return array
     */
    public function projected(): array
    {
        return $this->__projected_fields__;
    }

    /**
     * Get the processed filters applied to this Handler.
     * 
     * @return array
     */
    public function filtered(): array
    {
        return $this->__filters__;
    }


    /**
     * Generates a query based on the Handler it's called on.
     * 
     * @param bool $prefixTable Determines if fieldnames are prefixed with tablename. Default is false.
     * 
     * @return array Returns an associative array with keys 'query', 'placeholders', and 'projected'.
     */
    public function buildQuery($prefixTable = false): array
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

                        $rnd = Helpers::ticks($rnd);
                        $query .= " UNION ($setQ)";
                        $placeholders = array_merge($placeholders, $setP);
                    } else if (in_array($setOp, ['except', 'intersect'])) {

                        $rnd = Helpers::ticks($rnd);
                        $query = "SELECT * FROM ($query) AS $rnd";

                        $projected = explode(", ", $setH->resolveProjectedFields()[0]);

                        $existsSnippet = array_reduce($projected, function ($c, $i) use ($setH, $rnd) {
                            return $c . $rnd . '.' . Helpers::ticks($i) . ' = ' . Helpers::ticks($setH->as()) . '.' . Helpers::ticks($i) . ' AND ';
                        }, '');

                        $existsSnippet = trim($existsSnippet, ' AND ');

                        if ($setOp === 'except') {
                            $query .= " WHERE NOT EXISTS";
                        } else if ($setOp === 'intersect') {
                            $query .= " WHERE EXISTS";
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

    /**
     * Check if a Handler has any objects (rows) that fit it's criteria.
     * 
     * @return bool Returns true if there is at least one object (row) or false if there is none.
     */
    public function exists(): bool
    {
        return ($this->count() > 0);
    }

    /**
     * Get the first object in a Handler.
     * 
     * @return Q\Orm\Model | null
     */
    public function one()
    {
        $assoc = $this->buildQuery();
        $query = $assoc['query'] ?? '';
        $placeholders = $assoc['placeholders'] ?? [];
        $project = $assoc['project'];


        $fromQuerier = Querier::queryOne($query, $placeholders, $this->__model__, $project);

        return $fromQuerier;
    }


    /**
     * Get all the objects in a Handler as a Generator.
     * 
     * @return \Generator
     */
    public function all(): \Generator
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

    /**
     * Get all the objects in a Handler as an array.
     * 
     * @return array
     */
    public function array(): array
    {
        return iterator_to_array($this->all());
    }

    /**
     * Apply a function to every object in a Handler, usually with the aim of modification.
     * 
     * @param callable $f A callable that takes a model as argument.
     * 
     * @return \Generator
     */
    public function map(callable $f)
    {
        $all = $this->all();
        foreach ($all as $obj) {
            yield $f($obj);
        }
    }


    /**
     * Filter a Handler using a callable. If it returns true, the object is kept. If it returns false, the object is removed.
     * 
     * @param callable $f A callable that takes a model as argument.
     * 
     * @return \Generator
     */
    public function pick(callable $f): \Generator
    {
        $all = $this->all();
        foreach ($all as $obj) {
            if ($f($obj) === true) {
                yield $obj;
            }
        }
    }


    /**
     * Run raw sql queries (select, insert, update or delete) on a Handler.
     * 
     * @param string $query The raw query to run. Should be one of select, insert, update, or delete.
     * @param array $placeholders An array of placeholders, if any.
     * 
     * @return Generator|bool Returns a Generator for select queries and a bool for others.
     */
    public function raw(string $query, array $placeholders = [])
    {
        if (strpos(strtolower($query), 'select') === 0) {
            return (Querier::queryAll($query, $placeholders, $this->__model__, []))();
        } else if (preg_match('#^(insert|update|delete)#', strtolower($query))) {
            Querier::raw($query, $placeholders);
            return true;
        } else {
            throw new \Error(sprintf('Only SELECT, CREATE, INSERT and UPDATE queries are allowed via %s::raw().', self::class));
        }
    }
}
