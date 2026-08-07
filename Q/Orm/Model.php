<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;

/**
 * A model that can represent concrete or abstract ideas as well as humans.
 */
abstract class Model
{
    protected $__properties = [];
    protected $_prevState = [];

    /**
     * Construct a new model. This does not persist the object.
     * 
     * @param array $fields
     */
    public function __construct($fields = [])
    {
        // Unset Peculiar ID if provided; users cannot manually set these.
        $peculiarField = TableModelFinder::findPeculiarField(static::class);
        if ($peculiarField && isset($fields[$peculiarField])) {
            unset($fields[$peculiarField]);
        }

        $properties = Helpers::getModelProperties(static::class);
        foreach ($fields as $k => $v) {
            if (in_array($k, $properties)) {
                $this->$k = $v;
            }
        }
    }

    public function __call($name, $args)
    {
        // If a public property exists with this name (e.g. $post->user), return it
        if (property_exists($this, $name)) {
            $value = $this->$name;
        } else {
            // Otherwise look in dynamic properties
            $value = $this->__properties[$name] ?? null;
        }

        if ($value !== null) {

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
        if ($inProps !== null) {
            if ($inProps instanceof \Closure) {
                $evaluated = $this->$name();
                return $evaluated;
            } else {
                return $inProps;
            }
        }
        return null;
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
     * @return \Q\Orm\Model|null
     */
    public function save()
    {
        $pk = $this->pk();
        $schema_props = Helpers::getModelProperties(static::class);

        // Collect ALL properties: both public (on object) and dynamic (__properties)
        $all_props = [];

        // First, get schema-defined properties directly from the object (public properties)
        $prevState = $this->prevState();
        foreach ($schema_props as $prop) {
            $prev = $prevState[$prop] ?? null;
            // Include the prop when it holds a value now, or when it held a
            // non-null value before: a null current value over a non-null
            // previous one can only mean the user explicitly nulled it.
            if ($this->$prop !== null || $prev !== null) {
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
            // Skip Closures and Handlers - these are relationship accessors, not persistable
            $is_closure = $v instanceof \Closure;
            $is_handler = $v instanceof Handler;

            if ($is_closure || $is_handler) {
                continue; // Skip relationship accessors
            }

            if (in_array($k, $valid_keys)) {
                $filtered_props[$k] = $v;
            } else {
                // Only warn about real typos, not _set accessors
                $is_set_accessor = str_ends_with($k, '_set');

                if (!$is_set_accessor) {
                    // Check if it's a valid DB column (e.g. user_id vs user)
                    if (!in_array($k, Helpers::getModelColumns(static::class))) {
                        $ignored[] = $k;
                    }
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

        $prevState = $this->prevState();
        if (array_key_exists($pk, $prevState) && $this->$pk !== $prevState[$pk]) {
            throw new \Error("Cannot modify " . static::class . ".$pk because it's a primary key.");
        }

        $result = null;
        if (array_key_exists($pk, $filtered_props)) {
            $result = static::items()->filter([$pk => $this->$pk])->update($filtered_props, $prevState)->one();
        } else {
            if ($pk === 'id') {
                $result = static::items()->create($filtered_props)->order_by('id DESC')->one();
            } else {
                $result = static::items()->create($filtered_props)->order_by("$pk DESC")->one();
            }
        }

        if ($result) {
            // Hydrate $this from the persisted result
            $schema_props = Helpers::getModelProperties(static::class);
            foreach ($schema_props as $prop) {
                if (isset($result->$prop)) {
                    $this->$prop = $result->$prop;
                }
            }
            foreach ($result->getProps() as $k => $v) {
                $this->$k = $v;
            }

            // Update prevState from the freshly loaded result — its raw-row
            // capture holds every column (NULLs included), so subsequent
            // saves in null/value/null cycles compare against real values.
            $this->prevState($result->prevState());

            return $this;
        }

        return null;
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
        if ($assoc !== null) {
            $this->_prevState = $assoc;
        }
        return $this->_prevState;
    }

    /**
     * Reload the model from the database.
     * 
     * @return Model
     */
    public function reload(): Model
    {
        // Always reload from DB, regardless of local state
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

            // The freshly loaded object carries the raw-row prevState
            // (all columns, NULLs included) — same source save() now uses.
            $this->prevState($obj->prevState());
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
