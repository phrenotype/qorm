<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;

class Querier
{

    private static $connection = null;

    private static $derivedClasses = [];

    public static function setConnection(\PDO $connection)
    {
        self::$connection = $connection;
    }

    public static function getConnection(): \PDO
    {
        return self::$connection;
    }

    public static function raw($sql, array $params = [])
    {
        $pdo = self::$connection;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $stmt = null;
            $pdo->commit();
            /* Stack the query */
            QueryStack::stack($sql, $params);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    public static function relatedSchema($parentClassName)
    {
        $relatedSchema = [];
        if (self::$derivedClasses) {
            $derivedClasses = self::$derivedClasses;
        } else {
            $derivedClasses = Helpers::getDeclaredModels();
            self::$derivedClasses = $derivedClasses;
        }
        foreach ($derivedClasses as $class) {
            $schema = $class::schema();
            foreach ($schema as $fieldObject) {
                $model = $fieldObject->model ?? '';
                if ($fieldObject->isFK() && $model === $parentClassName) {
                    $relatedSchema[$class] = $schema;
                }
            }
        }
        return $relatedSchema;
    }

    private static function pointBackToDom(Model $model, array $project = [])
    {
        $modelClassName = Helpers::getClassName($model);
        $modelSchema = $modelClassName::schema();

        foreach ($modelSchema as $fieldName => $fieldObject) {
            if (!empty($project) && !in_array($fieldName, $project)) {
                continue;
            }

            if ($fieldObject->isFk()) {

                $parentClass = $fieldObject->model;
                $parentPkField = TableModelFinder::findModelPk($parentClass);


                $ownAttribute = $fieldObject->column->name;
                if ($ownAttribute == false) {
                    $ownAttribute = strtolower($fieldName) . '_' . $parentPkField;
                }

                if ($model->$ownAttribute == false) {
                    $ownAttribute = $fieldName;
                }


                $lookup = $model->$ownAttribute ?? NULL;

                $relName = strtolower($fieldName);
                $model->$relName = function () use ($lookup, $parentClass, $parentPkField) {
                    return $parentClass::items()->filter([$parentPkField => $lookup])->one();
                };
            }
        }
        return $model;
    }

    private static function oneOne(Model $model, array $project = [])
    {
        $parentClassName = Helpers::getClassName($model);
        $parentTableName = Helpers::modelNameToTableName(Helpers::getShortName($parentClassName));

        $relatedSchema = self::relatedSchema($parentClassName);

        foreach ($relatedSchema as $childClass => $schema) {

            foreach ($schema as $fieldName => $fieldObject) {


                if ($fieldObject->column->type === 'one_to_one' && $fieldObject->model === $parentClassName) {
                    if (!empty($project) && !in_array($fieldName, $project)) {
                        continue;
                    }

                    /* If it's a self relationship, don't point back. continue. Related schema returns self as well. */
                    if ($childClass === $parentClassName) {
                        continue;
                    }

                    $attr = Helpers::modelNameToTableName($childClass);

                    $childTableName = Helpers::modelNameToTableName(Helpers::getShortName($childClass));
                    if (strtolower($fieldName) === $parentTableName) {
                        $attr = $childTableName;
                    } else {
                        $attr = strtolower($fieldName);
                    }


                    /* To avoid referencing id with an object, as in the case of self relationships */
                    if ($childTableName === $parentTableName) {
                        $fieldName = TableModelFinder::findModelPk($childClass);
                        $value = $model->$fieldName;
                    } else {
                        $value = clone $model;
                    }

                    $model->$attr = function () use ($childClass, $fieldName, $value) {
                        return $childClass::items()->filter([
                            $fieldName => $value
                        ])->one();
                    };
                }
            }
        }

        return $model;
    }

    private static function oneMany(Model $model, array $project = [])
    {
        $parentClassName = Helpers::getClassName($model);
        $parentTableName = Helpers::modelNameToTableName(Helpers::getShortName($parentClassName));

        $relatedSchema = self::relatedSchema($parentClassName);

        foreach ($relatedSchema as $childClass => $schema) {
            foreach ($schema as $fieldName => $fieldObject) {
                if ($fieldObject->column->type === 'many_to_one' && $fieldObject->model === $parentClassName) {

                    $childTableName = Helpers::modelNameToTableName(Helpers::getShortName($childClass));
                    if (strtolower($fieldName) === $parentTableName) {
                        $attr =  $childTableName . '_set';
                    } else {
                        $attr = strtolower($fieldName) . '_set';
                    }

                    /* To avoid referencing id with an object, as in the case of self relationships */
                    if ($childTableName === $parentTableName) {
                        $fieldName = TableModelFinder::findModelPk($childClass);
                        $value = $model->$fieldName;
                    } else {
                        $value = clone $model;
                    }
                    $model->$attr = $childClass::items()->filter([
                        $fieldName => $value
                    ]);
                }
            }
        }

        return $model;
    }

    public static function makeRelations(Model $model, array $project = [])
    {
        $model = self::oneOne($model, $project);
        $model = self::oneMany($model, $project);
        $model = self::pointBackToDom($model, $project);
        return $model;
    }

    private static function removeRefCols(Model $model)
    {
        //This actually removes ref cols but does not remove undefined cols.
        //This is done so that join fields can come back
        $properties = Helpers::getModelProperties(Helpers::getClassName($model));
        $onObject = $model->getProps();

        foreach ($onObject as $k => $v) {
            if (!in_array($k, $properties) && Helpers::isRefField($k, Helpers::getClassName($model)) && $k !== 'id' && !preg_match("#_set$#", $k)) {
                unset($model->{$k});
            }
        }
        return $model;
    }

    public static function queryOne(string $query, array $params, string $class, array $project = [])
    {
        /* Stack the query */
        QueryStack::stack($query, $params);

        $statement = self::$connection->prepare($query);

        if (empty($params)) {
            $statement->execute();
        } else {
            $statement->execute($params);
        }

        $statement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class);
        $item = $statement->fetch();
        if (is_object($item)) {
            /*
            Insert, update, and delete will clear the cache.        
            One and all will do the object lookups.
            queryOne and queryAll will put the objects in cache.
            */
            $object = self::removeRefCols(self::makeRelations($item, $project));
            $object->prevState($object->getProps());
            return $object;
        }
        return null;
    }

