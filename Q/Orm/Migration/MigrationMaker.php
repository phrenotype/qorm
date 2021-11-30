<?php

namespace Q\Orm\Migration;

use Q\Orm\Cli\BG;
use Q\Orm\Cli\Bin;
use Q\Orm\Cli\FG;
use Q\Orm\Engines\CrossEngine;
use Q\Orm\SetUp;
use Q\Orm\Migration\Models\Q_Migration;


class MigrationMaker
{

    private static $pdo;

    private static function requireFiles($folder)
    {
        $files = scandir($folder);
        $modified_files = array_filter($files, function ($path) {
            return ($path !== '.') && ($path !== '..') && (!preg_match("/^\./", $path));
        });

        foreach ($modified_files as $file) {
            $file = $folder . DIRECTORY_SEPARATOR . $file;
            (is_dir($file)) && (self::requireFiles($file)()) || (require($file));
        }
        return true;
    }

    public static function setUpForMigrations($models, $migrationsFolder)
    {
        global $argc;

        //Manually Include models;        
        if (is_dir($models)) {
            if (!file_exists($models)) {
                mkdir($models, 0777, true);
            }
            self::requireFiles($models);
        } else if (is_file($models)) {
            if (file_exists($models)) {
                include_once $models;
            }
        }

        //Create migrations folder, if it does not exist        
        if (!file_exists($migrationsFolder)) {
            mkdir($migrationsFolder, 0777, true);
        } else {
            if (!is_dir($migrationsFolder)) {
                die('Migrations folder must be a directory');
            }
        }

        /* AutoLoad Migrations */
        spl_autoload_register(function ($class) use ($migrationsFolder) {
            $pathToMigrationClass = $migrationsFolder . DIRECTORY_SEPARATOR . $class . '.php';
            if (file_exists($pathToMigrationClass)) {
                include $pathToMigrationClass;
            }
        });

        // To avoid a query overhead every time
        if (php_sapi_name() === 'cli' && ($argc ?? 0) > 1) {
            self::createMigrationsTable();
            self::checkIntergrity();
        }
    }



    private static function createMigrationsTable()
    {
        $query = CrossEngine::createMigrationsTableQuery(SetUp::$engine);
        if ($query == false) {
            die('Unable to create migrations table');
        }
        self::$pdo->query($query);
    }



    private static function checkIntergrity()
    {
        /* Remove migrations that do not have corresponding files */
        $migrations = Q_Migration::items()->all();
        if ($migrations) {
            foreach ($migrations as $migration) {
                $filePath = Setup::$migrationsFolder . DIRECTORY_SEPARATOR . $migration->name . '.php';
                if (!file_exists($filePath)) {
                    Q_Migration::items()->filter(['id' => $migration->id])->delete();
                }
            }
        }

        /* Re-organise migrations to include files that are not registered */
        $files = array_values(array_diff(scandir(Setup::$migrationsFolder), array('..', '.')));
        $files = array_map(function ($f) {
            return basename($f, '.php');
        }, $files);

        $migrations = iterator_to_array(Q_Migration::items()->map(function ($m) {
            return $m->name;
        }), true);

        sort($files);
        sort($migrations);




        if ($files != $migrations && !empty($files)) {

            $nms = Q_Migration::items()->all();
            $merged = array_unique(array_merge($migrations, $files));
            sort($merged);

            $ms = [];

            foreach ($nms as $m) {
                if (in_array($m->name, $merged)) {
                    $ms[] = ['name' => $m->name, 'applied' => $m->applied];
                }
            }

            foreach ($files as $f) {
                if (!in_array($f, $migrations)) {
                    $ms[] = ['name' => $f, 'applied' => null];
                }
            }

            /* Wipe out all migrations */
            Q_Migration::items()->delete();
            Q_Migration::items()->create(...$ms);
        }
    }




    private static function nextMigrationId()
    {
        $migrationsFolder = Setup::$migrationsFolder;
        $files = [];
        if ($dh = opendir($migrationsFolder)) {
            while (($file = readdir($dh)) !== false) {
                $base = basename($file, '.php');
                $base = str_replace('Migration', '', $base);
                $files[] = (int)$base;
            }
            closedir($dh);
        }
        if (empty($files)) return 1;
        else return (int)max($files) + 1;
    }

    private static function generateMigration(string $name, array $ups, array $downs)
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

