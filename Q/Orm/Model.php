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
        if($value){
            $value = $this->$value;
        }else{
            $value = $this->__properties[$name];
        }

        if ($value) {

            if ($value instanceof \Closure) {
                $this->$name = ($value)();
                $this->__properties[$name] = ($value)();
                //Reset prevState, since we just opened up a closure
                $prevState = $this->prevState();
                foreach ($prevState as $k => $v) {
                    if ($k === $name) {
                        $prevState[$k] = $value;
                    }
                }
                $this->prevState($prevState);
            }
            return $value();
        }
        return null;
    }

    public function __get($name)
    {
        $inProps = $this->__properties[$name] ?? null;
        if($inProps){
            if($inProps instanceof \Closure){
                return $this->$name();
            }else{
                return $inProps;
            }
        }        
    }

    public function __set($name, $value)
    {
        $this->__properties[$name] = $value;
    }

    public function getProps(){
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
        $object_props = $this->getProps();
        if (array_key_exists($pk, $object_props)) {

            return static::items()->filter([$pk => $this->$pk])->update($object_props, $this->prevState())->one();
        } else {
            if ($pk === 'id') {
                return static::items()->create($object_props)->order_by('id DESC')->one();
            } else {
                return static::items()->create($object_props)->order_by("$pk DESC")->one();
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
            foreach ($prevState as $k => $v) {
                if ($this->$k !== $v) {
                    $isDirty = true;
                }
            }
        }

        if ($isDirty) {
            $pk = $this->pk();
            $obj = static::items()->filter([$pk => $this->$pk])->one(true);
            foreach ($obj->getProps() as $k => $v) {
                $this->$k = $v;
            }

            $this->prevState($obj->getProps());
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
