<?php

/**
 * Configuration information for QOrm.
 */
return  [

    // SQL dialect. SQLITE and MYSQL are supported for now.
    "Q_ENGINE"=>"SQLITE",

    // Database Name. For sqlite, this will be the name of the database file.
    "Q_DB_NAME"=>"qorm",

    // Database Host. Leave blank if using sqlite.
    "Q_DB_HOST"=>"",

    // Database User. Leave blank if using sqlite.
    "Q_DB_USER"=>"",

    // Database Password. Leave blank if using sqlite.
    "Q_DB_PASS"=>"",

    // Models Folder.
    "Q_MODELS"=>"models/StudyModels",

    // Migrations Folder.
    "Q_MIGRATIONS"=>"migrations",
    
    // Epoch for unique id (64-bit integer) generation.
    "Q_PECULIAR_EPOCH"=>"1640991600000",

    // Custom server id (0 - 511).
    "Q_PECULIAR_CUSTOM_ID"=>"3",

];

