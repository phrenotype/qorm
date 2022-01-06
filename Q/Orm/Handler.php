<?php

namespace Q\Orm;

use Q\Orm\Traits\CanAggregate;
use Q\Orm\Traits\CanBeASet;
use Q\Orm\Traits\CanBuildQuery;
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

    use CanSelect, CanCud, CanAggregate, CanRace, CanJoin, CanBeASet, CanGroup, CanBuildQuery;

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
     * @var string|null
     */
    private $__query__;

    /**
     * @var array
     */
    private $__placeholders__ = [];

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

    public function randomStr()
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
     * Get the fields registered for grouping.
     * 
     * @return array
     */
    public function grouped(): array
    {
        return $this->__group_by__;
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

        //Reset these two. I know, resetting __query__ is not neccesary since it's basically reset.
        $this->__query__ = '';
        $this->__placeholders__ = [];

        if (!empty($this->__set_operations__) && SetUp::$engine === SetUp::MYSQL) {
            list($projected, $defered) = $this->resolveProjectedFields(true, true);
        } else {
            list($projected, $defered) = $this->resolveProjectedFields(true, $prefixTable);
        }

        $tablename = ($this->as()) ? $this->tablenameWithAlias() : Helpers::ticks($this->tablename());

        if ($this->__distinct__ || !empty($this->__set_operations__)) {
            $this->__query__ = 'SELECT DISTINCT ' . $projected . ' FROM ' . $tablename;
        } else {
            $this->__query__ = 'SELECT ' . $projected . ' FROM ' . $tablename;
        }

        $this->buildSelect();

        $this->buildJoin();

        $this->buildSetOperations();

        return ['query' => $this->__query__, 'placeholders' => $this->__placeholders__ ?? [], 'project' => $defered];
    }

    public function query()
    {
        return $this->__query__;
    }

    public function placeholders()
    {
        return $this->__placeholders__;
    }

    /**
     * Check if a Handler has any objects (rows) that fit it's criteria.
     * 
     * @return bool Returns true if there is at least one object (row) or false if there is none.
     */
    public function exists(): bool
    {
        return ((int)$this->count() > 0);
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
