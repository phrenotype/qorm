# Setup
There are three short steps here.

## Step 1
Open ( or create ) the **qorm.config.php** in your document root (or anywhere you like) and add the following content to it:

```php
<?php

/**
 * Configuration information for QOrm.
 */
$QOrmConfig = [

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
    "Q_MODELS"=>"models",

    // Migrations Folder.
    "Q_MIGRATIONS"=>"migrations",
    
    // Epoch for unique id (64-bit integer) generation.
    "Q_PECULIAR_EPOCH"=>"1640991600000",

    // Custom server id (0 - 511).
    "Q_PECULIAR_CUSTOM_ID"=>"3",

];

return $QOrmConfig;

```

**Q_ENGINE** : This is the database engine you are using. For now only the values **MYSQL** and  **SQLITE** are supported

**Q_DB_NAME** : This is the name of your database

**Q_DB_HOST** : This is the database host name or IP address. For **SQLITE**, **This is can be omitted or left empty**.

**Q_DB_USER** : This is your **database username**. For **SQLITE**, **This is can be omitted or left empty**.

**Q_DB_PASS** : This is your **database password**. For **SQLITE**, **This is can be omitted or left empty**.

**Q_MODELS** : **Relative path** from project root to the ***file or folder*** where your model classes will be defined. If using a file, add the `.php` extension to it.

**Q_MIGRATIONS** : **Relative path** from project root to the ***folder*** where your generated migration files will be kept.

**Q_PECULIAR_EPOCH** : Epoch in milliseconds for the peculiar id generator. Don't change this once you have launched your project.
**Q_PECULIAR_CUSTOM_ID** : Any number from 0 to 511 (inclusive) that will be used to uniquely create ids for this server.

## Step 2
Then, setup your workspace like this :

- index.php
- models ( or models.php, if you are using a single file to store the model classes )
- migrations
- qorm.config.php

Note though, that the names above are arbitrary and you can keep your models and migrations anywhere in your project as far as you specify that path in the **qorm.config.php** file.

If you are using a framework, then create these folders wherever you please.

**migrations** is the empty folder where our migrations will be housed. **models.php or models** is the file or folder I intend to define my models in. Both of these are currently empty.


## Step 3

The last step is to find where to call the method `\Q\Orm\SetUp::main()` that will initialize the orm.

You need to pass only one required argument, the path to your configuration file. It can be anywhere in your filesystem. The file must always be called **qorm.config.php**.

### For Framework Users
If you use a framework, find the bootstrap file, or create a middleware and call `\Q\Orm\SetUp::main()` method within it.

```php
<?php

use Q\Orm\SetUp;

SetUp::main(__DIR__ . "/qorm.config.php");

// The rest of your code goes here

```

After this you can proceed to make queries anywhere with your project.

### For Non Framework Users

Use the code below as a template. The Q orm autoloads all the models.
```php
<?php
require "vendor/autoload.php";

use Q\Orm\SetUp;

SetUp::main(__DIR__ . "/qorm.config.php");

// The rest of your code goes here
```

That's it for the setup. From now on, we'll assume you already have a setup and have the `\Q\Orm\SetUp::main()` method called already.

## What Next ?

### Quick Tutorial
For a quick tutorial on basic **CRUD** operations ( Create, Read, Update, Delete ), click **[ here ](tutorial/start.md)**.

### In-Depth Tutorial ( Highly Recommended )

- [ Creating Models ](parts/creating_models.md )
- [ Migrating Models ](parts/migrating_models.md )
- [ Making Queries ](parts/making_queries.md )
- [ Query Filters ](parts/query_filters.md )
- [ Model Relationships ](parts/relationships.md )
- [ The Command Line Interface ](parts/cli.md )
- [ Defaults ](parts/defaults.md )
- [ Peculiar Ids ](parts/peculiar.md)
- [ UUID's ](parts/uuid.md )
- [ Joins ](parts/joins.md )
- [ Grouping Aggregates ](parts/grouping.md )
- [ Set Operations ](parts/sets.md )
- [ SubQueries ](parts/subqueries.md )
