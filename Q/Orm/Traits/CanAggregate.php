<?php

namespace Q\Orm\Traits;

use Q\Orm\Aggregate;
use Q\Orm\Connection;
use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\QueryStack;

/**
 * Confers the ability to aggregate on Handlers and Humans alike.
 */
trait CanAggregate
{

    /**
     * Prime a Handler to be used for aggregating.
     *
     * @param string $function The aggregate function to apply.
     * @param string $field The field to call the aggregate function on.
     *
     * @return Handler
     */
    public function aggregate(string $function, string $field): Handler
    {
        if (!preg_match("/[\w*]+/", $field)) {
            throw new \Error("Keywords are not supported in aggregate fieldname.");
        }
        $this->__primed_function = $function;
        $this->__primed_field = $field;
        return $this;
    }


    /**
     * Build aggregate query.
     *
     * @return array
     */
    public function buildAggregateQuery(): array
    {
        $assoc = $this->buildQuery();
        $query = $assoc['query'] ?? '';
        $placeholders = $assoc['placeholders'] ?? [];

        $field = $this->__primed_field;
        $function = $this->__primed_function;

        if (!($field && $function)) {
            throw new \Error(sprintf("Model Handler must be primed with the %s::%s method before aggregate query can be built.", Handler::class, 'aggregate'));
        }

        $upperF = strtoupper($function);
        $lowerF = Helpers::ticks(strtolower($function));

        $tmp = Helpers::ticks($this->randomStr());

        if ($field !== '*') {
            $field = $tmp . '.' . Helpers::ticks($field);
        }


        $q = "SELECT $upperF($field) AS $lowerF FROM ($query) AS $tmp";

        return [$q, $placeholders];
    }

    /**
     * Builds immediately executes an aggregate query.
     *
     * @param string $function
     * @param string $field
     *
     * @return mixed | null
     */
    private function buildAndExecuteAggregateQuery(string $function, string $field)
    {
        $this->aggregate($function, $field);
        list($q, $placeholders) = $this->buildAggregateQuery();
        $statement = Connection::getInstance()->prepare($q);
        $statement->execute($placeholders);
        $f = strtolower($function);
        $value = $statement->fetch(\PDO::FETCH_OBJ)->{$f};
        QueryStack::stack($q, $placeholders);
        return $value ?? null;
    }


    /**
     * Get the total count of objects in a Handler.
     *
     * @param string $field The field to count based on.
     *
     * @return int
     */
    public function count($field = '*'): int
    {
        return (int) $this->buildAndExecuteAggregateQuery(Aggregate::COUNT, $field);
    }

    /**
     * Get the maximum value in a field for all objects in a Handler.
     *
     * @param mixed $field The field to get max on.
     *
     * @return mixed
     */
    public function max($field): mixed
    {
        return $this->buildAndExecuteAggregateQuery(Aggregate::MAX, $field);
    }

    /**
     * Get the miniimum value in a field for all objects in a Handler.
     *
     * @param mixed $field The field to get min on.
     *
     * @return mixed
     */
    public function min($field): mixed
    {
        return $this->buildAndExecuteAggregateQuery(Aggregate::MIN, $field);
    }

    /**
     * Get the average value in a field for all objects in a Handler.
     *
     * @param mixed $field The field to calculate average based on.
     *
     * @return mixed
     */
    public function avg($field): mixed
    {
        return $this->buildAndExecuteAggregateQuery(Aggregate::AVG, $field);
    }

    /**
     * Get the sum of values in a field for all objects in a Handler.
     *
     * @param mixed $field The field to calculate sum based on.
     *
     * @return mixed
     */
    public function sum($field): mixed
    {
        return $this->buildAndExecuteAggregateQuery(Aggregate::SUM, $field);
    }
}
