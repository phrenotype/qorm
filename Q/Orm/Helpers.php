<?php

namespace Q\Orm;

use Q\Orm\Migration\TableModelFinder;


/**
 * A collection of helper methods to make computer and human life easy.
 */
class Helpers
{
    public static function filterableTerminals()
    {
        return [
            'eq',
            'neq',
            'lt',
            'lte',
            'gt',
            'gte',

            'contains',
            'icontains',

            'regex',
            'iregex',

            'startswith',
            'istartswith',

            'endswith',
            'iendswith',

            'is_null',

            'in',
            'not_in',

            '.exists'
        ];
    }

    public static function filterableMutators()
    {
        return [
            'lower',
            'upper',
            'length',
            'trim',
            'ltrim',
            'rtrim',

            'date',
            'time',
            'year',
            'day',
            'month',
            'hour',
            'minute',
            'second'
        ];
    }

    /**
     * @param Model $object
     * 
     * @return bool
     */
    public static function isModelEmpty(Model $object): bool
    {

        //At least one defined attribute has to be set
        $class = Helpers::getClassName($object);
        $props = Helpers::getModelProperties($class);
        $empty = true;

        $pk = $object->pk();

        $hasPrimaryKey = $object->$pk ?? null;

        //If primary key is set, a model is not empty
        if (isset($hasPrimaryKey)) {
            return false;
        } else {
            foreach ($props as $p) {
                if (!is_null($object->$p)) {
                    $empty = false;
                }
            }
        }

        return $empty;
    }

    /**
     * @param string $model
     * 
     * @return array
     */
    public static function getModelRefFields(string $model): array
    {
        $schema = $model::schema();
        $r = [];
        foreach ($schema as $prop => $field) {
            $name = $prop;
            if (!empty($field->column->name)) {
                $name = ($prop === $field->column->name) ? $prop : $field->column->name;
            }
            if (Helpers::isRefField($name, $model)) {
                $r[] = [
                    'model' => $field->model,
                    'field' => $prop
                ];
            }
        }
        return $r;
    }

    /**
     * @param string $field
     * @param string $model
     * 
     * @return bool
     */
    public static function isRefField(string $field, string $model): bool
    {

        $returnValue = false;

        if (!$returnValue) {
            $schema = $model::schema();
            foreach ($schema as $k => $obj) {
                if ($obj->isFk()) {
                    $name = TableModelFinder::findModelColumnName($model, $k);
                    if ($name === $field || $k === $field) {
                        $returnValue = true;
                    }
                }
            }
        }

        return $returnValue;
    }

    /**
     * @param mixed $value
     * @param array $assoc
     * 
     * @return array
     */
    public static function remove($value, array $assoc): array
    {
        $new = [];
        foreach ($assoc as $v) {
            if ($v == $value) {
                continue;
            }
            $new[] = $v;
        }
        return $new;
    }

    /**
     * @return string
     */
    public static function getEscaper(): string
    {
        if (SetUp::$engine === SetUp::MYSQL) {
            return '`';
        } else {
            return '"';
        }
    }

    /**
     * @param string $value
     * 
     * @return string
     */
    public static function ticks(string $value): string
    {
        $escaper = self::getEscaper();

        if (preg_match('/(?mi)^(`|").*\1$/', trim($value))) {
            return $value;
        } else {
            return "$escaper{$value}$escaper";
        }
    }

    /**
     * @param object $object
     * 
     * @return string
     */
    public static function getClassName(object $object): string
    {
        return (new \ReflectionObject($object))->name;
    }

    /**
     * @param string $class
     * 
     * @return string
     */
    public static function getShortName(string $class): string
    {
        return (new \ReflectionClass($class))->getShortName();
    }


    /**
     * @param string $class
     * 
     * @return array
     */
    public static function getModelProperties(string $class): array
    {
        $schema = $class::schema();
        $fields = [];
        foreach ($schema as $field => $fieldObject) {

            $fields[] = $field;
        }
        return $fields;
    }

    /**
     * @param string $class
     * 
     * @return array
     */
    public static function getModelColumns(string $class): array
    {
        $schema = $class::schema();
        $fields = [];
        foreach ($schema as $field => $fieldObject) {
            $fields[] = TableModelFinder::findModelColumnName($class, $field);
        }
        return $fields;
    }

    /**
     * @return array
     */
    public static function getDeclaredModels(): array
    {
        $parent = Model::class;
        return array_filter(get_declared_classes(), function ($class) use ($parent) {
            return (is_subclass_of($class, $parent) && self::getShortName($class) !== 'Q_Migration');
        });
    }

    /**
     * Convert a table name to it's model name.
     * 
     * @param string $tableName
     * 
     * @return string
     */
    public static function tableNameToModelName(string $tableName): string
    {
        $tableName = trim($tableName, "_");
        return preg_replace_callback(
            '#_[a-zA-Z]#',
            function ($m) {
                return strtoupper(str_replace('_', '', $m[0]));
            },
            ucwords($tableName)
        );
    }

