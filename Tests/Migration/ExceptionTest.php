<?php

namespace Tests\Migration;

use Tests\QormTestCase;
use Q\Orm\Helpers;

class ExceptionTest extends QormTestCase
{
    // Inherits setUpBeforeClass from QormTestCase

    public function testRunAsTransactionThrowsException()
    {
        // This test documents the FIXED behavior.
        // It asserts that an exception IS thrown for bad SQL.

        // Bad SQL
        $sql = "SELECT * FROM non_existent_table_forced_error";

        $this->expectException(\PDOException::class);

        ob_start(); // Buffer output to keep test clean
        Helpers::runAsTransaction($sql);
        ob_end_clean();
    }
}
