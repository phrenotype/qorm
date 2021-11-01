<?php

namespace Q\Orm\Migration;

class Topology
{
    public static function sortTablesToCreate(array $tablesToCreate): array
    {

        $newTables = [];        

        foreach ($tablesToCreate as $table) {

            $parents = self::parents($table->name, $tablesToCreate);

            if (!empty($parents) && !in_array($table->name, $newTables)) {


                $key = array_search($table->name, $newTables);
                if ($key !== false) {
                    unset($newTables[$key]);
                }


                foreach ($parents as $parent) {
                    if (!in_array($parent, $newTables)) {
                        if ($key === false) {
                            $newTables[] = $parent;
                        } else {
                            //array_splice($newTables, $offset, 0, $parent);
                            $newTables[$key] = $parent;
                            $key++;
                        }
                    }
                }

                if ($key === false) {
                    array_push($newTables, $table->name);                    
                } else {
                    $newTables[$key] = $table->name;
                }
            } else {

                if (!in_array($table->name, $newTables)) {

                   array_push($newTables, $table->name);
                }
            }
        }        

        $finalTables = [];

        foreach ($newTables as $t) {
            foreach ($tablesToCreate as $tc) {
                if ($t === $tc->name) {
                    $finalTables[] = $tc;
                }
            }
        }
                
        return $finalTables;
    }


    public static function parents(string $tableName, array $tablesToCreate): array
    {
        $parents = [];
        foreach ($tablesToCreate as $table) {
            if ($tableName === $table->name) {
                if (!empty($table->foreignKeys)) {
                    foreach ($table->foreignKeys as $fk) {
                        if($fk->refTable !== $table->name){
                            $parents[] = $fk->refTable;
                        }                        
                    }
                }
            }
        }
        return $parents;
    }


    public static function children(string $tableName, array $tablesToCreate): array
    {
        $dependencies = [];
        foreach ($tablesToCreate as $table) {
            if ($table->name === $tableName) {
                continue;
            } else {
                if (!empty($table->foreignKeys)) {
                    foreach ($table->foreignKeys as $fk) {
                        if ($fk->refTable === $tableName) {
                            $dependencies[] = $table->name;
                        }
                    }
                }
            }
        }
        return $dependencies;
    }
}
