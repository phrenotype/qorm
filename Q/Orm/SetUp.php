<?php

namespace Q\Orm;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Migration\MigrationMaker;
use Q\Orm\Migration\Operation;


/*
    SetUp is an Injector for the different services
*/

class SetUp
{
    const MYSQL = 'MYSQL';
    const POSTGRES = 'POSTGRES';
    const SQLITE = 'SQLITE';

    public static $engine;
    public static $modelsPath;
    public static $migrationsFolder;

    public static $dbConfig;

    private static function init(string $engine, string $modelsPath, string $migrationsFolder, array $config)
    {

        static::$engine = $engine;
        static::$modelsPath = $modelsPath;
        static::$migrationsFolder = $migrationsFolder;
        static::$dbConfig = $config;

        Connection::setUp($config);


        $pdo = Connection::getInstance();


        Querier::setConnection($pdo);
        Operation::setPDO($pdo);
        MigrationMaker::setPDO($pdo);
        CrossEngine::setPDO($pdo);
        MigrationMaker::setUpForMigrations($modelsPath, $migrationsFolder);


        /* Model Integrity Checks */
        Integrity::refuseDuplicateAttributes();
    }

    public static function env($key)
    {
        static $qAssoc;

        if (($_ENV[$key] ?? false)) {
            return $_ENV[$key];
        } else if (($_SERVER[$key] ?? false)) {
            return $_SERVER[$key];
        } else if (getenv($key)) {
            return getenv($key);
        } else if (file_exists('.env')) {
            if (is_null($qAssoc)) {
                $qAssoc = parse_ini_file('.env', false, INI_SCANNER_RAW);
            }            
            $value = $qAssoc[$key] ?? '';
            if ($value) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            return $value;
        }
        return '';
    }

    public static function main()
    {
        $host = self::env('Q_DB_HOST');
        $name = self::env('Q_DB_NAME');
        $user = self::env('Q_DB_USER');
        $pass = self::env('Q_DB_PASS');
        $models = self::env('Q_MODELS');
        $migrations = self::env('Q_MIGRATIONS');
        $engine = self::env('Q_ENGINE');
        self::init($engine, $models, $migrations, [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ]);
    }
}
