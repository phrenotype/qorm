<?php

namespace Q\Orm;

use Q\Orm\Cli\BG;
use Q\Orm\Cli\Bin;
use Q\Orm\Cli\FG;
use Q\Orm\Migration\SchemaToModel;

class ModelGenerator
{

    public static function generate(string $modelname)
    {
        $modelspath = SetUp::$modelsPath;
        if (!is_dir($modelspath)) {
            throw new \Error("Cannot generate a new model when using a single file to store models.");
        }
        $fullpath = $modelspath . DIRECTORY_SEPARATOR . $modelname . '.php';
        $heading = SchemaToModel::modelFileHeading($modelspath);
        $contents = $heading . self::makeEmptyModel($modelname);
        file_put_contents($fullpath, $contents);
        Bin::line(sprintf("New model '%s' created at %s", $modelname, realpath($fullpath)), FG::GREEN, BG::BLACK);
    }

    private static function makeEmptyModel(string $classname)
    {

        $code = PHP_EOL . "class " . $classname . " extends Model {" . PHP_EOL . PHP_EOL;

        $code .= PHP_EOL;


        $code .= "\tpublic static function schema() : array {" . PHP_EOL;
        $code .= "\t\treturn [" . PHP_EOL;

        $code .= "\t\t];";
        $code .= PHP_EOL . "\t}";


        $code .= PHP_EOL . "}";

        return $code;
    }
}