    /**
     * Convert a model class name to it's table name.
     * 
     * @param string $modelName
     * 
     * @return string
     */
    public static function modelNameToTableName(string $modelName): string
    {
        $modelName = trim($modelName, "_");
        $replaced = preg_replace_callback(
            '#(?<!^|_)[A-Z]#',
            function ($m) {
                $concat = '_' . $m[0];
                $concat = strtolower($concat);
                return $concat;
            },
            $modelName
        );
        return strtolower($replaced);
    }

    /**
     * Run a raw SQL query as a transaction.
     * 
     * @param string $largeQuery
     * 
     * @return void
     */
    public static function runAsTransaction(string $largeQuery): void
    {
        $pdo = Connection::getInstance();

        // SQLite: Foreign keys must be disabled OUTSIDE the transaction for DROP TABLE to work comfortably
        // safely ignoring constraints during migration rebuilds.
        $isSqlite = (SetUp::$engine === SetUp::SQLITE);
        $sqliteFkState = false;

        if ($isSqlite) {
            // Save current state (returns '0' or '1' string/int)
            $sqliteFkState = (bool) $pdo->query("PRAGMA foreign_keys")->fetchColumn();
            $pdo->exec("PRAGMA foreign_keys = OFF");
        }

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        try {

            fwrite(STDOUT, $largeQuery . PHP_EOL);

            // Robust SQL Splitter
            $statements = [];
            $len = strlen($largeQuery);
            $current = '';
            $i = 0;

            // States
            $inString = false;
            $quoteChar = '';
            $inLineComment = false;
            $inBlockComment = false;

            while ($i < $len) {
                $char = $largeQuery[$i];
                $next = ($i + 1 < $len) ? $largeQuery[$i + 1] : '';

                if ($inLineComment) {
                    if ($char === "\n") {
                        $inLineComment = false;
                    }
                    $current .= $char;
                    $i++;
                    continue;
                }

                if ($inBlockComment) {
                    if ($char === '*' && $next === '/') {
                        $inBlockComment = false;
                        $current .= "*/";
                        $i += 2;
                        continue;
                    }
                    $current .= $char;
                    $i++;
                    continue;
                }

                if ($inString) {
                    $current .= $char;
                    // Backticks (MySQL identifiers) and standard quotes
                    // MySQL strings support backslash. Standard strings use double-quote escape.
                    // We try to support both styles generally.
                    if ($char === '\\' && $quoteChar !== '`') {
                        if ($next !== '') {
                            $current .= $next;
                            $i++;
                        }
                    } else if ($char === $quoteChar) {
                        if ($next === $quoteChar) { // Double escape
                            $current .= $next;
                            $i++;
                        } else {
                            $inString = false;
                        }
                    }
                    $i++;
                    continue;
                }

                // Normal State
                if ($char === "'" || $char === '"' || $char === '`') {
                    $inString = true;
                    $quoteChar = $char;
                    $current .= $char;
                    $i++;
                    continue;
                }

                // Comments
                // SQL standard: -- must be followed by whitespace to be a comment
                $isLineComment = ($char === '#') ||
                    ($char === '-' && $next === '-' && isset($largeQuery[$i + 2]) && ctype_space($largeQuery[$i + 2]));
                if ($isLineComment) {
                    $inLineComment = true;
                    $current .= $char;
                    if ($char === '-') {
                        $current .= $next;
                        $i++;
                    }
                    $i++;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $current .= "/*";
                    $i += 2;
                    continue;
                }

                if ($char === ';') {
                    if (trim($current) !== '') {
                        $statements[] = trim($current);
                    }
                    $current = '';
                    $i++;
                    continue;
                }

                $current .= $char;
                $i++;
            }
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }

            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (\PDOException $e) {
                    // MySQL error 1091: Can't DROP '...'; check that column/key exists
                    $errorCode = $e->errorInfo[1] ?? null;
                    if ($errorCode === 1091) {
                        continue;
                    }
                    throw $e;
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            throw $e;
        } finally {
            if ($isSqlite) {
                if (!$pdo->inTransaction()) {
                    // Restore original state
                    $state = $sqliteFkState ? 'ON' : 'OFF';
                    $pdo->exec("PRAGMA foreign_keys = $state");
                }
            }
        }
    }

    /**
     * Recursively include all files in specified directory.
     * 
     * @param string $folder
     * 
     * @return array Returns an array of all the files found.
     */
    public static function files(string $folder): array
    {
        //Get all the files in a folder. It includes files in child folders
        $files = scandir($folder);
        $modified_files = array_filter($files, function ($path) {
            return ($path !== '.') && ($path !== '..') && (!preg_match("/^\./", $path));
        });

        $tmp = [];
        foreach ($modified_files as $file) {
            $file = $folder . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file)) {
                $tmp = array_merge($tmp, self::files($file));
            } else {
                $tmp[] = $file;
            }
        }
        return $tmp;
    }
}
