<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;

/**
 * A model that can represent concrete or abstract ideas as well as humans.
 */
abstract class Model
{
    protected $__properties = [];

    /**
     * Construct a new model. This does not persist the object.
     * 
     * @param array $fields
     */
    public function __construct($fields = [])
    {
        $properties = Helpers::getModelProperties(static::class);
        foreach ($fields as $k => $v) {
            if (in_array($k, $properties)) {
                $this->$k = $v;
            }
        }
    }

    public function __call($name, $args)
    {
        $value = method_exists($this, $name);
        if ($value) {
            $value = $this->$value;
        } else {
            $value = $this->__properties[$name];
        }

        if ($value) {

            if ($value instanceof \Closure) {

                //Get prevState for restoring later, since we just opened up a closure
                $prevState = $this->prevState();
                foreach ($prevState as $k => $v) {
                    if ($k === $name) {
                        $prevState[$k] = $value;
                    }
                }
                $this->prevState($prevState);

                return ($value)();
            }
            return $value;
        }
        return null;
    }

    public function __get($name)
    {
        $inProps = $this->__properties[$name] ?? null;
        if ($inProps) {
            if ($inProps instanceof \Closure) {
                $evaluated = $this->$name();
                return $evaluated;
            } else {
                return $inProps;
            }
        }
    }

    public function __set($name, $value)
    {
        $this->__properties[$name] = $value;
    }

    public function getProps()
    {
        return $this->__properties;
    }

    public function __toString()
    {
        $pk = $this->pk();
        $v = $this->$pk;
        return static::class . "($v)";
    }

    /**
     * Get the primary key field of this model.
     * 
     * @return string
     */
    public function pk(): string
    {
        return TableModelFinder::findModelPk(static::class);
    }

    /**
     * Saves the object to the database.
     * 
     * @return Q\Orm\Model|null
     */
    public function save()
    {
        $pk = $this->pk();
        $schema_props = Helpers::getModelProperties(static::class);

        // Collect ALL properties: both public (on object) and dynamic (__properties)
        $all_props = [];

        // First, get schema-defined properties directly from the object (public properties)
        foreach ($schema_props as $prop) {
            if (isset($this->$prop)) {
                $all_props[$prop] = $this->$prop;
            }
        }

        // Then merge with __properties (may override or add dynamic props)
        $dynamic_props = $this->getProps();
        $all_props = array_merge($all_props, $dynamic_props);

        // Filter to schema-defined properties only (excludes _set handlers, closures, typos)
        $valid_keys = array_merge($schema_props, [$pk]);

        $filtered_props = [];
        $ignored = [];

        foreach ($all_props as $k => $v) {
            if (in_array($k, $valid_keys)) {
                $filtered_props[$k] = $v;
            } else {
                // Only warn about real typos, not relationship accessors
                $is_set_accessor = str_ends_with($k, '_set');
                $is_closure = $v instanceof \Closure;
                $is_handler = $v instanceof Handler;

                if (!$is_set_accessor && !$is_closure && !$is_handler) {
                    $ignored[] = $k;
                }
            }
        }

        // Warn about potential typos
        if (!empty($ignored)) {
            trigger_error(
                sprintf("QORM: Ignored unknown properties on %s: %s", static::class, implode(', ', $ignored)),
                E_USER_NOTICE
            );
        }

        if (array_key_exists($pk, $filtered_props)) {
            return static::items()->filter([$pk => $this->$pk])->update($filtered_props, $this->prevState())->one();
        } else {
            if ($pk === 'id') {
                return static::items()->create($filtered_props)->order_by('id DESC')->one();
            } else {
                return static::items()->create($filtered_props)->order_by("$pk DESC")->one();
            }
        }
    }

    /**
     * Used to get and state the previous state of a model.
     * 
     * @param array|null $assoc
     * 
     * @return array
     */
    public function prevState(array $assoc = null): array
    {
        static $state;
        if ($assoc) {
            $state = $assoc;
        } else {
            if ($state) {
                return $state;
            }
        }
        return [];
    }

    /**
     * Reload the model from the database.
     * 
     * @return Model
     */
    public function reload(): Model
    {
        $prevState = $this->prevState();
        $isDirty = false;

        if ($prevState) {
            // Collect current state from both public properties and __properties
            $schema_props = Helpers::getModelProperties(static::class);
            $current = [];

            foreach ($schema_props as $prop) {
                if (isset($this->$prop)) {
                    $current[$prop] = $this->$prop;
                }
            }

            // Check if any schema property has changed
            foreach ($prevState as $k => $v) {
                $currentVal = $current[$k] ?? $this->__properties[$k] ?? null;
                if ($currentVal !== $v) {
                    $isDirty = true;
                    break;
                }
            }
        }

        if ($isDirty) {
            $pk = $this->pk();
            $obj = static::items()->filter([$pk => $this->$pk])->one();

            if ($obj) {
                // Restore both public properties and __properties
                $schema_props = Helpers::getModelProperties(static::class);

                foreach ($schema_props as $prop) {
                    if (isset($obj->$prop)) {
                        $this->$prop = $obj->$prop;
                    }
                }

                // Also restore dynamic properties
                foreach ($obj->getProps() as $k => $v) {
                    $this->$k = $v;
                }

                // Update prevState with schema properties
                $newState = [];
                foreach ($schema_props as $prop) {
                    if (isset($this->$prop)) {
                        $newState[$prop] = $this->$prop;
                    }
                }
                $this->prevState(array_merge($newState, $obj->getProps()));
            }
        }
        return $this;
    }

    /**
     * Get a JSON representation of a model.
     * 
     * @param bool $expandLists
     * 
     * @return string|false
     */
    public function json($expandLists = false)
    {
        $vars = $this->getProps();
        foreach ($vars as $k => $v) {
            if ($v instanceof \Closure) {
                $vars[$k] = call_user_func($v);
            }
        }
        if ($expandLists === true) {
            foreach ($vars as $k => $v) {
                if ($v instanceof Handler) {
                    $vars[$k] = iterator_to_array($v->all());
                }
            }
        }
        return json_encode($vars, JSON_PRETTY_PRINT);
    }

    /**
     * All the objects that belong to a model.
     * 
     * @return Handler
     */
    public static function items(): Handler
    {
        return new Handler(static::class);
    }

    /**
     * Get the user defined schema.
     * 
     * @return array
     */
    public static abstract function schema(): array;
}
