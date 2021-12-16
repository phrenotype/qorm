<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;

class Filter
{

    const CONJUNCTIONS = ['and', 'or'];

    private $string = '';
    private $values = [];

    private $store = [];

    private function __construct(array $assoc)
    {
        $this->store = $assoc;
    }

    public function parse($prefixWith = null)
    {

        foreach ($this->store as $key => $value) {

            if (is_int($key) && in_array(strtolower($value), self::CONJUNCTIONS)) {
                //For connectors and & or
                $this->string .= ' ' . strtoupper($value);
            } else if (is_string($key) && preg_match("#^\w+$#", $key)) {
                $this->string .= ' ' . Helpers::ticks($key) . ' = ?';
                $this->values[] = $value;
            } else {
                /* Will maybe add object path lookups later */
                /* E.g User::objects->filter(['user.accounts.id'=>]) */
                //For now, only support first level filter lookups for primitives

                $exploded = explode('.', $key);

                $field = Helpers::ticks($exploded[0]);

                if (preg_match("/^\w+$/", $exploded[0])) {
                    $field = Helpers::ticks($exploded[0]);
                    if ($prefixWith) {
                        $field = Helpers::ticks($prefixWith) . '.' . Helpers::ticks($exploded[0]);
                    }
                }


                $methods = array_slice($exploded, 1);

                $eval = $this->path($field, $value, $methods);

                if (is_array($eval)) {
                    $this->string .= ' ' . $eval[0];
                    if (is_array($eval[1])) {
                        //Flatten inner arrays, probably from an in statement
                        foreach ($eval[1] as $v) {
                            $this->values[] = $v;
                        }
                    } else {
                        $this->values[] = $eval[1];
                    }
                } else {
                    $this->string .= ' ' . $eval;
                }
            }
        }

        return ['query' => $this->string, 'placeholders' => $this->values];
    }


    public function path($key, $value, $methods)
    {
        $filterable = new ModelFilterable($key, $value);
        foreach ($methods as $method) {
            if (!method_exists($filterable, $method)) {
                throw new \Error("Invalid filter '$method'");
            }
            $filterable->$method();
        }
        return $filterable->extract();
    }

    public static function filter(array $assoc, $prefixWith = null)
    {
        return (new static($assoc))->parse($prefixWith);
    }

    public static function normalize($k, $v, $model, $ignoreUnkownFields = false)
    {
        $schema = $model::schema();

        $cols = Helpers::getModelColumns($model);
        $props = Helpers::getModelProperties($model);

        $inCols = in_array($k, $cols);
        $inProps = in_array($k, $props);

        $doesNotExist = !$inCols && !$inProps;
        $onlyCols = $inCols && !$inProps;
        $onlyProps = $inProps && !$inCols;
        $inBoth = $inProps && $inCols;


        if (!$ignoreUnkownFields) {
            if ($doesNotExist) {
                throw new \Error(sprintf("%s.%s field does not exist.", $model, $k));
            }

            if ($onlyCols) {
                //For direct column names of refs e.g user_id
                throw new \Error(sprintf("%s.%s does not exist in code. Please use the field name declared on %s.", $model, $k, $model));
            }
        }


        if (is_object($v)) {

            //Get this out of the way
            if (!($v instanceof Model) && !($v instanceof Handler)) {
                throw new \Error(sprintf("Objects must always be a type of %s or %s'.", Model::class, Handler::class));
            }


            //And this too
            if ($v instanceof Model && Helpers::isModelEmpty($v)) {
                throw new \Error(sprintf("%s.%s is an empty object. Models must have at least one field populated.", $model, $k));
            }


            $columnName = TableModelFinder::findModelColumnName($model, $k);


            $valuePkField = ($v instanceof Model) ? TableModelFinder::findPk(Helpers::getClassName($v)) : null;

            if ($inBoth) {

                //Either it's a regular scalar field, or the user decided to manually name the field.                        
                $isFk = TableModelFinder::findModelFK($model, function ($table, $fk) use ($columnName) {
                    return ($fk->field === $columnName);
                });

                if (is_object($isFk) && $v instanceof Model) {
                    $key = $columnName;
                    $value = $v->$valuePkField;
                } else if (($v instanceof Handler)) {
                    $key = $columnName;
                    $value = $v;
                } else if (!is_object($isFk)) {
                    throw new \Error(sprintf("%s.%s cannot point to non instances of %s because it's not a reference field.", $model, $k, Handler::class));
                }
            } else if ($onlyProps) {

                if ($v instanceof Model) {
                    if ($valuePkField && $columnName) {
                        $key = $columnName;
                        $value = $v->$valuePkField;
                    }
                } else if ($v instanceof Handler) {
                    $key = $columnName;
                    $value = $v;
                }
            }
        } else if (!is_object($v)) {

            $columnName = TableModelFinder::findModelColumnName($model, $k);

            /*
            $isFk = TableModelFinder::findModelFK($model, function ($table, $fk) use ($k, $columnName) {
                return ($fk->field === $columnName);
            });

            if (is_object($isFk)) {
                throw new \Error(sprintf("%s.%s cannot point to non-objects because it is a reference field.", $model, $k));
            }
            */

            if ($onlyProps) {

                if ($columnName) {
                    $key = $columnName;
                    $value = $v;
                }
            } else if ($onlyCols) {
                // Scalar fields must exist both in both or props, not cols only
                throw new \Error(sprintf("%s.%s cannot be used because it is not accessible.", $model, $k));
            } else if ($inBoth) {
                //Either it's a regular scalar field, or the user decided to manually name the field.                        
                //Either ways, it does not matter here, since a non-object is being passed
                //And it's in both                    
                $key = $k;
                $value = $v;
            }

            if ($ignoreUnkownFields) {
                $key = $k;
                $value = $v;
            }
        }

        return [$key, $value];
    }

