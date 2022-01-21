# Migrations

Now we have a model (or models), we need to alter our database to reflect the new changes.

We perform migrations any time changes that we intend to stay permanent are made to our models. These changes include creating the models for the first time, or even deleting model class files.

The cycle of migration is as follows : 

1. Perform a change or changes.
2. Make migrations for the changes.
3. Commit or migrate the migrations created.
4. If commit was unintended, rollback.

## PERFORM A CHANGE OR CHANGES
We have already performed a change by creating a new model. Note however that any change to either the model class name or the schema method qualifies as a 'change'.

## MAKE MIGRATIONS FOR THE CHANGES
Open a terminal and go to the root of your project. Then run this command.

`php vendor/bin/qorm makemigrations`

You should see a message like  
`Migration Migration0001 successfully created`  

Now, if you check the migrations folder, there should be a file named `Migration0001.php`.  
 
Contained in this file are the changes that were detected in your models.  

Do not directly modify generated migration files.

## COMMIT THE MIGRATION
This step commits or migrates the generated migration file, so that the changes will reflect in the database.  

To do this, run  

`php vendor/bin/qorm migrate`  

You should see a message like   

`Applied migration Migration0001`

## ROLLBACK
This enables you do undo the commit from the previous section. 

Note, however, that data lost from the previous step will not be recovered.

Rolling back only restores the schema to it's previous state.

So, incase you are not satisfied with the changes you made, you can go back to the previous state by running  

`php vendor/bin/qorm rollback`  

## SUBSEQUENTLY

If you perform another change on your models, like renaming it or creating another model, or adding a new field, just go through the above steps. The changes made will be detected accordingly and migration files will be incrementally generated.

## FINALLY
If you check your database now, it should have two tables called `author` and `post`.

To learn more about migrations in depth, refer to [ this page ](./../parts/cli.md).

---
[ Previous : Start](start.md) | [ Next : Crud ](crud.md)


