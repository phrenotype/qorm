<?php

namespace Q\Orm\Traits;

use Q\Orm\Filter;
use Q\Orm\Handler;
use Q\Orm\Helpers;
use Q\Orm\Migration\TableModelFinder;
use Q\Orm\Querier;


/**
 * Confers the ability to perform Insert, Update and Delete operations on Handlers and Humans alike.
 */
trait CanCud
{
    /**
     * Raise an exception if the key 'id' exists in an associative array.
     * 
     * @param array
     * @return void
     */
    private function idException(array $assoc)
    {
        $keys = array_keys($assoc);
        if (in_array('id', $keys)) {
            throw new \Error("The keyword 'id' is reserved. The field name cannot be created or updated. It can only be queried.");
        }
    }

    /**
     * Rename reference (key) fields to match the database column name.
     * 
     * @param array $assoc
     * @return array
     */
    private function renameCols(array $assoc): array
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


    /**
     * Remove properties not declared on model from assoc keys.
     * 
     * @param array $assoc
     * 
     * @return array
     */
    private function removeNonDeclaredProperties(array $assoc): array
    {
        $cols = Helpers::getModelProperties($this->__model__);
        $na = [];
        foreach ($assoc as $k => $v) {
            if (in_array($k, $cols)) {
                $na[$k] = $v;
            }
        }
        return $na;
    }


    /**
     * Generate values for default fields based on callbacks set.
     * 
     * @param array $assoc Associative array of values
     * @return array Returns associative array with new keys and values.
     */
    private function addDefaults($assoc): array
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

    /**
     * Ensures that fields defined as 'not null' without defaults always have a value.
     * 
     * @param array $assoc
     * 
     * @return void
     */
    private function checkNotNullWithoutDefault(array $assoc): void
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
     * This is for when a new record is being created on a pre-filtered Handler, in _set relationships. Not for any other purpose.     
     * 
     * @param array $assoc
     * 
     * @return array
     */
    private function addFiltersToCreate(array $assoc)
    {
        $assocKeys = array_keys($assoc);
        foreach ($this->__raw_filters__ as $k => $v) {
            if (!in_array($k, $assocKeys) && $k !== 'id' && !($v instanceof Handler) && Helpers::isRefField($k, $this->__model__)) {
                //Is it the primary key ?
                $assoc[$k] = $v;
            }
        }
        return $assoc;
    }


    /**
     * Create a new record, or records on a Handler. This method is variadic.
     * 
     * @param mixed ...$args A record(s) to be created.
     * 
     * @return Handler|null Returns the handler it was called on on success and null on failure.
     */
    public function create(...$args): Handler
    {
        if (count($args) === 1 && is_array($args[0])) {
            $assoc = $this->addFiltersToCreate($args[0]);
            $assoc = $this->renameCols($assoc);
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
                $assoc = $this->renameCols($assoc);
                $this->checkNotNullWithoutDefault($assoc);
                $assoc = $this->addDefaults($assoc);
                $a[] = $assoc;
            }
            $insert = Querier::insertMany($a, $this->__table_name__);
            return $this;
        }
    }


    /**
     * Prepares filters for update or deletion.
     * 
     * @return array Returns an associative array with keys 'query', 'placeholders'.
     */
    private function prepFiltersForUpdateOrDelete(): array
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


    /**
     * Validate update fields.
     * 
     * @param array $assoc
     * 
     * @return array
     */
    private function validateUpdateFields(array $assoc): array
    {
        $assoc = $this->removeNonDeclaredProperties($assoc);
        $assoc = $this->renameCols($assoc);


        $pk = TableModelFinder::findModelPk($this->__model__);
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


    /**
     * Prepare fields for update. Removes non 'dirty' fields.
     * 
     * @param array $assoc
     * @param mixed $prevState
     * 
     * @return array
     */
    private function prepareFieldsForUpdate(array $assoc, $prevState): array
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


    /**
     * Update record(s) in a Handler.
     * 
     * @param array $assoc The new values to update.
     * @param array|null $prevState The previous state, when dealing with a single model. Optional.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function update(array $assoc, array $prevState = null): Handler
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


    /**
     * Deletes a record(s) from a Hander.
     * 
     * @return Handler Returns the Handler it was called on.
     */
    public function delete(): Handler
    {
        $preppedFilters = $this->prepFiltersForUpdateOrDelete();
        $predicate = $preppedFilters['query'];
        $values = $preppedFilters['placeholders'];

        $delete = Querier::delete($predicate, $values, $this->__table_name__);

        return $this;
    }
}
