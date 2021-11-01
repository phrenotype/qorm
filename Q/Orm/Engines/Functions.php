<?php

namespace Q\Orm\Engines;

use Q\Orm\Helpers;
use Q\Orm\SetUp;

class Functions
{    

    public static function date($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "DATE($columnName)", "strftime('%Y-%m-%d', $columnName)");
    }
    public static function time($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "TIME($columnName)", "strftime('%H:%M:%S', $columnName)");
    }    
    public static function year($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "YEAR($columnName)", "strftime('%Y', $columnName)");
    }
    public static function month($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "MONTH($columnName)", "strftime('%m', $columnName)");
    }
    public static function day($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "DAY($columnName)", "strftime('%d', $columnName)");
    }
    public static function hour($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "HOUR($columnName)", "strftime('%H', $columnName)");
    }
    public static function minute($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "MINUTE($columnName)", "strftime('%M', $columnName)");
    }
    public static function second($engine, $columnName)
    {
        $columnName = Helpers::ticks($columnName);
        return Decider::decide($engine, "SECOND($columnName)", "strftime('%S', $columnName)");
    }



    public static function random($engine){
        return Decider::decide($engine, "RAND()", "random()");
    }



    public static function nowDate(){        
        return Decider::decide(SetUp::$engine, "DATE(NOW())", "strftime('%Y-%m-%d', 'now')");
    }

    public static function nowTime(){
        return Decider::decide(SetUp::$engine, "TIME(NOW())", "strftime('%H:%M:%S', 'now'");
    }

    public static function nowDateTime(){
        return Decider::decide(SetUp::$engine, "NOW()", "strftime('%Y-%m-%d %H:%M:%S', 'now')");
    }
}
