<?php

namespace Tests;

use Q\Orm\Engines\CrossEngine;
use Q\Orm\Helpers;
use Tests\Models\ChildNullable;
use Tests\Models\PeculiarUser;

class FKNullabilityTest extends QormTestCase
{
    public function testNullableFKWithCustomPK()
    {
        // We want to verify that ChildNullable's 'parent' field (which targets PeculiarUser's 'peculiar' PK)
        // has the 'null' property set to true, as defined in its schema.

        $tableName = Helpers::modelNameToTableName(Helpers::getShortName(ChildNullable::class));
        $table = CrossEngine::tableFromModels($tableName);

        $parentField = null;
        foreach ($table->fields as $field) {
            if ($field->name === 'parent') { // Field name is 'parent'
                $parentField = $field;
                break;
            }
        }

        $this->assertNotNull($parentField, "Could not find 'parent' field in generated table");
        $this->assertTrue($parentField->null, "FK field '{$parentField->name}' should be nullable");
    }

    public function testFKDoesNotMutateParentPK()
    {
        // Verify that after generating the child table, the parent's PK column name hasn't been changed.
        $childName = Helpers::modelNameToTableName(Helpers::getShortName(ChildNullable::class));
        CrossEngine::tableFromModels($childName);

        $parentName = Helpers::modelNameToTableName(Helpers::getShortName(PeculiarUser::class));
        $parentTable = CrossEngine::tableFromModels($parentName);

        $pkField = null;
        foreach ($parentTable->fields as $field) {
            if ($field->name === 'peculiar') {
                $pkField = $field;
                break;
            }
        }

        $this->assertNotNull($pkField, "Parent PK field 'peculiar' should exist and be named 'peculiar'");
        $this->assertEquals('peculiar', $pkField->name);
    }
}
