<?php

namespace Q\Orm\Cli;

class BG
{
    const BLACK = '40';
    const RED = '41';
    const GREEN = '42';
    const YELLOW = '43';
    const BLUE = '44';
    const MAGENTA = '45';
    const CYAN = '46';
    const LIGHT_GRAY = '47';

    public static function getConstants()
    {
        $rc = new \ReflectionClass(self::class);
        return $rc->getConstants();
    }
}