    public static function objectify(array $assoc, string $model, $joined = false)
    {

        $newAssoc = [];


        foreach ($assoc as $k => $v) {

            /* Skip instances of Handler */
            // if($v instanceof Handler){
            //     $newAssoc[$k]=$v;
            //     continue;
            // }

            $tmpKey = null;
            if (preg_match("#(\w+\.)+\w+#", $k)) {
                preg_match("#^(\w+)#", $k, $matches);
                if ($matches) {
                    $tmpKey = $matches[1];
                }
            } else {
                $tmpKey = $k;
            }


            if ($tmpKey === 'id' && (is_object($v) && !($v instanceof Handler))) {
                throw new \Error(sprintf("%s.%s can only reference instances of %s.", $model, $tmpKey, Handler::class));
            }


            //Spare 'id' since this might be a query
            if ((is_int($k) && in_array(strtolower($v), Filter::CONJUNCTIONS)) || $tmpKey === 'id') {
                $newAssoc[$k] = $v;
                continue;
            }


            list($key, $value) = self::normalize($tmpKey, $v, $model, $joined);

            if ($key !== $tmpKey) {
                if (preg_match("#(\w+\.)+\w+#", $k)) {
                    $exploded = explode('.', $k);
                    array_shift($exploded);
                    array_unshift($exploded, $key);
                    $k = implode('.', $exploded);
                } else {
                    $k = $key;
                }
            }
            $newAssoc[$k] = $value;
        }
        if (empty($newAssoc)) {
            throw new \Error('Filter validation failed. Check your filters.');
        }
        return $newAssoc;
    }

    public static function validate(array $filters)
    {
        $count = count($filters);
        $keys = array_keys($filters);
        if ($count > 1) {
            if (in_array(strtolower($keys[1]), Filter::CONJUNCTIONS)) {
                throw new \Error('Filters cannot start with a conjuction.');
            } else if (in_array($keys[$count - 1], Filter::CONJUNCTIONS)) {
                throw new \Error('Filters cannot end with a conjunction');
            }
        }
        foreach ($keys as $k) {
            if (preg_match("#^(?:\w+\.)+\w+$#", $k)) {
                $ploded = explode(".", $k);
                $n = count($ploded);
                $last = $ploded[$n - 1];

                if (!in_array($last, Helpers::filterableTerminals()) && $n === 1) {
                    throw new \Error(". syntax fields must end with a terminal method.");
                }
                if ($n > 2) {
                    $first = $ploded[1];
                    if (!in_array($last, Helpers::filterableTerminals())) {
                        throw new \Error(". syntax fields must end with a terminal method.");
                    }
                    if (!in_array($first, Helpers::filterableMutators())) {
                        throw new \Error(". syntax fields must begin with a mutator method.");
                    }
                }
            }
        }
    }

    public static function validateHaving(array $filters)
    {
        $count = count($filters);
        $keys = array_keys($filters);
        if ($count > 1) {
            if ($count !== 2) {
                throw new \Error(sprintf("Having filters must be at least two"));
            }
            if (in_array(strtolower($keys[1]), Filter::CONJUNCTIONS)) {
                throw new \Error('Filters cannot start with a conjuction.');
            } else if (in_array($keys[$count - 1], Filter::CONJUNCTIONS)) {
                throw new \Error('Filters cannot end with a conjunction.');
            }
        }
        foreach ($keys as $k) {
            if (!preg_match("/^(\w+)\((\*|\w+)\)\.\w+$/i", $k)) {
                throw new \Error(sprintf("'%s' has to be an aggregate function without ticks", $k));
            }
            if (preg_match("#^(?:[\w()]+\.)+\w+$#", $k)) {
                $ploded = explode(".", $k);
                $n = count($ploded);
                $last = $ploded[$n - 1];

                if (!in_array($last, Helpers::filterableTerminals()) && $n === 1) {
                    throw new \Error(". syntax fields must end with a terminal method.");
                }


                if ($n > 2) {
                    $first = $ploded[1];
                    if (!in_array($last, Helpers::filterableTerminals())) {
                        throw new \Error(". syntax fields must end with a terminal method.");
                    }
                    if (!in_array($first, Helpers::filterableMutators())) {
                        throw new \Error(". syntax fields must begin with a mutator method.");
                    }
                }
            }
        }
    }
}
