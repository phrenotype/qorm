<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;

abstract class Migration
{
    public $operations = [];
    public $reverse = [];

    public function up()
    {
        // Collect main SQL (CREATE TABLE, indexes, etc.)
        $ops = array_map(function (\Closure $o) {
            return $o()->sql;
        }, $this->operations);

        // Collect deferred SQL (FK constraints that must run after all tables exist)
        $deferredOps = array_filter(array_map(function (\Closure $o) {
            return $o()->deferredSql ?? '';
        }, $this->operations));

        // Combine: main SQL first, then deferred FK SQL
        $largeQuery = join("", $ops) . join("", $deferredOps);

        if (!empty($largeQuery)) {
            Helpers::runAsTransaction($largeQuery);
        } else {
            throw new \Error("Empty up query.");
        }
    }

    public function down()
    {
        $rvs = array_map(function (\Closure $o) {
            return $o()->sql;
        }, $this->reverse);
        $largeQuery = join('', $rvs);
        if (!empty($largeQuery)) {
            Helpers::runAsTransaction($largeQuery);
        } else {
            throw new \Error("Empty down query.");
        }
    }
}
