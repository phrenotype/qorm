<?php

namespace Q\Orm\Traits;

use Q\Orm\Aggregate;
use Q\Orm\Connection;
use Q\Orm\Helpers;
use Q\Orm\QueryStack;

trait CanAggregate
{

    public function aggregate(string $function, string $field)
    {
        $this->__primed_function = $function;
        $this->__primed_field = $field;
        return $this;
    }


    public function buildAggregateQuery()
    {
        if ($this->__count__ == null) {
            $assoc = $this->buildQuery();
            $query = $assoc['query'] ?? '';
            $placeholders = $assoc['placeholders'] ?? [];

            $field = $this->__primed_field;
            $function = $this->__primed_function;

            if (!($field && $function)) {
                throw new \Error(sprintf("Model handler must be primed with the %s::%s method before aggregate query can be built.", Handler::class, 'aggregate'));
            }

            $upperF = strtoupper($function);
            $lowerF = Helpers::ticks(strtolower($function));

            $tmp = Helpers::ticks($this->randomStr());

            $q = "SELECT $upperF($field) AS $lowerF FROM ($query) AS $tmp";
            return [$q, $placeholders];
        }
    }

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


    public function count($field = '*')
    {
        if ($this->__count__ == null) {

            $count = $this->buildAndExecuteAggregateQuery(Aggregate::COUNT, $field);

            $this->__count__ = $count;
            return $this->__count__;
        } else {
            return $this->__count__;
        }
    }

    public function max($field)
    {
        if ($this->__max__ == null) {

            $max = $this->buildAndExecuteAggregateQuery(Aggregate::MAX, $field);

            $this->__max__ = $max;
            return $this->__max__;
        } else {
            return $this->__max__;
        }
    }

    public function min($field)
    {
        if ($this->__min__ == null) {

            $min = $this->buildAndExecuteAggregateQuery(Aggregate::MIN, $field);

            $this->__min__ = $min;
            return $this->__min__;
        } else {
            return $this->__min__;
        }
    }

    public function avg($field)
    {
        if ($this->__avg__ == null) {

            $avg = $this->buildAndExecuteAggregateQuery(Aggregate::AVG, $field);

            $this->__avg__ = $avg;
            return $this->__avg__;
        } else {
            return $this->__avg__;
        }
    }

    public function sum($field)
    {
        if ($this->__sum__ == null) {

            $sum = $this->buildAndExecuteAggregateQuery(Aggregate::SUM, $field);

            $this->__sum__ = $sum;
            return $this->__sum__;
        } else {
            return $this->__sum__;
        }
    }

    public function exists()
    {
        return ($this->count() > 0);
    }
}
