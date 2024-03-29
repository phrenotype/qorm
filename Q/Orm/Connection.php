<?php

namespace Q\Orm;

use Q\Orm\Engines\Decider;

/**
 * Handles instantiation of PDO objects.
 */
class Connection
{

    private static $parameters = false;
    private static $instance = false;

    private function __construct(array $parameters)
    {
        self::$parameters = $parameters;
        $instance = $this->decidePDOInstance($parameters);

        if ($instance) {
            self::$instance = $instance;
        }
    }

    private function decidePDOInstance(array $parameters)
    {
        return Decider::decide(
            Setup::$engine,
            function () use ($parameters) {
                return $this->connectMySQL($parameters);
            },
            function () use ($parameters) {
                return $this->connectSQLite($parameters);
            }
        );
    }

    private function connectSQLite(array $parameters): \PDO
    {

        $name = $parameters['name'];
        if ($name) {

            $connectionString = 'sqlite:' . $name . '.sqlite3';

            $pdo = new \PDO($connectionString, null, null, [
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_PERSISTENT => true,
            ]);

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);            

            /* Create a function for sql regex to use */
            $pdo->sqliteCreateFunction(
                'regexp',
                function ($pattern, $data, $delimiter = '~', $modifiers = 'isuS') {
                    if (isset($pattern, $data) === true) {
                        return (preg_match(sprintf('%1$s%2$s%1$s%3$s', $delimiter, $pattern, $modifiers), $data) > 0);
                    }

                    return null;
                }
            );

            $pdo->sqliteCreateFunction(
                'concat',
                function (...$args) {
                    return array_reduce($args, function ($c, $i) {
                        return $c . (string)$i;
                    }, '');
                }
            );

            return $pdo;
        } else {
            throw new \Error('Database Name Required');
        }
    }

    private function connectMySQL(array $parameters): \PDO
    {
        $host = $parameters['host'] ?? false;
        $name = $parameters['name'] ?? false;
        $user = $parameters['user'] ?? false;
        $password = $parameters['pass'] ?? '';

        if ($host && $name && $user) {

            $connectionString = 'mysql:dbname=' . $name . ';host=' . $host . ';charset=utf8;';

            $pdo = new \PDO(
                $connectionString,
                $user,
                $password,
                [
                    \PDO::ATTR_PERSISTENT => true,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);            

            return $pdo;
        } else {
            throw new \Error('Invalid parameters');
        }
    }


    /**
     * Get connected PDO instance.
     * 
     * @return \PDO
     */
    public static function getInstance(): \PDO
    {
        return self::$instance;
    }


    /**
     * Create a connection.
     * 
     * @param mixed $parameters
     * 
     * @return \PDO
     */
    public static function setUp($parameters): \PDO
    {
        if (self::$instance == false) {
            new self($parameters);
            return self::$instance;
        } else {

            return self::$instance;
        }
    }


    /**
     * Get connection parameters.
     * 
     * @return array
     */
    public static function getParameters(): array
    {
        return self::$parameters;
    }
}
