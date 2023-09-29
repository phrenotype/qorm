<?php

namespace Q\Orm\Cli;

class FG
{
    const BLACK = '0;30';
    const DARK_GRAY = '1;30';
    const BLUE = '0;34';
    const LIGHT_BLUE = '1;34';
    const GREEN = '0;32';
    const LIGHT_GREEN = '1;32';
    const CYAN = '0;36';
    const LIGHT_CYAN = '1;36';
    const RED = '0;31';
    const LIGHT_RED = '1;31';
    const PURPLE = '0;35';
    const LIGHT_PURPLE = '1;35';
    const BROWN = '0;33';
    const YELLOW = '1;33';
    const LIGHT_GRAY = '0;37';
    const WHITE = '1;37';

    public static function getConstants()
    {
        $rc = new \ReflectionClass(self::class);
        return $rc->getConstants();
    }
}

