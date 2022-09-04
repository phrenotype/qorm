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
    public static function env(string $key): string
    {
        static $qAssoc;

        $env = self::$env;
        if (!$env) {
            $env = '.env';
        }

        if (($_ENV[$key] ?? false)) {

            return $_ENV[$key];
        } else if (($_SERVER[$key] ?? false)) {
            return $_SERVER[$key];
        } else if (getenv($key)) {

            return getenv($key);
        } else if (is_readable($env)) {

            if (is_null($qAssoc)) {
                $qAssoc = parse_ini_file($env, false, INI_SCANNER_RAW);
            }

            $value = $qAssoc[$key] ?? '';
            if ($value) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            return $value;
        }
        return null;
    }


    /**
     * Wire up everthing.
     *
     * @param bool $hit
     * @param string|null $env Path to env file, if none exists in root folder.
     *
     * @return void
     */
    public static function main($hit = true, string $env = null): void
    {
        self::$env = $env;

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
