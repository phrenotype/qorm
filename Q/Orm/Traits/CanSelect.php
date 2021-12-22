<?php

namespace Q\Orm\Traits;

use Q\Orm\Filter;
use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;

/**
 * Confers the ability to perform the select operation on Handlers and Humans alike.
 */
trait CanSelect
{
    /**
     * Order objects in a Handler by fields. This method is variadic.
     * 
     * @param mixed ...$fields The fields to order by.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function order_by(...$fields): Handler
    {
        foreach ($fields as $k => $f) {
            if (!preg_match("#^(\w+|\w+\((\*|\w+)\))(\s+(?:desc|asc))?$#", strtolower($f))) {
                throw new \Error(sprintf("Invalid syntax for order by."));
            }
        }

        foreach ($fields as $index => $field) {
            /* Add backticks to identifiers */

            if (preg_match("/^[\w\s]+$/", $field)) {
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
            } else if (preg_match("/^(\w+)\((\*|\w+)\)/", $field)) {
                $fields[$index] = preg_replace_callback("/^(\w+)\((\*|\w+)\)/", function ($groups) {
                    $func = $groups[1];
                    $param = $groups[2];
                    if ($param !== '*') {
                        $param = Helpers::ticks($param);
                    }
                    return $func . '(' . $param . ')';
                }, $field);
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

    /**
     * Limit the objects that will potentially be fetched from a Handler.
     * 
     * @param int $limit
     * @param int $offset
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function limit(int $limit, int $offset = 0): Handler
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

    /**
     * Fetch a particular page or objects based on offset and limit. TLDR; Use this for pagination.
     * 
     * @param int $page The page number.
     * @param int $ipp Items per page (limit). Default is 20.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function page(int $page, int $ipp = 20): Handler
    {
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $ipp;
        $this->limit($ipp, $offset);
        return $this;
    }

    /**
     * Select only distinct objects from a Handler.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function distinct(): Handler
    {
        $this->__distinct__ = true;
        return $this;
    }

    /**
     * Filter the objects in a Handler based on certain criteria.
     * 
     * @param array $assoc The associative array of query filters.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function filter(array $assoc): Handler
    {
        Filter::validate($assoc);

        $this->__raw_filters__ = array_merge($this->__raw_filters__, $assoc);

        $joined = !empty($this->__joined__);

        $assoc = Filter::deClass($assoc, $this->model(), $joined);

        $alias = null;

        if ($this->__set_operations__) {
            //Only prefix filter fields when dealing with set operations
            //This cannnot apply to joins because the fields 'belong to all'
            //Might modify this if situation or use case arises
            $alias = $this->as();
        }

        $alias = $this->as() ?? null;

        if (!empty($this->__set_operations__)) {
            $this->__after_set_filters__[] = Filter::filter($assoc, $alias);
        } else if (!empty($this->__joined__)) {
            $this->__after_join_filters__[] = Filter::filter($assoc, $alias);
        } else {
            $this->__filters__[] = Filter::filter($assoc, $alias);
        }


        //Reset aggregates
        $this->__count__ = null;
        $this->__avg__ = null;
        $this->__min__ = null;
        $this->__max__ = null;
        $this->__sum__ = null;
        return $this;
    }


    /**
     * Project fields. This method is variadic.
     * 
     * @param mixed ...$fields The fields to project.
     * 
     * @return Handler Returns the handler it was called on.
     */
    public function project(...$fields): Handler
    {

        foreach ($fields as $k => $field) {
            if (
                !preg_match('/^\w+$/', $field) &&
                (!preg_match(Handler::AGGRT_WITH_AS, strtolower($field)) && !preg_match(Handler::PLAIN_ALIASED_FIELD, strtolower($field)))
            ) {
                throw new \Error(sprintf("'%s' is an invalid field projection format", $field));
            }
            if (preg_match("/^\w+$/", $field)) {
                continue;
            } else if (preg_match(Handler::AGGRT_WITH_AS, $field)) {
                $fields[$k] = preg_replace_callback(Handler::AGGRT_WITH_AS, function ($groups) {
                    $func = $groups[1];
                    $fld = $groups[2];
                    $alias = ($groups[4] ?? '');

                    if (trim($fld) !== '*') {
                        $fld = Helpers::ticks($fld);
                    }
                    if ($alias !== '') {
                        $alias = ' AS ' . Helpers::ticks($alias);
                    }

                    return $func . '(' . $fld . ')' . $alias;
                }, $field);
            } else if (preg_match(Handler::PLAIN_ALIASED_FIELD, $field)) {
                $fields[$k] = preg_replace_callback(Handler::PLAIN_ALIASED_FIELD, function ($groups) {
                    $fld = $groups[1];
                    $dot = ($groups[3] ?? '');
                    $alias = ($groups[5] ?? '');
                    if ($dot !== '') {
                        $dot = Helpers::ticks($dot);
                    }
                    if ($alias !== '') {
                        $alias =  Helpers::ticks($alias);
                    } else {
                        $alias  = null;
                    }

                    return Helpers::ticks($fld) . '.' . $dot . ($alias ? ' AS ' . $alias : '');
                }, $field);
            }
        }
        $this->__projected_fields__ = array_merge($this->__projected_fields__, $fields);
        return $this;
    }



    /**
     * @param bool $includePk
     * @param bool $prefixtable
     * 
     * @return array
     */
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
                    $escaper = Helpers::getEscaper();
                    if (!preg_match("|($escaper\w+$escaper\.$escaper\w+$escaper)(\s+as\s+$escaper\w+$escaper)?|i", $p)) {
                        throw new \Error(sprintf("Projected fields in joins must always be prefixed with Handler alias. Prefix '%s' with an alias.", $p));
                    }
                }

