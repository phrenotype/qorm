<?php

namespace Q\Orm;

class QueryStack
{
    private static $stack = [];
    public static function stack(string $query, array $placeholders)
    {
        self::$stack[] = [
            'query' => $query,
            'placeholders' => $placeholders
        ];
    }
    public static function get()
    {
        return self::$stack;
    }
}