<?php

namespace Q\Orm\Migration;

use Q\Orm\Cli\BG;
use Q\Orm\Cli\Bin;
use Q\Orm\Cli\FG;
use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;
use Q\Orm\SetUp;
use Q\Orm\Migration\Models\Q_Migration;


class MigrationMaker
{

    private static $pdo;

    private static function requireModels($folder)
    {
        $files = scandir($folder);
        $modified_files = array_filter($files, function ($path) {
            return ($path !== '.') && ($path !== '..') && (!preg_match("/^\./", $path)) && (preg_match("/\.php$/", $path));
        });

        foreach ($modified_files as $file) {
            $file = $folder . DIRECTORY_SEPARATOR . $file;
            $namespaced = str_replace("/", "\\", dirname($file)) . "\\" . basename($file, '.php');
            if (!class_exists($namespaced)) {
                (is_dir($file)) && (self::requireModels($file)) || (require($file));
            }
        }
        return true;
    }

    public static function setUpForMigrations(string $models, string $migrationsFolder)
    {
        global $argc;

        //Manually Include models;                
        if (is_dir($models)) {
            if (!file_exists($models)) {
                mkdir($models, 0777, true);
            }
            self::requireModels($models);
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
        if (php_sapi_name() === 'cli' && ($argc ?? 0) > 1 && SetUp::$engine != null) {
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
        /* A registered migration whose file is missing keeps its row (and
         * its applied stamp): history is never deleted here. The missing
         * file is reported loudly instead. StateBuilder skips unresolvable
         * migrations when replaying; migrate() and rollback() fail loud
         * if asked to run one. */
        $migrations = Q_Migration::items()->all();
        if ($migrations) {
            foreach ($migrations as $migration) {
                $filePath = Setup::$migrationsFolder . DIRECTORY_SEPARATOR . $migration->name . '.php';
                if (!file_exists($filePath)) {
                    Bin::line('Migration file missing for registered migration ' . $migration->name . ' -- history row preserved', FG::RED, BG::BLACK);
                }
            }
        }

        /* Register files that are not yet registered. Insert-only: existing
         * rows keep their ids and applied stamps. */
        $files = array_values(array_diff(scandir(Setup::$migrationsFolder), array('..', '.')));
        $files = array_filter($files, function ($f) {
            if (preg_match("/^\./", $f)) {
                return false;
            } else {
                return true;
            }
        });
        $files = array_map(function ($f) {
            return basename($f, '.php');
        }, $files);

        $migrations = iterator_to_array(Q_Migration::items()->map(function ($m) {
            return $m->name;
        }), true);

        sort($files);

        foreach ($files as $f) {
            if (!in_array($f, $migrations)) {
                Q_Migration::items()->create(['name' => $f, 'applied' => null]);
            }
        }
    }








    public static function make($blank = false)
    {
        $actions = TableComparer::compare();

        $ups = $actions[0];
        $downs = $actions[1];

        //No need to really check empty($downs) because ups require equal number of downs
        (!$blank) && (empty($ups)) && Bin::line('No Changes Detected', FG::RED, BG::BLACK) && die;

        //Creates the migration file and adds it to the database as an unapplied migration
        $fileNumber = sprintf("%04d", MigrationGenerator::nextMigrationId());

        $code = MigrationGenerator::generateMigration($fileNumber, $ups, $downs);


        $migrationName = 'Migration' . $fileNumber;
        if ((int) $fileNumber === 1) {
            file_put_contents(Setup::$migrationsFolder . DIRECTORY_SEPARATOR . $migrationName . '.php', $code);
            Q_Migration::items()->create(['name' => "Migration{$fileNumber}", 'applied' => null]);
            Bin::line('Migration ' . $migrationName . ' successfully created', FG::GREEN, BG::BLACK);
        } else if ((int) $fileNumber > 1) {
            $prevIdFormatted = sprintf("%04d", (int) $fileNumber - 1);
            $prevPath = Setup::$migrationsFolder . DIRECTORY_SEPARATOR . 'Migration' . $prevIdFormatted . '.php';
            /* A missing previous file means degraded history (its row is
             * preserved but the file is gone): there is nothing to dedup
             * against, so proceed as different. */
            $prev_contents = file_exists($prevPath) ? str_replace('Migration' . $prevIdFormatted, $migrationName, file_get_contents($prevPath)) : null;

            if ($prev_contents === null || md5($code) != md5($prev_contents)) {
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

            $last = Q_Migration::items()->order_by('id DESC')->one();
            if ($last && $last->applied == false) {
                try {
                    self::upMigration($last->name);
                    Bin::line('Applied Migration ' . $last->name, FG::GREEN, BG::BLACK);
                } catch (\Throwable $e) {
                    Bin::line("Migration {$last->name} Failed", FG::RED, BG::BLACK);
                    Bin::line($e->getMessage(), FG::RED, BG::BLACK);
                    Bin::line("In " . $e->getFile() . " on line " . $e->getLine(), FG::RED, BG::BLACK);
                    die;
                }
            } else {
                Bin::line('No unapplied migration found', FG::RED, BG::BLACK);
            }
        } else {
            //Apply all unapplied migrations up to and including named migration
            $unapplied = Q_Migration::items()->filter(['name' => $name])->one();

            if ($unapplied) {
                $beforeAndUnapplied = Q_Migration::items()->filter(['id.lte' => $unapplied->id, 'and', 'applied.is_null' => true])->order_by('id ASC')->all();
                foreach ($beforeAndUnapplied as $migration) {
                    try {
                        self::upMigration($migration->name);
                    } catch (\Throwable $e) {
                        Bin::line("Migration {$migration->name} Failed", FG::RED, BG::BLACK);
                        Bin::line($e->getMessage(), FG::RED, BG::BLACK);
                        Bin::line("In " . $e->getFile() . " on line " . $e->getLine(), FG::RED, BG::BLACK);
                        die;
                    }
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

            $last = Q_Migration::items()->order_by('id DESC')->one();
            if ($last && $last->applied != false) {
                try {
                    self::downMigration($last->name);
                    Bin::line('Rolled back ' . $last->name, FG::GREEN, BG::BLACK);
                } catch (\Throwable $e) {
                    Bin::line("Rollback {$last->name} Failed", FG::RED, BG::BLACK);
                    Bin::line($e->getMessage(), FG::RED, BG::BLACK);
                    Bin::line("In " . $e->getFile() . " on line " . $e->getLine(), FG::RED, BG::BLACK);
                    die;
                }
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
                        try {
                            self::downMigration($migration->name);
                        } catch (\Throwable $e) {
                            Bin::line("Rollback {$migration->name} Failed", FG::RED, BG::BLACK);
                            Bin::line($e->getMessage(), FG::RED, BG::BLACK);
                            Bin::line("In " . $e->getFile() . " on line " . $e->getLine(), FG::RED, BG::BLACK);
                            die;
                        }
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
