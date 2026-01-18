<?php

namespace Q\Orm\Migration;

class Topology
{

    public static function sortTablesToCreate(array $tablesToCreate): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        // Map names to objects for quick lookup
        $tableMap = [];
        foreach ($tablesToCreate as $table) {
            $tableMap[$table->name] = $table;
        }

        foreach ($tablesToCreate as $table) {
            self::visit($table, $tableMap, $sorted, $visited, $visiting);
        }

        return $sorted;
    }

    private static function visit($table, array $tableMap, array &$sorted, array &$visited, array &$visiting)
    {
        if (isset($visited[$table->name])) {
            return;
        }

        if (isset($visiting[$table->name])) {
            throw new \RuntimeException("Circular dependency detected involving table: " . $table->name);
        }

        $visiting[$table->name] = true;

        if (!empty($table->foreignKeys)) {
            foreach ($table->foreignKeys as $fk) {
                // Ignore self-references
                if ($fk->refTable === $table->name) {
                    continue;
                }

                // Only visit if the parent is in the list of tables we are creating
                if (isset($tableMap[$fk->refTable])) {
                    self::visit($tableMap[$fk->refTable], $tableMap, $sorted, $visited, $visiting);
                }
            }
        }

        unset($visiting[$table->name]);
        $visited[$table->name] = true;
        $sorted[] = $table;
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
