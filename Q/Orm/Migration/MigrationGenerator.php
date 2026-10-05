<?php

namespace Q\Orm\Migration;

use Q\Orm\Migration\Models\Q_Migration;
use Q\Orm\SetUp;

class MigrationGenerator
{

    public static function nextMigrationId()
    {
        $migrationsFolder = SetUp::$migrationsFolder;
        $files = [];
        if ($dh = opendir($migrationsFolder)) {
            while (($file = readdir($dh)) !== false) {
                $base = basename($file, '.php');
                $base = str_replace('Migration', '', $base);
                $files[] = (int)$base;
            }
            closedir($dh);
        }
        /* History rows are never deleted, so registered numbers stay
         * reserved even when their files are missing. Without this,
         * make() would reuse a registered number and hit the unique
         * name constraint on insert. */
        foreach (Q_Migration::items()->all() as $m) {
            $files[] = (int)str_replace('Migration', '', $m->name);
        }
        if (empty($files)) return 1;
        else return (int)max($files) + 1;
    }

    public static function generateMigration(string $name, array $ups, array $downs)
    {
        $code = '';
        $code  = "<?php" . PHP_EOL . PHP_EOL;
        $code .= "use \\Q\\Orm\\Field;" . PHP_EOL;
        $code .= "use \\Q\\Orm\\Migration\\Migration;" . PHP_EOL;
        $code .= "use \\Q\\Orm\\Migration\\SchemaBuilder;" . PHP_EOL;
        $code .= "use \\Q\\Orm\\Migration\\Schema;" . PHP_EOL;
        $code .= "use \\Q\\Orm\\Migration\\Column;" . PHP_EOL . PHP_EOL;
        $code .= "class Migration" . $name . " extends Migration {" . PHP_EOL . PHP_EOL;
        $code .= "\tpublic function __construct(){" . PHP_EOL . PHP_EOL;
        $code .= "\t\t\$this->operations = [" . PHP_EOL;

        if (!empty($ups)) {
            foreach ($ups as $op) {
                $code .= "\t\t\t$op," . PHP_EOL;
            }
        }


        $code .= "\t\t];" . PHP_EOL . PHP_EOL;

        /* */
        $code .= "\t\t\$this->reverse = [" . PHP_EOL;

        if (!empty($downs)) {
            foreach ($downs as $down) {
                $code .= "\t\t\t$down," . PHP_EOL;
            }
        }

        $code .= "\t\t];" . PHP_EOL;
        /* */


        $code .= "\t}" . PHP_EOL;
        $code .= "}";

        return $code;
    }
}
