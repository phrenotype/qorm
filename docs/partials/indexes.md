# Index  
This class if found at `Q\Orm\Migration\Index`. It represents a database index.

# Attributes  
`type`  
This indicates the type of index. It has to be one of the [constants](#constants) below.  

`field`  
This is the actual database table field name the index if created on.

# Constants

`Index::INDEX`  
This represents a regular index.  

`Index::UNIQUE`  
This represents a `unique` index.  

`Index::PRIMARY_KEY`  
This represents a primary key index.
