<?php

namespace Q\Orm\Engines;

class CastTypes
{
    public static function integer($engine)
    {
        return Decider::decide($engine, "UNSIGNED", "INTEGER");
    }
}