    public static function make($blank = false)
    {
        $actions = TableComparer::compare();

        $ups = $actions[0];
        $downs = $actions[1];

        //No need to really check empty($downs) because ups require equal number of downs
        (!$blank) && (empty($ups)) && Bin::line('No Changes Detected', FG::RED, BG::BLACK) && die;

        //Creates the migration file and adds it to the database as an unapplied migration
        $fileNumber = sprintf("%04d", self::nextMigrationId());

        $code = self::generateMigration($fileNumber, $ups, $downs);


        $migrationName = 'Migration' . $fileNumber;
        if ((int)$fileNumber === 1) {
            file_put_contents(Setup::$migrationsFolder . DIRECTORY_SEPARATOR . $migrationName . '.php', $code);
            Q_Migration::items()->create(['name' => "Migration{$fileNumber}", 'applied' => null]);
            Bin::line('Migration ' . $migrationName . ' successfully created', FG::GREEN, BG::BLACK);
        } else if ((int)$fileNumber > 1) {
            $prevIdFormatted = sprintf("%04d", (int)$fileNumber - 1);
            $prev_contents = file_get_contents(Setup::$migrationsFolder . DIRECTORY_SEPARATOR . 'Migration' . $prevIdFormatted . '.php');
            $prev_contents = str_replace('Migration' . $prevIdFormatted, $migrationName, $prev_contents);

            if (md5($code) != md5($prev_contents)) {
                file_put_contents(Setup::$migrationsFolder . DIRECTORY_SEPARATOR . $migrationName . '.php', $code);
                Q_Migration::items()->create(['name' => "Migration{$fileNumber}", 'applied' => null]);
                Bin::line('Migration ' . $migrationName . ' successfully created', FG::GREEN, BG::BLACK);
            } else {
                Bin::line('No difference from previous migration', FG::RED, BG::BLACK);
            }
        }
        die;
    }


    private static function upMigration($className)
    {
        $instance = new $className;
        $instance->up();
        Q_Migration::items()->filter(['name' => $className])
            ->update([
                'applied' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
    }

    public static function migrate($name = null)
    {
        if ($name === null) {

            //$unapplied = self::$pdo->query("SELECT * FROM q_migration WHERE applied IS NULL ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_OBJ);
            //Migrate LAST(NOT LAST UNAPPLIED) migration

            $last = Q_Migration::items()->order_by('id DESC')->one();
            if ($last && $last->applied == false) {
                self::upMigration($last->name);
                Bin::line('Applied Migration ' . $last->name, FG::GREEN, BG::BLACK);
            } else {
                Bin::line('No unapplied migration found', FG::RED, BG::BLACK);
            }
        } else {
            //Apply all unapplied migrations up to and including named migration
            $unapplied = Q_Migration::items()->filter(['name' => $name])->one();

            if ($unapplied) {
                $beforeAndUnapplied = Q_Migration::items()->filter(['id.lte' => $unapplied->id, 'and', 'applied.is_null' => true])->order_by('id ASC')->all();
                foreach ($beforeAndUnapplied as $migration) {
                    self::upMigration($migration->name);
                }
                Bin::line('Applied all migrations down to ' . $unapplied->name, FG::GREEN, BG::BLACK);
            } else {
                Bin::line('Migration ' . $name . ' was not found', FG::RED, BG::BLACK);
            }
        }
        die;
    }


    private static function downMigration($className)
    {
        $instance = new $className;
        $instance->down();
        Q_Migration::items()->filter(['name' => $className])->update(['applied' => null]);
    }

    public static function rollback($name = null)
    {
        if ($name === null) {
            /*
            Undo the LAST MIGRATION (NOT LAST APPLIED) migration
        */
            //$last = self::$pdo->query("SELECT * FROM q_migration WHERE NOT (applied IS NULL) ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_OBJ);
            $last = Q_Migration::items()->order_by('id DESC')->one();
            if ($last && $last->applied != false) {

                self::downMigration($last->name);

                Bin::line('Rolled back ' . $name, FG::GREEN, BG::BLACK);
            } else {
                Bin::line('No applied migration found', FG::RED, BG::BLACK);
            }
        } else {
            // Rollback migrations backwards to particular state                        
            $last = Q_Migration::items()->filter(['name' => $name, 'and', 'applied.is_null' => false])->one();
            if ($last) {
                $after = Q_Migration::items()->filter(['id' => $last->id, 'and', 'applied.is_null' => false])->order_by('id DESC')->all();
                if ($after) {
                    foreach ($after as $migration) {
                        self::downMigration($migration->name);
                    }
                }
                Bin::line('Rolled back all migrations to ' . $name, FG::GREEN, BG::BLACK);
            } else {
                Bin::line('Migration ' . $name . ' has was not found or has not been applied.', FG::RED, BG::BLACK);
            }
        }
        die;
    }

    public static function migrations()
    {
        $formatCommand = function ($command, $fg = FG::GREEN) {
            return Bin::color(sprintf("%-30s", $command), FG::GREEN, BG::BLACK);
        };
        $migrations = Q_Migration::items()->order_by('id ASC')->all();
        if ($migrations->valid()) {
            $string = $formatCommand("MIGRATION", FG::BROWN) . Bin::color("DATE APPLIED", FG::BROWN, BG::BLACK) . PHP_EOL . PHP_EOL;
            foreach ($migrations as $m) {
                $string .= $formatCommand($m->name, FG::WHITE) . Bin::color($m->applied ?? 'NULL', FG::WHITE, BG::BLACK) . PHP_EOL;
            }
            fwrite(STDOUT, $string);
        } else {
            fwrite(STDOUT, Bin::color("No migrations have been registered.", FG::RED, BG::LIGHT_GRAY));
        }
    }


    public static function setPDO(\PDO $pdo)
    {
        self::$pdo = $pdo;
    }
}
