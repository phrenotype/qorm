<?php

namespace Q\Orm;

class Project
{
    public static function join(string $value): bool
    {
        $escaper = Helpers::getEscaper();
        if (!preg_match("|($escaper\w+$escaper\.$escaper\w+$escaper)(\s+as\s+$escaper\w+$escaper)?|i", $value)) {
            return false;
        } else {
            return true;
        }
    }

    public static function plainAliased(string $value): bool
    {
        return (bool)preg_match(Handler::PLAIN_ALIASED_FIELD, strtolower($value));
    }

    public static function aggregateWithAs(string $value): bool
    {
        return (bool)preg_match(Handler::AGGRT_WITH_AS, strtolower($value));
    }

    public static function aggregateWithAsAndTicks(string $value): bool
    {
        return (bool)preg_match(Handler::AGGRT_WITH_AS_AND_TICKS, strtolower($value));
    }
}
