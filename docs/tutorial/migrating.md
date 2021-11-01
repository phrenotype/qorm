# Migrations

We perform migrations any time changes that we wish to stay permanent are made to our models. These changes include creating the models for the first time.

The cycle of migration is as follows : 

1. Perform a change or changes.
2. Make migrations for the changes.
3. Commit or migrate the migrations created.

## PERFORM A CHANGE OR CHANGES
We have already performed a change by creating new models

## MAKE MIGRATIONS FOR THE CHANGES
Open a terminal and go to the root of your project. Then run this command.

`php vendor/bin/qorm makemigrations`

You should see a message like  
`Migration Migration0001 successfully created`  

Now, if you check the migrations folder, there should be a file named `Migration0001.php`.  
 
Contained in this file are the changes that were detected in your models.

## COMMIT THE MIGRATION
The last step is to commit or migrate the generated migration file, so that the changes will reflect in the database.  

To do this, run  

`php vendor/bin/qorm migrate`  

You should see a message like   

`Applied migration Migration0001`

## SUBSEQUENTLY

If you perform another change on your models, like renaming it or creating another model, or adding a new field, just go through the above steps. The changes made will be detected accordingly and migration files will be incrementally generated.

## FINALLY
If you check your database now, it should have two tables called `author` and `post`.

To learn more about migrations in depth, refer to [ this page ](./../parts/cli.md).

---
[ Previous : Start](start.md) | [ Next : Crud ](crud.md)


