# CLI COMMANDS  
**[ Table Of Contents](toc.md)**

This is the most powerful and important feature of this orm. In fact, it's the very **reason it was made**.

If you've used django before, you'll notice that when you `makemigrations`, the django orm automatically detect any changes you've made to your models and creates a migration file based on that.

Unfortunately, no major php framework has anything of the sorts. One has to manually define migrations based on his/her changes. Well, we offer that as well, but for *most* of your migrations, we'll correctly detect the change and let you apply the migration at your own time.

With this, you can zip your migrations folder and send it to another developer and with a single command, they will have an exact replica of your database. So, migration files are **generate once, run everywhere**.


## COMMANDS
To see all the available commands, run  

`php vendor/bin/qorm`

## ALL MIGRATIONS
To see all the migrations that have been created, run  

`php vendor/bin/qorm migrations`

## CREATING MIGRATIONS
After defining or making changes your models, go to the terminal and run  

`php vendor/bin/qorm makemigrations`  

In the *migrations* folder, you'll notice that a new file has been added with the format `Migration000[digit(s)].php`, based on the changes you've made to the models.

## CREATING A BLANK MIGRATION
To create a blank migration, go to the terminal and run

`php vendor/bin/qorm makemigrations blank`

A blank migration file will be created for you. Edit the file to create your custom migration.

## TO APPLY A MIGRATION
To apply the last created migration, go to the terminal and run

`php vendor/bin/qorm migrate`

Now your changes have been applied to the database.

## ROLLBACK
To rollback the last applied migration, go to the terminal and run:

`php vendor/bin/qorm rollback`

## STEPPING

To apply all migrations down to a particular migration, Go to the terminal and run

`php vendor/bin/qorm migrate MigrationName`

Note that the .php extension is not added to the migration name.


To rollback migrations up to a particular migration. Go to the terminal and run:

`php vendor/bin/qorm rollback MigrationName`

Again, note that the .php extension is not added to the migration name.

----
**[Previous Part : Model Relationships](relationships.md)** | **[Next Part : Defaults](defaults.md)**