<?php

namespace Q\Orm\Traits;

use Q\Orm\Filter;
use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Querier;

trait CanCrud
{

    private function idException(array $assoc)
    {
        $keys = array_keys($assoc);
        if (in_array('id', $keys)) {
            throw new \Error("The keyword 'id' is reserved. The field name cannot be created or updated. It can only be queried.");
        }
    }


    private function renameCols(array $assoc, string $model)
    {
        $this->idException($assoc);
        $na = [];
        foreach ($assoc as $k => $v) {
            list($key, $value) = Filter::normalize($k, $v, $this->__model__);
            if ($key !== $k) {
                $k = $key;
            }
            $na[$k] = $value;
        }
        if (empty($na)) {
            throw new \Error("Fields validation failed for {$this->__model__}.");
        }
        return $na;
    }


    private function removeNonDeclaredProperties(array $assoc, string $model)
    {
        $cols = Helpers::getModelProperties($model);
        $na = [];
        foreach ($assoc as $k => $v) {
            if (in_array($k, $cols)) {
                $na[$k] = $v;
            }
        }
        return $na;
    }



    private function addDefaults($assoc)
    {        
        $props = $this->__model__::schema();
        $keys = array_keys($assoc);
        foreach ($props as $prop => $field) {
            $name = TableModelFinder::findModelColumnName($this->__model__, $prop);
            if (!in_array($name, $keys)) {
                if ($field->column->default instanceof \Closure) {
                    $assoc[$name] = call_user_func($field->column->default);
                }
            }
        }
        return $assoc;
    }

    private function checkNotNullWithoutDefault($assoc)
    {
        //Can foreign keys have defaults ? Yes.
        $props = $this->__model__::schema();           
        $keys = array_keys($assoc);
        foreach ($props as $prop => $field) {
            //Is it in filters ?
            $filterKeys = array_keys($this->__filters__);
            $name = TableModelFinder::findModelColumnName($this->__model__, $prop);
            $inFilters = in_array($name, $filterKeys);
            if (!in_array($name, $keys) && $field->column->null === false && is_null($field->column->default) && !$inFilters) {
                throw new \Error("{$this->__model__}.$prop cannot be null. Either provide a value or set a default value.");
            }
        }
    }

    /**
     * The purpose is actually for prefiltered Handlers, in _set relationships. Not for any other purpose.
     * That's why we don't even bother to open the handler.
     */
    private function addFiltersToCreate(array $assoc){        
        $assocKeys = array_keys($assoc);
        foreach($this->__raw_filters__ as $k=>$v){
            if(!in_array($k, $assocKeys) && $k !== 'id' && !($v instanceof Handler) && Helpers::isRefField($k, $this->__model__)){
                //Is it the primary key ?
                $assoc[$k] = $v;
            }
        }        
        return $assoc;
    }

    public function create(...$args)
    {
        if (count($args) === 1 && is_array($args[0])) {
            $assoc = $this->addFiltersToCreate($args[0]);
            $assoc = $this->renameCols($assoc, $this->__model__);            
            $this->checkNotNullWithoutDefault($assoc);
            $assoc = $this->addDefaults($assoc);
                        
            if (!empty($assoc)) {
                $insert = Querier::insert($assoc, $this->__table_name__);
                return $this;
            }
            return null;
        } else {
            //Bulk create
            $a = [];
            foreach ($args as $arg) {
                $assoc = $this->addFiltersToCreate($arg);
                $assoc = $this->renameCols($assoc, $this->__model__);
                $this->checkNotNullWithoutDefault($assoc);
                $assoc = $this->addDefaults($assoc);
                $a[] = $assoc;
            }
            $insert = Querier::insertMany($a, $this->__table_name__);
            return $this;
        }
    }



    private function prepFiltersForUpdateOrDelete()
    {
        $query_predicate = '';
        $predicate_values = [];

        if (!empty($this->__filters__)) {
            if (count($this->__filters__) === 1) {
                $query_predicate = $this->__filters__[0]['query'];
                $predicate_values = $this->__filters__[0]['placeholders'];
            } else {
                foreach ($this->__filters__ as $filter) {
                    $query_predicate .= $filter['query'] . ' AND';
                    $predicate_values = array_merge($predicate_values, $filter['placeholders']);
                }
                $query_predicate = rtrim(trim($query_predicate), ' AND');
            }
        } else {
            //No ( Empty ) filters. Batch update
        }

        return ['query' => $query_predicate, 'placeholders' => $predicate_values];
    }

    private function validateUpdateFields(array $assoc)
    {
        $assoc = $this->removeNonDeclaredProperties($assoc, $this->__model__);
        $assoc = $this->renameCols($assoc, $this->__model__);


        $pk = TableModelFinder::findPk($this->__model__);
        $new_assoc = [];
        foreach ($assoc as $field => $value) {

            //Can't modify primary key field during updates                
            if ($field != $pk) {

                $new_assoc[$field] = $value;
            } else {
                throw new \Error("Cannot modify {$this->__model__}.$field because it's a primary key.");
            }
        }
        return $new_assoc;
    }


    private function prepareFieldsForUpdate(array $assoc, $prevState)
    {
        /* Allow only 'dirty' fields */
        $nf = [];
        if ($prevState) {
            foreach ($assoc as $k => $v) {
                if ($v != $prevState[$k] && $k !== 'id') {
                    $nf[$k] = $v;
                }
            }
        } else {
            $nf = $assoc;
        }
        if (!empty($nf)) {
            $nf = $this->validateUpdateFields($nf);
        }
        return $nf;
    }


    public function update(array $assoc, array $prevState = null)
    {
        $assoc = $this->prepareFieldsForUpdate($assoc, $prevState);
        if (!empty($assoc)) {
            $preppedFilters = $this->prepFiltersForUpdateOrDelete();
            $predicate = $preppedFilters['query'];
            $values = $preppedFilters['placeholders'];

            Querier::update($assoc, $predicate, $values, $this->__table_name__);
        }
        return $this;
    }


    public function delete()
    {
        $preppedFilters = $this->prepFiltersForUpdateOrDelete();
        $predicate = $preppedFilters['query'];
        $values = $preppedFilters['placeholders'];

        $delete = Querier::delete($predicate, $values, $this->__table_name__);
        //if ((int)$delete > 0) {
        return $this;
        //}

    }
}
