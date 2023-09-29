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
        } else {
            return $default;
        }
    }
}
