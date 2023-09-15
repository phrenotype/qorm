<?php

namespace Q\Orm\Migration;

class Topology
{

    public static function sortTablesToCreate(array $tablesToCreate): array
    {

        $allParents = array_filter($tablesToCreate, function ($table) use ($tablesToCreate) {
            if (!empty(static::children($table->name, $tablesToCreate))) {
                return true;
            } else {
                return false;
            }
        });


        $topLevelParents = array_filter($allParents, function ($table) use ($allParents) {
            if (!empty(static::parents($table->name, $allParents))) {
                return false;
            } else {
                return true;
            }
        });

        $lowLevelParents = array_filter($allParents, function ($table) use ($allParents) {
            if (!empty(static::parents($table->name, $allParents))) {
                return true;
            } else {
                return false;
            }
        });



        $newTables = array_map(function ($table) {
            return $table->name;
        }, $topLevelParents);

        $newTables = [...$newTables, ...array_map(function ($table) {
            return $table->name;
        }, $lowLevelParents)];


        foreach ($tablesToCreate as $table) {

            $parents = self::parents($table->name, $tablesToCreate);

            if (!in_array($table->name, $newTables)) {


                $key = array_search($table->name, $newTables);

                //Add the parents first
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
                        // Referring to your self will not count against you.
                        if ($fk->refTable !== $table->name) {
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
            //If you point to your self, you do not have children
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
