<?php

namespace Q\Orm;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Migration\MigrationMaker;
use Q\Orm\Migration\Operation;
use Q\Orm\Peculiar\Peculiar;

/**
 * An Injector for different classes
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

    public static $env;

    private static function init(string $modelsPath, string $migrationsFolder, array $config = [], string $engine = null)
    {

        static::$engine = $engine;
        static::$modelsPath = $modelsPath;
        static::$migrationsFolder = $migrationsFolder;
        static::$dbConfig = $config;

        if (!empty($config) && $engine != null) {
            Connection::setUp($config);
            $pdo = Connection::getInstance();
            Querier::setConnection($pdo);
            Operation::setPDO($pdo);
            MigrationMaker::setPDO($pdo);
            CrossEngine::setPDO($pdo);

            /* Model Integrity Checks */
            Integrity::refuseDuplicateAttributes();

            /* Set the epoch and customId for Peculiar */
            $epoch = (int)self::env('Q_PECULIAR_EPOCH');
            $customId = (int)self::env('Q_PECULIAR_CUSTOM_ID');

            if ($epoch) {
                Peculiar::setEpoch($epoch);
            }

            if ($customId) {
                Peculiar::setCustomId($customId);
            }
        }

        MigrationMaker::setUpForMigrations($modelsPath, $migrationsFolder);
    }

    /**
     * Get and environment variable.
     *
     * @param string $key
     *
     * @return string
     */
    public static function env(string $key): string|null
    {
        static $QConfig;

        if(is_array($QConfig)){
            return $QConfig[$key] ?: null;
        }else {
            $QConfig = require self::$env;
            return $QConfig[$key] ?: null;
        }        
    }


    /**
     * Wire up everthing.
     *
     * @param bool $hit
     * @param string|null $env Path to env file, if none exists in root folder.
     *
     * @return void
     */
    public static function main(string $env = null, $hit = true): void
    {
        self::$env = $env ?: 'qorm.config.php';

        if(basename(self::$env) !== "qorm.config.php"){
            throw new \Exception(sprintf("The valid configuration file is qorm.config.php"));
            die;
        }


        $models = self::env('Q_MODELS');
        $migrations = self::env('Q_MIGRATIONS');

        $host = self::env('Q_DB_HOST');
        $name = self::env('Q_DB_NAME');
        $user = self::env('Q_DB_USER');
        $pass = self::env('Q_DB_PASS');


        $engine = self::env('Q_ENGINE');

        $config = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ];

        if (!$hit) {
            $engine = null;
            $config = [];
        }

        self::init($models, $migrations, $config, $engine);
    }
}