                $inCols = in_array($p, $cols);
                $inProps = in_array($p, $props);

                $doesNotExist = !$inCols && !$inProps;
                $onlyCols = $inCols && !$inProps;
                $onlyProps = $inProps && !$inCols;
                $inBoth = $inProps && $inCols;

                //Making an exception for join fields with alias
                if (preg_match(Handler::PLAIN_ALIASED_FIELD, strtolower($p))) {
                    $newProjected[] = $p;
                    continue;
                }

                //Making an exception for group by
                if (!empty($this->__group_by__) && preg_match(Handler::AGGRT_WITH_AS_AND_TICKS, strtolower($p))) {
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

        $pk = TableModelFinder::findModelPk($this->__model__);

        $cols = Helpers::getModelColumns($this->__model__);

        $defaultFields = $cols;
        if (!in_array($pk, $cols) && $includePk) {
            $defaultFields = array_merge([$pk], $defaultFields);
        }

        if (empty($newProjected)) {
            $newProjected = $defaultFields;
        }


        $ticked = array_map(function ($c) {

            if (strpos(strtolower($c), ' as ') === false || preg_match(Handler::AGGRT_WITH_AS_AND_TICKS, $c)) {
                return $c;
            } else {
                return Helpers::ticks($c);
            }
        }, $newProjected);

        if ($prefixtable) {
            $tablenameAppended = array_map(function ($c) {
                if (preg_match(Handler::AGGRT_WITH_AS_AND_TICKS, $c)) {
                    return $c;
                } else if (strpos($c, '.') === false) {
                    if ($this->as()) {
                        $tn = Helpers::ticks($this->as());
                    } else {
                        $tn = Helpers::ticks($this->tablename());
                    }
                    return $tn . '.' . Helpers::ticks($c);
                } else {
                    return $c;
                }
            }, $ticked);
        } else {
            $tablenameAppended = $ticked;
        }


        $projected = join(', ', $tablenameAppended);
        return [$projected, $defered];
    }

    /**
     * @param bool $afterSet
     * @param bool $afterJoin
     * 
     * @return array
     */
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

        return [$query, $placeholders];
    }

    /**
     * @param bool $afterSet
     * @param bool $afterJoin
     * 
     * @return string
     */
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

    /**
     * @param bool $afterSet
     * @param bool $afterJoin
     * 
     * @return string
     */
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
}
