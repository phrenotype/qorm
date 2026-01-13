<?php

namespace Q\Orm;

use Q\Orm\Cli\Bin;
use Q\Orm\Migration\Introspector;


/**
 * The Integrity class tries to make sure that the models are in order
 */
class Integrity
{
    /**
     * @deprecated Use refuseAmbiguousModels() instead
     */
    public static function refuseDuplicateAttributes()
    {
        self::refuseAmbiguousModels();
    }

    /**
     * Build a type signature array for a table.
     * Signature format: "{type}:{size}:{null}:{unsigned}:{fk_target}"
     */
    private static function buildTypeSignature($table): array
    {
        return array_map(function ($c) use ($table) {
            $fkTarget = '';
            foreach ($table->foreignKeys as $fk) {
                if ($fk->field === $c->name) {
                    $fkTarget = $fk->refTable;
                    break;
                }
            }
            return sprintf(
                "%s:%s:%s:%s:%s",
                $c->type,
                $c->size,
                $c->null ? '1' : '0',
                $c->unsigned ? '1' : '0',
                $fkTarget
            );
        }, $table->fields);
    }

    public static function refuseAmbiguousModels()
    {
        $models = Introspector::modelsToArrayOfTables();
        foreach ($models as $table) {
            foreach ($models as $other) {
                //Don't compare the table against itself
                if ($table->name !== $other->name) {

                    // Check 1: Duplicate attribute names (existing)
                    $attributes = array_map(function ($c) {
                        return $c->name;
                    }, $table->fields);
                    $other_attributes = array_map(function ($c) {
                        return $c->name;
                    }, $other->fields);
                    sort($attributes);
                    sort($other_attributes);
                    if (count($attributes) === count($other_attributes) && $attributes == $other_attributes) {
                        Bin::line("$table->name and $other->name seem like same model. They have exactly same attributes. Please remove one or consider adding or changing attribute names.", '0;31;47m');
                        die;
                    }

                    // Check 2: Duplicate type signatures (New)
                    $signature = self::buildTypeSignature($table);
                    $other_signature = self::buildTypeSignature($other);

                    sort($signature);
                    sort($other_signature);

                    if (count($signature) === count($other_signature) && $signature == $other_signature) {
                        Bin::line("$table->name and $other->name seem like same model. They have exactly same type signatures. Please remove one or consider adding a unique field to one.", '0;31;47m');
                        die;
                    }
                }
            }
        }
    }

    public static function phpVersionCheck()
    {
        if (version_compare(PHP_VERSION, '7.0.0') == -1) {
            throw new \Error("Minimum version of php required is 7.0.0.");
        }
        die;
    }
}
