<?php

namespace Q\Orm;

use Q\Orm\Cli\Bin;
use Q\Orm\Migration\Introspector;

use function Q\Orm;

/* The Integrity class tries to make sure that the models are in order */

class Integrity
{
    public static function refuseDuplicateAttributes()
    {
        $models = Introspector::modelsToArrayOfTables();
        foreach ($models as $table) {
            foreach ($models as $other) {
                //Don't compare the table against itself
                if ($table->name !== $other->name) {
                    $attributes = array_map(function ($c) {
                        return $c->name;
                    }, $table->fields);
                    $other_attributes = array_map(function ($c) {
                        return $c->name;
                    }, $other->fields);
                    sort($attributes);
                    sort($other_attributes);
                    if (count($attributes) === count($other_attributes) && $attributes == $other_attributes) {
                        Bin::line("$table->name and $other->name seem like same model. They have exactly same attributes. Please remove one or consider adding or changing attribute names.", '0;31;47m');
                        die;
                    }
                }
            }
        }
    }

    public static function phpVersionCheck(){

    }
}
