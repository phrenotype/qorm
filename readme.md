# **Q Orm**

![license](https://img.shields.io/github/license/phrenotype/qorm)
![contributors](https://img.shields.io/github/contributors/phrenotype/qorm)
![contributors](https://img.shields.io/github/languages/code-size/phrenotype/qorm)

```php
<?php
$users = User::items()->filter(['id.in' => Comment::items()->project('user')])
    ->order_by('id desc')
    ->limit(10)
    ->all();
```


```sql
SELECT ... FROM user WHERE id IN ( SELECT user_id FROM comment ) ORDER BY id DESC LIMIT 10
```  

This is a simple orm that makes quering and generating migrations extremely easy. It **auto detects** changes in models when the user decides to make migrations, hence **removing the need to manually write migrations**.

It provides all the basic pieces need to craft almost any SQL query. You end up with one query all written in SQL without having to do in PHP tasks that would have otherwise been done by SQL.

You can **automatically generate and re-use migration files**. That is, you can copy and zip your migrations folder and send it to another developer, and all they have to do is run one command and an entire copy of your schema is made. As a bonus, the migrations are written in php, so, no sql will be seen in your code (unless you want it, of course).

So all you ever do is modify your models, ask the orm to detect the changes, and when you are ready, apply the changes. It's not your job to detect or keep track of changes you've made to your models.

This way, you can focus on modelling your data without ever having to convert it to a relational schema yourself.

A .env parser is included, so you don't need any external libraries to parse the .env file.

Also, the models are **autoloaded**. You don't need to include the folders in your autoloader.

As long as you point to the correct file(s) or folders for models and migrations, you can leave the rest to the orm.

## Why Use Q Orm ?

If you've used the django framework, you'll notice that when you `makemigrations`, the django orm automatically detects any changes you've made to your models and creates a migration file based on that.

Unfortunately, no major php orm has anything of that sorts. One has to manually define migrations based on their changes. Well, we offer that as well, but for *most* of your migrations, we'll **correctly** detect the change and let you apply the migration at your own time.

With this, you can zip your migrations folder and send it to another developer and with a single command, they will have an exact replica of your database. So, migration files are **auto-generate once, run everywhere**.  

1. You don't have to write migrations by hand.
1. Model definition and schema are all in one class.
1. A powerful and smooth query api.
1. Full support for nested queries, joins and **all** set operations, irrespective of sql dialect.
1. You can compose nested queries more easily.
1. Every operation is lazy. Except stated otherwise.
1. The n+1 problem does not exist.
1. Every update, insertion, or deletion is done via transactions.
1. All operations are done on the database to speed things up and avoid race conditions.
1. Migration files can be moved to another project and simply ran, irrespective of sql flavor.
1. You will end up with a well modelled data and properly designed database without feeling forced to do so.
1. All changes to model classes are automatically detected. With this, you will hardly ever need to write your own migrations. And if you do, you have the ability to do so, even in raw sql.

## Why .env For Setup ?
This orm uses the .env file for configuration. This is for two reasons:  

1. Convention.
1. So that the cli tool can access the database credentials.

The benefits more than out weight whatever minimalist risks there may be. Also, this orm does not have any dependencies, the .env file is parsed by the library.

TLDR; Just create a file called .env in the root of your project if you do not already have one.

## Install

`composer require qorm/qorm`

## Tutorials And Setup
For the complete **setup** and **tutorials** visit [this page](docs/setup.md).  

## Database Support
For now, this project only supports `MYSQL` (`MariaDB`), and `SQLITE`. Work is in progress to add support for `POSTGRESQL`.

## Contribution
To contribute, contact the email below.

## Contact
**Twitter** : [@phrenotyper](https://twitter.com/phrenotyper)
**Email** : paul.contrib@gmail.com  

