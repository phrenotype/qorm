<?php

namespace Q\Orm\Migration;

class Operation
{

    const CREATE_TABLE = 'create-table';
    const DROP_TABLE = 'drop-table';
    const DROP_TABLE_IF_EXISTS = 'drop-table-if-exists';
    const RENAME_TABLE = 'rename-table';

    const ADD_COLUMN = 'add-column';
    const DROP_COLUMN = 'drop-column';
    const MODIFY_COLUMN = 'modify-column';
    const CHANGE_COLUMN = 'change-column';

    const ADD_INDEX = 'add-index';
    const ADD_UNIQUE = 'add-unique';
    const ADD_FOREIGN_KEY = 'add-foreign-key';
    const ADD_PRIMARY_KEY = 'add-primary-key';

    const DROP_INDEX = 'drop-index';
    const DROP_UNIQUE = 'drop-unique';
    const DROP_FOREIGN_KEY = 'drop-foreign-key';
    const DROP_PRIMARY_KEY = 'drop-primary-key';

    const QUERY = 'query';

    private static $pdo;

    public $name;
    public $params;
    public $sql;

    public function __construct($name, $params, $sql)
    {
        $this->name = $name;
        $this->params = $params;
        $this->sql = $sql;
    }

    public function runSql()
    {
        $pdo = self::$pdo;

        $queries = explode(';', trim($this->sql));

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($queries as $query) {
                if ($query != false) {

                    fwrite(STDOUT, $query . PHP_EOL);
                    $stmt = $pdo->query($query);
                    $stmt = null;
                }
            }
            $pdo->commit();
        } catch (\PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    public static function setPDO(\PDO $pdo)
    {
        self::$pdo = $pdo;
    }
}
