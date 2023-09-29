<?php

namespace Q\Orm\Migration;

use Q\Orm\Helpers;

abstract class Migration
{
    public $operations = [];
    public $reverse = [];

    public function up()
    {        
        $ops = array_map(function (\Closure $o) {
            return $o()->sql;
        }, $this->operations);
        $largeQuery = join("", $ops);
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
