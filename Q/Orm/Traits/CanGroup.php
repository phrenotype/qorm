<?php

namespace Q\Orm\Traits;

use Q\Orm\Filter;
use Q\Orm\Handler;
use Q\Orm\Helpers;


/**
 * Confers the ability to group results (with having filters) on Handlers and Humans alike.
 */
trait CanGroup
{

    /**
     * Group a Handler by certain fields. This method is variadic
     * 
     * @param mixed ...$fields The fields to group by.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function group_by(...$fields): Handler
    {

        foreach ($fields as $field) {
            if (!preg_match("#^\w+$#", $field)) {
                throw new \Error(sprintf("%s does not match the syntax of a field.", $field));
            }
            if (empty($this->__projected_fields__)) {
                throw new \Error(sprintf("Cannot call 'group by' without projecting fields and aggregates"));
            }


            if (!empty($this->__set_operations__)) {
                $this->__after_set_group_by__[] = Helpers::ticks($field);
            } else if (!empty($this->__joined__)) {
                $this->__after_join_group_by__[] = Helpers::ticks($field);
            } else {
                $this->__group_by__[] = Helpers::ticks($field);
            }
        }
        return $this;
    }

    /**
     * Apply Having filter on a Handler.
     * 
     * @param array $assoc The filters.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function having(array $assoc): Handler
    {
        if (empty($this->__group_by__)) {
            throw new \Error("'group_by' must be called before 'having'.");
        }

        Filter::validateHaving($assoc);

        $newAssoc = [];
        foreach ($assoc as $filter => $value) {
            if (preg_match("/^(\w+)\((\*|\w+)\)(\.\w+)$/i", $filter)) {
                $newKey = preg_replace_callback(
                    "/^(\w+)\((\*|\w+)\)(\.\w+)$/i",
                    function ($groups) {
                        return $groups[1] . '(' . ($groups[2] == '*' ? '*' : Helpers::ticks($groups[2])) . ')' . $groups[3];
                    },
                    $filter
                );
                $newAssoc[$newKey] = $value;
            }
        }


        $joined = !empty($this->__joined__);

        if (!empty($this->__set_operations__)) {
            $this->__after_set_having__[] = Filter::filter($newAssoc);
        } else if (!empty($this->__joined__)) {
            $this->__after_join_having__[] = Filter::filter($newAssoc);
        } else {
            $this->__having__[] = Filter::filter($newAssoc);
        }

        return $this;
    }

    /**
     * Resolves the having filters on a Handler.
     * 
     * @param bool $afterSet
     * @param bool $afterJoin
     * 
     * @return array
     */
    private function resolveHaving($afterSet = false, $afterJoin = false): array
    {
        if ($afterSet) {
            $filters = $this->__after_set_having__;
        } else if ($afterJoin) {
            $filters = $this->__after_join_having__;
        } else {
            $filters = $this->__having__;
        }
        $query = '';
        $placeholders = [];
        if (!empty($filters)) {
            $query .= ' HAVING ';
            foreach ($filters as $filter) {
                $query .= $filter['query'] . ' AND ';
                $placeholders = array_merge($placeholders, $filter['placeholders']);
            }
            $query = rtrim($query, ' AND ');
        }

        return [$query, $placeholders];
    }
}
