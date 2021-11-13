# **Q Orm**

![github stars](https://img.shields.io/github/stars/paulrobert00/q?style=social)
![packagist stars](https://img.shields.io/packagist/stars/qorm/qorm)
![license](https://img.shields.io/github/license/paulrobert00/q)
![contributors](https://img.shields.io/github/contributors/paulrobert00/q)
![contributors](https://img.shields.io/github/languages/code-size/paulrobert00/q)
![downloads](https://img.shields.io/packagist/dm/qorm/qorm)

```php
<?php
//Fetch all the users that have at least one comment
$users = User::items()->filter(['id.in' => Comment::items()->project('user')])
    ->order_by('id desc')
    ->limit(10);
```
Will translate to  

```sql
SELECT ... FROM user WHERE id IN ( SELECT user_id FROM comment ) ORDER BY id DESC LIMIT 10
```
This is a simple orm that makes data modelling and migrations **extremely easy**. It auto detects changes in models when the user decides to make migrations, hence removing the need to manually write migrations.

Also, you can **actually re-use migration files**. That is, you can copy and zip your migrations folder and send it to another developer, and all they have to do is run one command and an entire copy of your schema is made. As a bonus, the migrations are written in php, so, no sql will be seen in your code.

So all you ever do is modify your models, ask the orm to detect the changes, then ask the orm to apply the changes. It's not your job to detect or keep track of changes you've made to your models. It's the orm's!. And for the first time, you can literally switch databases and the migrations will run without headaches.

This way, you can focus on modelling your data without ever having to convert it to a relational schema yourself.

A .env parser is included, so you don't need any external libraries to parse the .env file.

Also, the models are **autoloaded**, eliminating the need for an autoloader.

As long as you point to the correct file(s) or folders for models and migrations, you can leave the rest to the orm.

## WHY USE Q ORM?
1. You don't have to write migrations by hand.
1. Model definition and schema are all in one class.
1. An intuitive and powerful api for set operations.
1. A powerful and smooth query api, including joins and nested queries.
1. You can compose nested queries more easily.
1. Every operation is lazy. Except stated otherwise.
1. The n+1 problem does not exist.
1. Most operations are done on the database to avoid race conditions.
1. Migration files can be moved to another project and simply ran, irrespective of sql flavor.
1. You will end up with a well modelled data and properly designed database without feeling forced to do so.
1. All changes to model classes are automatically detected and migrations made. With this, you will hardly ever need to write your own migrations. And if you do, you have the ability to do so, even in raw sql.

## WHY .env FOR SETUP ?
This orm uses the .env file for configuration. This is for two reasons:  

1. Convention.
1. So that the cli tool can access the database credentials.

The benefits more than out weight whatever minimalist risks there may be. Also, this orm does not have any dependencies, the .env file is parsed by the library.

TLDR; Just create a file called .env in the root of your project if your setup is not using one already.

## INSTALLATION
Installation is done via composer.

`composer require qorm/qorm`


## SETUP
There are three short steps here.  

### STEP ONE
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

**Q_DB_USER** : This is your ***database username***. For **SQLITE**, **This is can be omitted or left empty**.  

**Q_DB_PASS** : This is your ***database password***. For **SQLITE**, **This is can be omitted or left empty**.  

**Q_MODELS** : **Relative path** from project root to the ***file or folder*** where your model classes will be defined. If using a file, add the `.php` extension to it. When using a folder, only model class files should be in the folder. Do not put dot files or configuration files in this folder.

**Q_MIGRATIONS** : **Relative path** from project root to the ***folder*** where your generated migration files will be kept. Only migration class files should be in this folder. Do not put dot files or configurations files.


### STEP TWO
Then, setup your workspace somewhat like this :  

- index.php
- models ( or models.php, if you are using a single file to store the model classes )
- migrations
- .env 

Note though, that the names above are arbitrary and you can keep your models and migrations anywhere in your project as far as you specify that path in the .env file.

If you are using a framework, then create these folders wherever you please.

**migrations** is the empty folder where our migrations will be housed. **models.php or models** is the file or folder I intend to define my models in. Both of these are currently empty.


### STEP THREE

The last step is to find where to call the method `\Q\Orm\SetUp::main()` that will initialize the orm. 

#### FOR FRAMEWORK USERS
If you are using a framework, find the bootstrap file, or create a middleware and call `\Q\Orm\SetUp::main()` method within it. 

```php
<?php

use Q\Orm\SetUp;

Setup::main();

```

After this you can proceed to make queries anywhere with your project.

#### FOR NON FRAMEWORK USERS

Use the code below as a template. The Q orm autoloads all the models. Assuming here that the `User` model has been defined.

```php
<?php

require "vendor/autoload";

use Q\Orm\SetUp;

Setup::main();

// The rest of your code goes here

```

That's it for the setup. From now on, we'll assume you already have a setup and have the `\Q\Orm\SetUp::main()` method called already.

## QUICK TUTORIAL
For a quick tutorial, click [ here ](docs/tutorial/start.md)

## IN-DEPTH TUTORIAL
For a more complete and in-depth tutorial, click [ here ](docs/parts/toc.md)
      
