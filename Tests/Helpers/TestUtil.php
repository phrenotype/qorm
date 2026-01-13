<?php

namespace Tests\Helpers;

use Q\Orm\Migration\SchemaBuilder;
use Q\Orm\Migration\Schema;
use Q\Orm\Field;
use Q\Orm\Migration\Column;
use Q\Orm\Migration\Index;
use Q\Orm\Migration\ForeignKey;

class TestUtil
{
    /**
     * Creates a table for a given model using QORM's Schema builder, ensuring strict adherence to the model definition.
     * This avoids raw SQL and uses the standard migration path logic.
     *
     * @param string $modelClass The fully qualified class name of the model.
     */
    public static function createTableFromModel(string $modelClass)
    {
        $schema = $modelClass::schema();
        $tableName = (new \ReflectionClass($modelClass))->getShortName(); // Or use a proper table name resolver if available

        // Convert PascalCase to snake_case for table name if needed, matching QORM conventions
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));

        $operation = Schema::create($tableName, function (SchemaBuilder $tb) use ($schema) {

            // Always add ID if it's the standard QORM model pattern
            // SQLite requires explicit INTEGER PRIMARY KEY AUTOINCREMENT for auto-increment behavior
            // The SchemaBuilder's id() method uses bigint, which maps to BIGINT, causing the error.
            // We manually define it as integer here to ensure SQLite compatibility while relying on QORM's abstraction mapping if needed.
            // But QORM's SchemaBuilder::id() does: column('id', 'bigint', ...). 
            // In SQLite, BIGINT PRIMARY KEY AUTOINCREMENT is invalid. It MUST be INTEGER.

            // Checking if we are in SQLite mode to apply correct type, or generic integer which maps to INTEGER.
            $tb->column('id', 'integer', ['null' => false, 'auto_increment' => true, 'unsigned' => true])->primary('id');

            foreach ($schema as $name => $field) {
                if ($name === 'id')
                    continue; // Skip ID checking as we added it explicitly or it's handled

                $col = $field->column;

                // Determine the correct column name (respect override)
                $finalColName = $col->name ?? $name;

                // If it's a relationship field (Foreign Key), we need to handle it differently
                if ($field->isFk()) {
                    // For relations, use the explicitly set column name (e.g. user_id) 
                    // or default to the key name.

                    $tb->integer($finalColName, [
                        'name' => $finalColName,
                        'type' => 'bigint', // Standard FK type
                        'unsigned' => true,
                        'null' => $col->null ?? true
                    ]);
                    continue;
                }

                // Map standard fields
                $def = [
                    'name' => $finalColName,
                    'type' => $col->type,
                    'null' => $col->null ?? true,
                    'default' => $col->default ?? null,
                    'size' => $col->size ?? null,
                    'unsigned' => $col->unsigned ?? false
                ];

                // Clean up nulls to match typical definition arrays
                $def = array_filter($def, function ($v) {
                    return !is_null($v);
                });

                // Resolve Closure defaults (e.g., DateNow) to static values for SQL generation
                if (isset($def['default'])) {
                    if ($def['default'] instanceof \Closure) {
                        $def['default'] = $def['default']();
                    }
                    // Boolean normalization for SQL (false -> 0, true -> 1)
                    if (is_bool($def['default'])) {
                        $def['default'] = (int) $def['default'];
                    }
                }

                switch ($col->type) {
                    case Field::CHAR:
                        $tb->string($name, $def);
                        break;
                    case Field::INTEGER:
                        $tb->integer($name, $def);
                        break;
                    case Field::TEXT:
                        $tb->text($name, $def);
                        break;
                    case Field::BOOL:
                        $tb->boolean($name, $def);
                        break;
                    case Field::DATETIME:
                        $tb->datetime($name, $def);
                        break;
                    case Field::DATE:
                        $tb->date($name, $def);
                        break;
                    case Field::FLOAT:
                        $tb->float($name, $def);
                        break;
                    case Field::DECIMAL:
                        $tb->decimal($name, $def);
                        break;
                    case Field::ENUM:
                        // SQLite doesn't support ENUM natively, map to TEXT or VARCHAR
                        $def['type'] = 'text';
                        $tb->string($name, $def);
                        break;
                }
            }
        });

        // execute the operation's SQL
        $operation->runSql();
    }
}