    public static function queryAll(string $query, array $params, string $class, array $project = [])
    {
        /* Stack the query */
        QueryStack::stack($query, $params);

        $statement = self::$connection->prepare($query);

        if (empty($params)) {
            $statement->execute();
        } else {
            $statement->execute($params);
        }

        $statement->setFetchMode(\PDO::FETCH_CLASS, $class);


        $models = function () use ($statement, $project, $query) {
            foreach ($statement as $row) {
                /*
                Insert, update, and delete will clear the cache.        
                One and all will do the object lookups.
                queryOne and queryAll will put the objects in cache.
                */
                $object = self::removeRefCols(self::makeRelations($row, $project));
                $object->prevState($object->getProps());
                yield $object;
            }
        };

        return $models;
    }

    public static function insert(array $fields, $table)
    {
        $fs = implode(', ', array_map(function ($f) {
            return Helpers::ticks($f);
        }, array_keys($fields)));
        $v = array_values($fields);

        $placeholders = implode(",", array_fill(0, count($fields), "?"));

        $table = Helpers::ticks($table);
        $sql = "INSERT INTO {$table}({$fs}) VALUES($placeholders)";

        /* Stack the query */
        QueryStack::stack($sql, $fields);


        $pdo = self::$connection;

        $stmt = $pdo->prepare($sql);

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            $stmt->execute($v);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $stmt = null;
        return $pdo->lastInsertId();
    }

    public static function insertMany(array $assocs, $table)
    {
        $fs = implode(', ', array_map(function ($f) {
            return Helpers::ticks($f);
        }, array_keys($assocs[0])));
        $placeholders = implode(",", array_fill(0, count($assocs[0]), "?"));

        $table = Helpers::ticks($table);
        $sql = "INSERT INTO {$table}({$fs}) VALUES($placeholders)";

        /* Stack the query */
        QueryStack::stack($sql, $assocs);

        $pdo = self::$connection;

        $stmt = $pdo->prepare($sql);

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }


        try {
            foreach ($assocs as $asc) {
                $stmt->execute(array_values($asc));
            }
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        $stmt = null;
        return $pdo->lastInsertId();
    }

    public static function update($fields, string $condition, array $placeholders, $table)
    {
        $fields_string = ltrim(array_reduce(array_keys($fields), function ($c, $i) {
            return $c . ', ' . Helpers::ticks($i) . ' = ?';
        }, ''), ', ');

        $values = array_values($fields);

        $final_placeholders = array_merge($values, $placeholders);

        $table = Helpers::ticks($table);

        if ($condition != '') {
            $sql = "UPDATE {$table} SET {$fields_string} WHERE {$condition}";
        } else {
            $sql = "UPDATE {$table} SET {$fields_string}";
        }

        /* Stack the query */
        QueryStack::stack($sql, $final_placeholders);


        $pdo = self::$connection;

        $stmt = $pdo->prepare($sql);

        if(!$pdo->inTransaction()){
            $pdo->beginTransaction();    
        }
        
        try {
            $stmt->execute($final_placeholders);
            $pdo->commit();
        } catch (\PDOException $e) {
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }            
        }
        $stmt = null;
    }

    public static function delete(string $condition, array $placeholders, $table)
    {
        $table = Helpers::ticks($table);
        if ($condition != '') {
            $sql = "DELETE FROM {$table} WHERE $condition";
        } else {
            $sql = "DELETE FROM {$table}";
            if (SetUp::$engine !== SetUp::SQLITE) {
                $sql = "TRUNCATE TABLE $table";
            }
        }

        /* Stack the query */
        QueryStack::stack($sql, $placeholders);


        $pdo = self::$connection;

        $stmt = $pdo->prepare($sql);

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            $stmt->execute($placeholders);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        $stmt = null;
    }
}
