<?php

namespace Q\Orm\Traits;

use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;


/**
 * Confers the ability to perform Joins on Handlers and Humans alike.
 */
trait CanJoin
{
    private function j(Handler $modelHandler, string $fieldname, string $reffieldname, string $type): Handler
    {
        if ($this === $modelHandler) {
            throw new \Error("Do not store base handler in variables when doing joins.");
        }
        if (!(Helpers::isRefField($fieldname, $modelHandler->model()) || !in_array($fieldname, Helpers::getModelProperties($modelHandler->model()))) && $fieldname !== 'id') {
            throw new \Error(sprintf("%s.%s cannot be joined because it is not a reference field or does not exist on the model.", $modelHandler->model(), $fieldname));
        }

        if (!in_array($reffieldname, Helpers::getModelProperties($this->model())) && $reffieldname !== 'id') {
            throw new \Error(sprintf("%s.%s cannot be joined does not exist on the model.", $this->model(), $reffieldname));
        }

        if (
            !empty($this->__filters__) ||
            !empty($this->__order_by__) ||
            !empty($this->__limit__) ||
            !empty($this->__having__) ||
            !empty($this->__group_by__)
        ) {
            throw new \Error(sprintf("Cannot call filter, group, having, order, or limit before a join"));
        }

        if (empty($this->projected())) {
            throw new \Error(sprintf("Fields must be projected before join can be made."));
        }

        if ($this->as() == null || $modelHandler->as() == null) {
            throw new \Error(sprintf("Every table in a join must have a unique alias."));
        }

        if ($fieldname !== 'id') {
            $actualfieldname = TableModelFinder::findModelColumnName($modelHandler->model(), $fieldname);
        } else {
            $actualfieldname = $fieldname;
        }

        if ($reffieldname !== 'id') {
            $actualreffieldname = TableModelFinder::findModelColumnName($this->model(), $reffieldname);
        } else {
            $actualreffieldname = $reffieldname;
        }

        if (!in_array($type, ['join', 'left join', 'right join'])) {
            throw new \Error(sprintf("Only left, right and inner joins are supported."));
        }

        $this->__joined__[] = [$modelHandler, $actualfieldname, $actualreffieldname, strtoupper($type)];
        return $this;
    }

    /**
     * Perform a join operation on a Handler.
     * 
     * @param string $reffieldname
     * @param Handler $handler
     * @param string $fieldname     
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function join(string $reffieldname, Handler $handler, string $fieldname): Handler
    {
        return $this->j($handler, $fieldname, $reffieldname, 'join');
    }

    /**
     * Perform a left join operation on a Handler.
     * 
     * @param string $reffieldname
     * @param Handler $handler
     * @param string $fieldname     
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function leftJoin(string $reffieldname, Handler $handler, string $fieldname): Handler
    {
        return $this->j($handler, $fieldname, $reffieldname, 'left join');
    }

    /**
     * Perform a right join operation on a Handler.
     * 
     * @param string $reffieldname
     * @param Handler $handler
     * @param string $fieldname     
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function rightJoin(string $reffieldname, Handler $handler, string $fieldname): Handler
    {
        return $this->j($handler, $fieldname, $reffieldname, 'right join');
    }


    /**
     * Set or get Handler alias.
     * 
     * @param string|null $alias Alias to set. Optional. If not passed, the current alias is returned.
     * 
     * @return Handler|string If setting, it returns the Handler it was called on. When getting, it returns a string.
     */
    public function as(string $alias = null)
    {
        if ($alias) {
            $this->__table_alias__ = $alias;
            return $this;
        } else {
            return $this->__table_alias__;
        }
    }


    /**
     * Resolve join.
     * 
     * @param bool $afterSet
     * 
     * @return array
     */
    private function resolveJoin($afterSet = false): array
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
}
