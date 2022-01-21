<?php

namespace Q\Orm\Cli;

use Q\Orm\Migration\MigrationMaker;
use Q\Orm\Migration\SchemaToModel;
use Q\Orm\ModelGenerator;
use Q\Orm\SetUp;

$app = new Bin;


$app->register('makemigrations', function ($args) {
    MigrationMaker::make();
});

$app->register('makemigrations\s+\w+', function ($args) {
    if (($args[1] ?? false) && $args[1] === 'blank') {
        MigrationMaker::make(true);
    } else {
        Bin::line("Usage: makemigrations blank", FG::RED, BG::LIGHT_GRAY);
    }
});


$app->register('migrations', function ($args) {
    MigrationMaker::migrations();
});

$app->register('migrate', function ($args) {
    MigrationMaker::migrate();
});

$app->register('migrate\s+\w+', function ($args) {
    if (($args[1] ?? false)) {
        MigrationMaker::migrate($args[1]);
    } else {
        Bin::line("Usage: migrate [MigrationName]", FG::RED, BG::LIGHT_GRAY);
    }
});



$app->register('rollback', function ($args) {
    MigrationMaker::rollback();
});

$app->register('rollback\s+\w+', function ($args) {
    if (($args[1] ?? false)) {
        MigrationMaker::rollback($args[1]);
    } else {
        Bin::line("Usage: rollback [MigrationName]", FG::RED, BG::LIGHT_GRAY);
    }
});



$app->register('inspect', function ($args) {
    SchemaToModel::main();
});

$app->register('inspect\s+\w+', function ($args) {
    if (($args[1] ?? false)) {
        SchemaToModel::main($args[1]);
    } else {
        Bin::line("Usage: inspect [ModelLocation]", FG::RED, BG::LIGHT_GRAY);
    }
});


$app->register('create\s+\w+', function ($args) {
    if (($args[1] ?? false)) {
        ModelGenerator::generate($args[1]);
    } else {
        Bin::line("Usage: create [ModelName]", FG::RED, BG::LIGHT_GRAY);
    }
});


$itsMe = (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME']));

/* If you call me directly, or include me in a file called 'qorm' */
$itsMe = $itsMe || (in_array(basename($_SERVER['SCRIPT_FILENAME'], '.php'), ['qorm']));


if ($itsMe) {
    $app->run($argv, function () {
        Setup::main();
        if (SetUp::$engine === Setup::SQLITE) {
            Bin::line("NOTE : In SQLITE, SIZE does not matter ! :)", FG::BROWN, BG::BLACK);
            Bin::line('');
        }
    });
}
