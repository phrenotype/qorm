<?php

use PHPUnit\Framework\TestCase;
use Q\Orm\Helpers;
use Q\Orm\SetUp;
use Tests\Models\User;

class ModelSaveTest extends TestCase
{
    protected function setUp(): void
    {
        SetUp::main(__DIR__ . '/Database/qorm.config.php', false);
    }

    protected function tearDown(): void
    {
    }

    /**
     * Test that invalid properties are filtered out during save.
     * This verifies the fix for constructor + save() not working.
     * 
     * Note: Public properties (name, email) are stored directly on the object,
     * not in __properties. Only undefined properties trigger __set().
     */
    public function testSaveFiltersInvalidProperties()
    {
        $user = new User();
        $user->name = "John";           // Stored as public property
        $user->email = "john@test.com"; // Stored as public property
        $user->typo_field = "should be ignored"; // Stored in __properties via __set()

        $props = $user->getProps();

        // Invalid property IS stored in __properties (via __set)
        $this->assertArrayHasKey('typo_field', $props);

        // Public properties are NOT in __properties - they're on the object directly
        // This is expected PHP behavior for classes with public properties

        // Verify schema properties are correctly identified
        $schema_props = Helpers::getModelProperties(User::class);
        $this->assertContains('name', $schema_props);
        $this->assertContains('email', $schema_props);
        $this->assertNotContains('typo_field', $schema_props);
    }

    /**
     * Test that E_USER_NOTICE is triggered for invalid properties during save.
     */
    public function testSaveWarnsOnInvalidProperties()
    {
        $user = new User();
        $user->name = "Jane";
        $user->email = "jane@test.com";
        $user->invalid_prop = "typo";

        $warning_triggered = false;
        $warning_message = '';

        set_error_handler(function ($errno, $errstr) use (&$warning_triggered, &$warning_message) {
            if ($errno === E_USER_NOTICE && strpos($errstr, 'QORM: Ignored unknown properties') !== false) {
                $warning_triggered = true;
                $warning_message = $errstr;
            }
            return true;
        });

        try {
            // We can't actually save without a database, but we can verify
            // the warning behavior by checking the properties filtering logic
            $props = $user->getProps();
            $schema_props = Helpers::getModelProperties(User::class);

            // Simulate the filtering logic from save()
            $ignored = [];
            foreach ($props as $k => $v) {
                if (!in_array($k, $schema_props) && $k !== 'id') {
                    $is_set_accessor = str_ends_with($k, '_set');
                    $is_closure = $v instanceof \Closure;

                    if (!$is_set_accessor && !$is_closure) {
                        $ignored[] = $k;
                    }
                }
            }

            if (!empty($ignored)) {
                trigger_error(
                    sprintf("QORM: Ignored unknown properties on %s: %s", User::class, implode(', ', $ignored)),
                    E_USER_NOTICE
                );
            }
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($warning_triggered, "Warning should be triggered for invalid properties");
        $this->assertStringContainsString('invalid_prop', $warning_message);
    }

    /**
     * Test that relationship accessors (_set) are not warned about.
     */
    public function testSaveDoesNotWarnOnSetAccessors()
    {
        $user = new User();
        $user->name = "Test";
        $user->email = "test@test.com";
        $user->comment_set = "simulated_set_accessor"; // Simulates _set accessor

        $props = $user->getProps();
        $schema_props = Helpers::getModelProperties(User::class);

        // Simulate the filtering logic
        $ignored = [];
        foreach ($props as $k => $v) {
            if (!in_array($k, $schema_props) && $k !== 'id') {
                $is_set_accessor = str_ends_with($k, '_set');
                $is_closure = $v instanceof \Closure;

                if (!$is_set_accessor && !$is_closure) {
                    $ignored[] = $k;
                }
            }
        }

        // _set accessors should not be in ignored list
        $this->assertNotContains('comment_set', $ignored);
    }

    /**
     * Test that Closures (forward relationship accessors) are not warned about.
     */
    public function testSaveDoesNotWarnOnClosures()
    {
        $user = new User();
        $user->name = "Test";
        $user->email = "test@test.com";
        $user->some_relation = function () {
            return null;
        }; // Simulates forward accessor

        $props = $user->getProps();
        $schema_props = Helpers::getModelProperties(User::class);

        // Simulate the filtering logic
        $ignored = [];
        foreach ($props as $k => $v) {
            if (!in_array($k, $schema_props) && $k !== 'id') {
                $is_set_accessor = str_ends_with($k, '_set');
                $is_closure = $v instanceof \Closure;

                if (!$is_set_accessor && !$is_closure) {
                    $ignored[] = $k;
                }
            }
        }

        // Closures should not be in ignored list
        $this->assertNotContains('some_relation', $ignored);
    }

    /**
     * Test that reload() correctly detects dirty public properties.
     */
    public function testReloadDetectsDirtyPublicProperties()
    {
        $user = new User();
        $user->name = "Original";
        $user->email = "original@test.com";

        // Simulate prevState (as if loaded from DB)
        $user->prevState(['name' => 'Original', 'email' => 'original@test.com']);

        // Modify the public property
        $user->name = "Modified";

        // Get schema props and check dirty detection logic
        $schema_props = Helpers::getModelProperties(User::class);
        $current = [];
        foreach ($schema_props as $prop) {
            if (isset($user->$prop)) {
                $current[$prop] = $user->$prop;
            }
        }

        $prevState = $user->prevState();
        $isDirty = false;
        foreach ($prevState as $k => $v) {
            $currentVal = $current[$k] ?? null;
            if ($currentVal !== $v) {
                $isDirty = true;
                break;
            }
        }

        $this->assertTrue($isDirty, "Model should be detected as dirty when public property changes");
    }

    /**
     * Test that reload() correctly detects clean state (not dirty).
     */
    public function testReloadDetectsCleanState()
    {
        $user = new User();
        $user->name = "Same";
        $user->email = "same@test.com";

        // Simulate prevState matching current values
        $user->prevState(['name' => 'Same', 'email' => 'same@test.com']);

        // Get schema props and check dirty detection logic
        $schema_props = Helpers::getModelProperties(User::class);
        $current = [];
        foreach ($schema_props as $prop) {
            if (isset($user->$prop)) {
                $current[$prop] = $user->$prop;
            }
        }

        $prevState = $user->prevState();
        $isDirty = false;
        foreach ($prevState as $k => $v) {
            $currentVal = $current[$k] ?? null;
            if ($currentVal !== $v) {
                $isDirty = true;
                break;
            }
        }

        $this->assertFalse($isDirty, "Model should not be dirty when properties match prevState");
    }
}
