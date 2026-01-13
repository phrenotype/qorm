<?php

namespace Q\Orm\Engines;

use Q\Orm\SetUp;

class Decider
{
    public static function decide($engine, $forMysql, $forSqlite, $default = null)
    {
        if ($engine === SetUp::MYSQL) {
            return (is_callable($forMysql) ? $forMysql() : $forMysql);
        } else if ($engine === Setup::SQLITE) {
            return (is_callable($forSqlite) ? $forSqlite() : $forSqlite);
        } else if ($default !== null) {
            return $default;
        } else {
            throw new \RuntimeException(
                "QORM: Database engine not configured. Call SetUp::main() before using QORM. " .
                "Current engine value: " . var_export($engine, true)
            );
        }
    }
}
