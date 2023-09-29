<?php

namespace Q\Orm\Migration;

use Q\Orm\Connection;
use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;
use Q\Orm\SetUp;

class Introspector
{
    public static function modelsToArrayOfTables() : array
    {
        $models = Helpers::getDeclaredModels();        
        $processed = CrossEngine::modelsToTables($models);        
        return $processed;
    }

    public static function schemaToArrayOfTables(): array
    {
        $dbName = Connection::getParameters()['name'];
        return CrossEngine::schemaToTables(SetUp::$engine, $dbName);
    }
}