<?php

namespace Q\Orm\Traits;

use Q\Orm\Filter;
use Q\Orm\Handler;
use Q\Orm\Helpers;

trait CanGroup
{

    public function group_by(...$fields)
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

    public function having(array $assoc)
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
}
