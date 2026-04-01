<?php
/**
 * ModuleSettings Tests
 *
 * Tests for schema-driven module settings:
 * - Schema loading from module directory
 * - Defaults from tabs
 * - Migration with callback
 * - Overrides-only saving with unknown keys preservation
 * - get/set/has methods
 * - Module without schema file
 */

require_once __DIR__ . '/../core/bootstrap.php';

class ModuleSettingsTest
{
    private $testModule = 'tmig_testmod';
    private $results = array();

    public function __construct()
    {
        // Ensure directories exist
        $moduleDir = MANTRA_MODULES . '/' . $this->testModule;
        if (!is_dir($moduleDir)) {
            mkdir($moduleDir, 0755, true);
        }

        $settingsDir = MANTRA_CONTENT . '/settings';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }
    }

    public function __destruct()
    {
        // Clean up test module directory
        $moduleDir = MANTRA_MODULES . '/' . $this->testModule;
        if (is_dir($moduleDir)) {
            $files = glob($moduleDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($moduleDir);
        }

        // Clean up test settings files
        $patterns = array(
            MANTRA_CONTENT . '/settings/' . $this->testModule . '.json',
            MANTRA_CONTENT . '/settings/tmig_noschema.json',
        );
        foreach ($patterns as $p) {
            if (file_exists($p)) {
                @unlink($p);
            }
        }
    }

    public function run()
    {
        echo "Running ModuleSettings Tests...\n\n";

        // Schema and defaults
        $this->testSchemaLoading();
        $this->testDefaultsFromTabs();
        $this->testDefaultsForNestedPaths();

        // Migration
        $this->testMigrationWithCallback();
        $this->testMigrationFromZero();
        $this->testMigrationNotReapplied();

        // get/set/has
        $this->testGetSetHas();
        $this->testSetMultiple();

        // Saving
        $this->testOverridesOnlySaving();
        $this->testUnknownKeysPreserved();

        // Edge cases
        $this->testModuleWithoutSchema();
        $this->testMissingSettingsFile();

        $this->printResults();
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            $this->results[] = array('status' => 'PASS', 'message' => $message);
            echo "  PASS: $message\n";
        } else {
            $this->results[] = array('status' => 'FAIL', 'message' => $message);
            echo "  FAIL: $message\n";
        }
    }

    // ---------------------------------------------------------------
    // Schema and defaults
    // ---------------------------------------------------------------

    private function testSchemaLoading()
    {
        echo "\n--- Schema loaded from module directory ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'api_key', 'type' => 'text', 'default' => ''),
                    ),
                ),
            ),
        ));

        $ms = new Module\ModuleSettings($this->testModule);
        $schema = $ms->schema();

        $this->assert(is_array($schema), 'Schema loaded as array');
        $this->assert($schema['version'] === 1, 'Schema version is 1');
        $this->assert(!empty($schema['tabs']), 'Schema has tabs');
    }

    private function testDefaultsFromTabs()
    {
        echo "\n--- Defaults applied from tab fields ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'api_key', 'type' => 'text', 'default' => ''),
                        array('path' => 'enabled', 'type' => 'toggle', 'default' => true),
                        array('path' => 'max_items', 'type' => 'number', 'default' => 25),
                    ),
                ),
            ),
        ));

        // No settings file on disk
        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assert($ms->get('api_key') === '', 'Empty string default applied');
        $this->assert($ms->get('enabled') === true, 'Boolean default applied');
        $this->assert($ms->get('max_items') === 25, 'Numeric default applied');
    }

    private function testDefaultsForNestedPaths()
    {
        echo "\n--- Defaults for nested dot-paths ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'advanced',
                    'title' => 'Advanced',
                    'fields' => array(
                        array('path' => 'cache.enabled', 'type' => 'toggle', 'default' => false),
                        array('path' => 'cache.ttl', 'type' => 'number', 'default' => 3600),
                    ),
                ),
            ),
        ));

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);

        $this->assert($ms->get('cache.enabled') === false, 'Nested boolean default applied');
        $this->assert($ms->get('cache.ttl') === 3600, 'Nested numeric default applied');
    }

    // ---------------------------------------------------------------
    // Migration
    // ---------------------------------------------------------------

    private function testMigrationWithCallback()
    {
        echo "\n--- Migration with callback ---\n";

        $schemaPath = MANTRA_MODULES . '/' . $this->testModule . '/settings.schema.php';
        file_put_contents($schemaPath, <<<'PHP'
<?php
return array(
    'version' => 2,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General',
            'fields' => array(
                array('path' => 'tracker_id', 'type' => 'text', 'default' => ''),
            ),
        ),
    ),
    'migrate' => function($data, $from, $to) {
        // v1->v2: rename analytics_id -> tracker_id
        if ($from < 2 && isset($data['analytics_id'])) {
            $data['tracker_id'] = $data['analytics_id'];
            unset($data['analytics_id']);
        }
        $data['schema_version'] = $to;
        return $data;
    }
);
PHP
        );

        $this->writeSettingsFile(array(
            'analytics_id' => 'UA-12345',
            'schema_version' => 1
        ));

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assert(!$ms->has('analytics_id'), 'Old field removed by migration');
        $this->assert(
            $ms->get('tracker_id') === 'UA-12345',
            'Value migrated to new field name'
        );

        // Verify on disk
        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);
        $this->assert($raw['schema_version'] === 2, 'schema_version bumped on disk');
    }

    private function testMigrationFromZero()
    {
        echo "\n--- Migration from v0 (no schema_version in settings file) ---\n";

        $schemaPath = MANTRA_MODULES . '/' . $this->testModule . '/settings.schema.php';
        file_put_contents($schemaPath, <<<'PHP'
<?php
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General',
            'fields' => array(
                array('path' => 'color', 'type' => 'text', 'default' => 'blue'),
            ),
        ),
    ),
    'migrate' => function($data, $from, $to) {
        $data['_was_migrated'] = true;
        $data['_from'] = $from;
        return $data;
    }
);
PHP
        );

        // Settings without schema_version
        $this->writeSettingsFile(array('color' => 'red'));

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assert($ms->get('color') === 'red', 'Existing value preserved');
        $this->assert($ms->get('_was_migrated') === true, 'Migration callback ran');
        $this->assert($ms->get('_from') === 0, '$from was 0 for missing schema_version');
    }

    private function testMigrationNotReapplied()
    {
        echo "\n--- Migration not re-applied on second load ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'value', 'type' => 'text', 'default' => 'default'),
                    ),
                ),
            ),
        ));

        $this->writeSettingsFile(array('value' => 'custom'));

        // First load
        $ms1 = new Module\ModuleSettings($this->testModule);
        $ms1->load();

        // Second load (fresh instance)
        $ms2 = new Module\ModuleSettings($this->testModule);
        $ms2->load();

        $this->assert(
            $ms2->get('value') === 'custom',
            'Value stable across loads'
        );

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);
        $this->assert(
            isset($raw['schema_version']) && $raw['schema_version'] === 1,
            'schema_version persisted after first load'
        );
    }

    // ---------------------------------------------------------------
    // get/set/has
    // ---------------------------------------------------------------

    private function testGetSetHas()
    {
        echo "\n--- get/set/has methods ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'key1', 'type' => 'text', 'default' => 'val1'),
                        array('path' => 'key2', 'type' => 'text', 'default' => 'val2'),
                    ),
                ),
            ),
        ));

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);

        $this->assert($ms->has('key1'), 'has() true for default key');
        $this->assert(!$ms->has('nonexistent'), 'has() false for missing key');
        $this->assert($ms->get('key1') === 'val1', 'get() returns default');
        $this->assert($ms->get('missing', 'fb') === 'fb', 'get() returns fallback');

        $ms->set('key1', 'updated');
        $this->assert($ms->get('key1') === 'updated', 'set() updates value');
    }

    private function testSetMultiple()
    {
        echo "\n--- setMultiple() method ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'a', 'type' => 'text', 'default' => ''),
                        array('path' => 'b', 'type' => 'text', 'default' => ''),
                    ),
                ),
            ),
        ));

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->setMultiple(array('a' => 'x', 'b' => 'y'));
        $ms->save();

        $this->assert($ms->get('a') === 'x', 'First value set');
        $this->assert($ms->get('b') === 'y', 'Second value set');
    }

    // ---------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------

    private function testOverridesOnlySaving()
    {
        echo "\n--- Overrides-only saving (values at defaults not saved) ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'color', 'type' => 'text', 'default' => 'blue'),
                        array('path' => 'size', 'type' => 'number', 'default' => 10),
                    ),
                ),
            ),
        ));

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        // Only change color, leave size at default
        $ms->set('color', 'red');
        $ms->save();

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);

        $this->assert(
            isset($raw['color']) && $raw['color'] === 'red',
            'Override value saved'
        );
        $this->assert(
            !isset($raw['size']),
            'Default value NOT saved (overrides-only)'
        );
        $this->assert(
            isset($raw['schema_version']),
            'schema_version saved'
        );
    }

    private function testUnknownKeysPreserved()
    {
        echo "\n--- Unknown keys preserved through save ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'name', 'type' => 'text', 'default' => ''),
                    ),
                ),
            ),
        ));

        // Settings file with extra keys not in schema
        $this->writeSettingsFile(array(
            'name' => 'Test',
            'custom_plugin_data' => array('foo' => 'bar'),
            'legacy_flag' => true
        ));

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->set('name', 'Updated');
        $ms->save();

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);

        $this->assert(
            $raw['name'] === 'Updated',
            'Known field updated'
        );
        $this->assert(
            isset($raw['custom_plugin_data']) && $raw['custom_plugin_data']['foo'] === 'bar',
            'Unknown nested key preserved'
        );
        $this->assert(
            isset($raw['legacy_flag']) && $raw['legacy_flag'] === true,
            'Unknown scalar key preserved'
        );
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    private function testModuleWithoutSchema()
    {
        echo "\n--- Module without settings schema file ---\n";

        $noSchemaModule = 'tmig_noschema';

        // Write settings file but no schema
        $settingsPath = MANTRA_CONTENT . '/settings/' . $noSchemaModule . '.json';
        file_put_contents($settingsPath, json_encode(array('key' => 'value')));

        $ms = new Module\ModuleSettings($noSchemaModule);
        $schema = $ms->schema();

        $this->assert($schema === null, 'Schema is null for module without schema file');
        $this->assert($ms->get('key') === 'value', 'Raw settings still readable');
    }

    private function testMissingSettingsFile()
    {
        echo "\n--- Load with no settings file uses defaults ---\n";

        $this->writeModuleSchema(array(
            'version' => 1,
            'tabs' => array(
                array(
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => array(
                        array('path' => 'mode', 'type' => 'text', 'default' => 'auto'),
                    ),
                ),
            ),
        ));

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assert(
            $ms->get('mode') === 'auto',
            'Default value used when settings file missing'
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function writeModuleSchema($schema)
    {
        $schemaPath = MANTRA_MODULES . '/' . $this->testModule . '/settings.schema.php';
        $content = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $content);
    }

    private function writeSettingsFile($data)
    {
        $path = MANTRA_CONTENT . '/settings/' . $this->testModule . '.json';
        file_put_contents($path, json_encode($data));
    }

    private function removeSettingsFile()
    {
        $path = MANTRA_CONTENT . '/settings/' . $this->testModule . '.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function printResults()
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ModuleSettings Test Results\n";
        echo str_repeat('=', 50) . "\n";

        $passed = 0;
        $failed = 0;

        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }

        $total = $passed + $failed;
        echo "Total: $total | Passed: $passed | Failed: $failed\n";

        if ($failed === 0) {
            echo "\nAll tests passed!\n";
        } else {
            echo "\nSome tests failed!\n";
        }
    }
}

// Run tests
$test = new ModuleSettingsTest();
$test->run();
