<?php

namespace Q\Orm;

/* >>> IMPORTANT : Remember to update the filterableTerminal and filterableMutators methods in the Helper class
anytime a new filter method is added */

interface Filterable
{

    /* Final methods */
    function eq();
    function neq();
    function lt();
    function lte();
    function gt();
    function gte();

    function contains();
    function icontains();

    function regex();
    function iregex();

    function startswith();
    function endswith();

    function istartswith();
    function iendswith();

    function is_null();
    
    function in();
    function not_in();



    /* Key changing methods */
    function lower();
    function upper();
    function length();
    function trim();
    function rtrim();
    function ltrim();

    function date();
    function time();
    function year();
    function day();
    function month();
    function hour();
    function minute();
    function second();

    /* Extract expression and replacement */
    function extract();

    function __get($name);
}
