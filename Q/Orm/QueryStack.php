<?php

namespace Q\Orm;

/**
 * Stores all queries that have been run.
 */
class QueryStack
{
    private static $stack = [];

    /**
     * Store a query.
     * 
     * @param string $query
     * @param array $placeholders
     * 
     * @return void
     */
    public static function stack(string $query, array $placeholders): void
    {
        self::$stack[] = [
            'query' => $query,
            'placeholders' => $placeholders
        ];
    }

    /**
     * Get the stored queries.
     * 
     * @return array
     */
    public static function get(): array
    {
        return self::$stack;
    }
}
