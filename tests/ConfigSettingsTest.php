<?php
/**
 * ConfigSettings Tests
 *
 * Tests for schema-driven config.json management:
 * - Real v2 migration (admin sub-modules removal)
 * - Field defaults from tabs
 * - Overrides-only saving
 * - get/set/has methods
 * - schema_version handling
 */

require_once __DIR__ . '/../core/bootstrap.php';

class ConfigSettingsTest
{
    private $configPath;
    private $originalConfig = null;
    private $results = array();

    public function __construct()
    {
        $this->configPath = MANTRA_CONTENT . '/settings/config.json';

        // Backup original config.json
        if (file_exists($this->configPath)) {
            $this->originalConfig = file_get_contents($this->configPath);
        }
    }

    public function __destruct()
    {
        // Restore original config.json
        if ($this->originalConfig !== null) {
            file_put_contents($this->configPath, $this->originalConfig);
        } elseif (file_exists($this->configPath)) {
            // Config didn't exist before; remove the test one
            @unlink($this->configPath);
        }
    }

    public function run()
    {
        echo "Running ConfigSettings Tests...\n\n";

        $this->testMigrationV1toV2AdminSubModules();
        $this->testFieldDefaultsFromTabs();
        $this->testOverridesOnlySaving();
        $this->testGetSetHas();
        $this->testSchemaVersionPreservedAfterSave();
        $this->testMigrationNotReapplied();
        $this->testSetMultiple();
        $this->testMissingConfigFile();

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
    // Migration
    // ---------------------------------------------------------------

    private function testMigrationV1toV2AdminSubModules()
    {
        echo "\n--- Real migration v1->v2: admin sub-modules removal ---\n";

        // Write a v1 config with admin sub-modules in modules.enabled
        $this->writeConfig(array(
            'schema_version' => 1,
            'modules' => array(
                'enabled' => array('admin', 'admin-dashboard', 'admin-pages', 'admin-posts', 'admin-settings', 'seo')
            )
        ));

        $cs = new ConfigSettings();
        $cs->load();

        $enabled = $cs->get('modules.enabled');
        $this->assert(is_array($enabled), 'modules.enabled is an array after migration');
        $this->assert(
            in_array('admin', $enabled),
            'admin module preserved'
        );
        $this->assert(
            in_array('seo', $enabled),
            'seo module preserved'
        );
        $this->assert(
            !in_array('admin-dashboard', $enabled),
            'admin-dashboard removed by v2 migration'
        );
        $this->assert(
            !in_array('admin-pages', $enabled),
            'admin-pages removed by v2 migration'
        );
        $this->assert(
            !in_array('admin-posts', $enabled),
            'admin-posts removed by v2 migration'
        );
        $this->assert(
            !in_array('admin-settings', $enabled),
            'admin-settings removed by v2 migration'
        );

        // Verify schema_version bumped
        $schema = $cs->schema();
        $currentVersion = isset($schema['version']) ? $schema['version'] : 0;
        $this->assert(
            $cs->get('schema_version') === $currentVersion,
            'schema_version bumped to current (' . $currentVersion . ')'
        );
    }

    // ---------------------------------------------------------------
    // Field defaults
    // ---------------------------------------------------------------

    private function testFieldDefaultsFromTabs()
    {
        echo "\n--- Field defaults applied from schema tabs ---\n";

        // Write a minimal config (missing most fields)
        $this->writeConfig(array(
            'site' => array('name' => 'Test Site')
        ));

        $cs = new ConfigSettings();
        $cs->load();

        // These should come from tab field defaults
        $this->assert(
            $cs->get('locale.timezone') === 'UTC',
            'timezone default applied from schema tab'
        );
        $this->assert(
            $cs->get('content.posts_per_page') === 10,
            'posts_per_page default applied from schema tab'
        );
        $this->assert(
            $cs->get('session.lifetime') === 7200,
            'session.lifetime default applied from schema tab'
        );
        $this->assert(
            $cs->get('debug.enabled') === true,
            'debug.enabled default applied from schema tab'
        );

        // Explicitly set value should not be overwritten
        $this->assert(
            $cs->get('site.name') === 'Test Site',
            'Explicit site.name not overwritten by default'
        );
    }

    // ---------------------------------------------------------------
    // Overrides-only saving
    // ---------------------------------------------------------------

    private function testOverridesOnlySaving()
    {
        echo "\n--- Config saved as overrides-only (diff from defaults) ---\n";

        // Write config with one override
        $this->writeConfig(array(
            'site' => array('name' => 'Custom Name')
        ));

        $cs = new ConfigSettings();
        $cs->load();
        $cs->save();

        // Read raw file
        $raw = json_decode(file_get_contents($this->configPath), true);

        $this->assert(
            isset($raw['site']['name']) && $raw['site']['name'] === 'Custom Name',
            'Override value preserved in file'
        );
        $this->assert(
            !isset($raw['locale']['timezone']),
            'Default timezone NOT written to file (overrides-only)'
        );
        $this->assert(
            !isset($raw['session']['lifetime']),
            'Default session.lifetime NOT written to file (overrides-only)'
        );
        $this->assert(
            isset($raw['schema_version']),
            'schema_version IS written to file'
        );
    }

    // ---------------------------------------------------------------
    // get/set/has
    // ---------------------------------------------------------------

    private function testGetSetHas()
    {
        echo "\n--- get/set/has methods ---\n";

        $this->writeConfig(array());

        $cs = new ConfigSettings();

        $this->assert($cs->has('site.name'), 'has() returns true for default key');
        $this->assert(!$cs->has('nonexistent.path'), 'has() returns false for missing key');

        $this->assert(
            $cs->get('site.name') === 'Mantra CMS',
            'get() returns default value'
        );
        $this->assert(
            $cs->get('nonexistent', 'fallback') === 'fallback',
            'get() returns fallback for missing key'
        );

        $cs->set('site.name', 'New Name');
        $this->assert(
            $cs->get('site.name') === 'New Name',
            'set() updates value in memory'
        );
    }

    private function testSetMultiple()
    {
        echo "\n--- setMultiple() method ---\n";

        $this->writeConfig(array());

        $cs = new ConfigSettings();
        $cs->setMultiple(array(
            'site.name' => 'Bulk Name',
            'locale.timezone' => 'Europe/Moscow'
        ));
        $cs->save();

        $this->assert($cs->get('site.name') === 'Bulk Name', 'First value set');
        $this->assert($cs->get('locale.timezone') === 'Europe/Moscow', 'Second value set');

        // Verify persistence
        $raw = json_decode(file_get_contents($this->configPath), true);
        $this->assert(
            isset($raw['site']['name']) && $raw['site']['name'] === 'Bulk Name',
            'First value persisted'
        );
        $this->assert(
            isset($raw['locale']['timezone']) && $raw['locale']['timezone'] === 'Europe/Moscow',
            'Second value persisted'
        );
    }

    // ---------------------------------------------------------------
    // schema_version persistence
    // ---------------------------------------------------------------

    private function testSchemaVersionPreservedAfterSave()
    {
        echo "\n--- schema_version preserved through save cycle ---\n";

        $this->writeConfig(array('site' => array('name' => 'SV Test')));

        $cs = new ConfigSettings();
        $cs->load();
        $cs->set('site.name', 'SV Test Updated');
        $cs->save();

        // Read raw file
        $raw = json_decode(file_get_contents($this->configPath), true);
        $schema = $cs->schema();
        $expectedVersion = is_array($schema) && isset($schema['version']) ? $schema['version'] : 0;

        $this->assert(
            isset($raw['schema_version']) && $raw['schema_version'] === $expectedVersion,
            'schema_version (' . $expectedVersion . ') present in saved file'
        );
    }

    private function testMigrationNotReapplied()
    {
        echo "\n--- Migration not re-applied on second load ---\n";

        // Write v1 config
        $this->writeConfig(array(
            'schema_version' => 1,
            'modules' => array(
                'enabled' => array('admin', 'admin-dashboard', 'seo')
            )
        ));

        // First load: migration runs
        $cs1 = new ConfigSettings();
        $cs1->load();
        $enabled1 = $cs1->get('modules.enabled');

        // Second load (new instance): migration should not re-run
        $cs2 = new ConfigSettings();
        $cs2->load();
        $enabled2 = $cs2->get('modules.enabled');

        $this->assert(
            $enabled1 === $enabled2,
            'Second load produces same result (migration not re-applied)'
        );
        $this->assert(
            !in_array('admin-dashboard', $enabled2),
            'admin-dashboard still absent on second load'
        );
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    private function testMissingConfigFile()
    {
        echo "\n--- Load with missing config file uses defaults ---\n";

        // Remove config.json
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }

        $cs = new ConfigSettings();
        $cs->load();

        $this->assert(
            $cs->get('site.name') === 'Mantra CMS',
            'Default site.name used when config.json missing'
        );
        $this->assert(
            $cs->get('locale.timezone') === 'UTC',
            'Default timezone used when config.json missing'
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function writeConfig($data)
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->configPath, json_encode($data));
    }

    private function printResults()
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ConfigSettings Test Results\n";
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
$test = new ConfigSettingsTest();
$test->run();
