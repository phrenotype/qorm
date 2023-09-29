<?php

namespace Q\Orm\Engines;

class DataTypes
{
    public static function smallInteger($engine)
    {
        return Decider::decide($engine, "SMALLINT", "INTEGER");
    }
    public static function integer($engine)
    {
        return Decider::decide($engine, "INT", "INTEGER");
    }
    public static function bigInteger($engine)
    {
        return Decider::decide($engine, "BIGINT", "INTEGER");
    }
    public static function text($engine)
    {
        return Decider::decide($engine, "TEXT", "TEXT");
    }
    public static function mediumText($engine)
    {
        return Decider::decide($engine, "MEDIUMTEXT", "TEXT");
    }
    public static function largeText($engine)
    {
        return Decider::decide($engine, "LARGETEXT", "TEXT");
    }
}
