# Setup
There are three short steps here.

## Step 1
Open ( or create ) the **.env** and add the following content to it:

```
Q_ENGINE=MYSQL
Q_DB_NAME=q
Q_DB_HOST=127.0.0.1
Q_DB_USER=user
Q_DB_PASS=secret
Q_MODELS=models
Q_MIGRATIONS=migrations
```

**Q_ENGINE** : This is the database engine you are using. For now only the values **MYSQL** and  **SQLITE** are supported

**Q_DB_NAME** : This is the name of your database

**Q_DB_HOST** : This is the database host name or IP address. For **SQLITE**, **This is can be omitted or left empty**.

**Q_DB_USER** : This is your **database username**. For **SQLITE**, **This is can be omitted or left empty**.

**Q_DB_PASS** : This is your **database password**. For **SQLITE**, **This is can be omitted or left empty**.

**Q_MODELS** : **Relative path** from project root to the ***file or folder*** where your model classes will be defined. If using a file, add the `.php` extension to it.

**Q_MIGRATIONS** : **Relative path** from project root to the ***folder*** where your generated migration files will be kept.


## Step 2
Then, setup your workspace like this :

- index.php
- models ( or models.php, if you are using a single file to store the model classes )
- migrations
- .env

Note though, that the names above are arbitrary and you can keep your models and migrations anywhere in your project as far as you specify that path in the .env file.

If you are using a framework, then create these folders wherever you please.

**migrations** is the empty folder where our migrations will be housed. **models.php or models** is the file or folder I intend to define my models in. Both of these are currently empty.


## Step 3

The last step is to find where to call the method `\Q\Orm\SetUp::main()` that will initialize the orm.

### For Framework Users
If you use a framework, find the bootstrap file, or create a middleware and call `\Q\Orm\SetUp::main()` method within it.

```php
<?php

use Q\Orm\SetUp;

Setup::main();

```

After this you can proceed to make queries anywhere with your project.

### For Non Framework Users

Use the code below as a template. The Q orm autoloads all the models. Assuming here that the `User` model has been defined.

```php
<?php
require "vendor/autoload.php";

use Q\Orm\SetUp;

Setup::main();

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
