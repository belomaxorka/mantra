<?php
/**
 * Database Schema Tests
 *
 * Tests for schema normalization, migration ordering, and Config metadata
 * preservation. Covers fixes for:
 * - normalizeDocument() skipping migration for pre-schema documents
 * - Migration running before defaults (correct ordering)
 * - readCollection() using writeRaw instead of write
 * - Config::bootstrap() preserving schema_version
 */

require_once __DIR__ . '/../core/bootstrap.php';

class DatabaseSchemaTest
{
    private $testDir;
    private $results = array();

    public function __construct()
    {
        $this->testDir = MANTRA_STORAGE . '/test-schema-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    public function __destruct()
    {
        $schemas = glob(MANTRA_CORE . '/schemas/tschema_*.php');
        foreach ($schemas as $schema) {
            @unlink($schema);
        }
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function run()
    {
        echo "Running Database Schema Tests...\n\n";

        // Fix #1: normalizeDocument migration for pre-schema documents
        $this->testMigrationRunsWithoutSchemaVersion();
        $this->testMigrationCallbackForPreSchemaDoc();
        $this->testMigrationSkippedWhenVersionCurrent();
        $this->testMigrationBumpsVersionWithoutCallback();

        // Fix #1 (ordering): defaults applied after migration
        $this->testDefaultsAppliedAfterMigration();
        $this->testMigrateFieldRenameNotShadowedByDefaults();

        // Fix #2: readCollection uses writeRaw (no updated_at mutation)
        $this->testReadCollectionNormalizationPreservesTimestamps();

        // Fix #3: Config::bootstrap preserves schema_version
        $this->testConfigBootstrapPreservesSchemaVersion();
        $this->testConfigPruneWithoutSchemaVersion();

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
    // Fix #1: migration runs for documents without schema_version
    // ---------------------------------------------------------------

    private function testMigrationRunsWithoutSchemaVersion()
    {
        echo "\n--- Migration runs for documents without schema_version ---\n";

        $collection = 'tschema_mig_nosv';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('name' => '', 'status' => 'active'),
    'migrate' => function($doc, $from, $to) {
        $doc['_migrated_from'] = $from;
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        // Write a raw document WITHOUT schema_version (simulates pre-schema data)
        $id = 'pre-schema-doc';
        $this->writeRawJson($collection, $id, array('name' => 'Legacy'));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert($doc !== null, 'Pre-schema document is readable');
        $this->assert(
            isset($doc['_migrated_from']) && $doc['_migrated_from'] === 0,
            'Migration callback received $from=0 for missing schema_version'
        );
        $this->assert(
            isset($doc['schema_version']) && $doc['schema_version'] === 2,
            'schema_version bumped to current after migration'
        );
        $this->assert($doc['name'] === 'Legacy', 'Original data preserved');
        $this->assert($doc['status'] === 'active', 'Defaults applied after migration');
    }

    private function testMigrationCallbackForPreSchemaDoc()
    {
        echo "\n--- Migration callback with field rename for pre-schema doc ---\n";

        $collection = 'tschema_mig_rename';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('username' => '', 'email' => ''),
    'migrate' => function($doc, $from, $to) {
        if ($from < 2 && isset($doc['login']) && !isset($doc['username'])) {
            $doc['username'] = $doc['login'];
            unset($doc['login']);
        }
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        $id = 'old-user';
        $this->writeRawJson($collection, $id, array(
            'login' => 'admin',
            'email' => 'admin@test.com'
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(!isset($doc['login']), 'Old field "login" removed by migration');
        $this->assert(
            isset($doc['username']) && $doc['username'] === 'admin',
            'Field renamed: login -> username with correct value'
        );
        $this->assert($doc['email'] === 'admin@test.com', 'Unrelated field preserved');
        $this->assert($doc['schema_version'] === 2, 'schema_version set to 2');

        // Re-read: no double migration
        $doc2 = $db->read($collection, $id);
        $this->assert($doc2['username'] === 'admin', 'Re-read returns same migrated value');
    }

    private function testMigrationSkippedWhenVersionCurrent()
    {
        echo "\n--- Migration skipped when document version matches schema ---\n";

        $collection = 'tschema_mig_skip';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 1,
    'defaults' => array('name' => ''),
    'migrate' => function($doc, $from, $to) {
        $doc['_should_not_exist'] = true;
        return $doc;
    }
);
PHP
        );

        $id = 'current-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Up to date',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(
            !isset($doc['_should_not_exist']),
            'Migration callback NOT invoked for up-to-date document'
        );
        $this->assert($doc['schema_version'] === 1, 'schema_version unchanged');
    }

    private function testMigrationBumpsVersionWithoutCallback()
    {
        echo "\n--- Schema version bumped even without migrate callback ---\n";

        $collection = 'tschema_mig_bump';
        $this->createTestSchema($collection, array(
            'version' => 3,
            'defaults' => array('name' => '', 'new_field' => 'default_val')
        ));

        $id = 'old-ver-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Test',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert($doc['schema_version'] === 3, 'schema_version bumped from 1 to 3 without callback');
        $this->assert($doc['new_field'] === 'default_val', 'New defaults applied');
    }

    // ---------------------------------------------------------------
    // Fix #1 (ordering): defaults applied AFTER migration
    // ---------------------------------------------------------------

    private function testDefaultsAppliedAfterMigration()
    {
        echo "\n--- Defaults applied after migration, not before ---\n";

        $collection = 'tschema_order';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('title' => '', 'subtitle' => 'default subtitle'),
    'migrate' => function($doc, $from, $to) {
        // Migration checks if 'subtitle' is absent - should be true
        // because defaults have not been applied yet
        $doc['_subtitle_was_absent'] = !isset($doc['subtitle']);
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        $id = 'order-test';
        $this->writeRawJson($collection, $id, array('title' => 'Hello'));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(
            isset($doc['_subtitle_was_absent']) && $doc['_subtitle_was_absent'] === true,
            'During migration, subtitle was NOT yet set by defaults'
        );
        $this->assert(
            $doc['subtitle'] === 'default subtitle',
            'After migration, subtitle filled by defaults'
        );
    }

    private function testMigrateFieldRenameNotShadowedByDefaults()
    {
        echo "\n--- Migrate field rename: defaults do not shadow migration ---\n";

        // Scenario from CLAUDE.md: rename login -> username
        // With old code (defaults first), defaults set username='', then
        // migration's !isset($doc['username']) check fails -> data loss.
        // With fix (migration first), migration sees raw doc without defaults.

        $collection = 'tschema_shadow';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('username' => '', 'status' => 'active'),
    'migrate' => function($doc, $from, $to) {
        if ($from < 2 && isset($doc['login']) && !isset($doc['username'])) {
            $doc['username'] = $doc['login'];
            unset($doc['login']);
        }
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        $id = 'shadow-test';
        $this->writeRawJson($collection, $id, array(
            'login' => 'john_doe'
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(
            $doc['username'] === 'john_doe',
            'Rename migration works: username = "john_doe" (not empty string from defaults)'
        );
        $this->assert(!isset($doc['login']), 'Old login field removed');
        $this->assert($doc['status'] === 'active', 'Other defaults still applied');
    }

    // ---------------------------------------------------------------
    // Fix #2: readCollection normalization uses writeRaw
    // ---------------------------------------------------------------

    private function testReadCollectionNormalizationPreservesTimestamps()
    {
        echo "\n--- readCollection normalization preserves timestamps ---\n";

        $collection = 'tschema_timestamps';
        $this->createTestSchema($collection, array(
            'version' => 2,
            'defaults' => array('name' => '', 'new_field' => 'added')
        ));

        // Write raw document with explicit timestamps and old schema_version
        $frozenTime = '2025-01-15 10:00:00';
        $id = 'ts-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Timestamp Test',
            'schema_version' => 1,
            'created_at' => $frozenTime,
            'updated_at' => $frozenTime
        ));

        $db = new Database($this->testDir);

        // Read entire collection triggers normalization via readCollection()
        $items = $db->read($collection);

        $found = null;
        foreach ($items as $item) {
            if (isset($item['_id']) && $item['_id'] === $id) {
                $found = $item;
                break;
            }
        }

        $this->assert($found !== null, 'Document found in collection');
        $this->assert(
            $found['new_field'] === 'added',
            'Defaults applied during collection normalization'
        );
        $this->assert(
            $found['schema_version'] === 2,
            'Schema version bumped during collection normalization'
        );
        $this->assert(
            $found['updated_at'] === $frozenTime,
            'updated_at NOT mutated by normalization (writeRaw used, not write)'
        );
        $this->assert(
            $found['created_at'] === $frozenTime,
            'created_at preserved during normalization'
        );
    }

    // ---------------------------------------------------------------
    // Fix #3: Config::bootstrap preserves schema_version
    // ---------------------------------------------------------------

    private function testConfigBootstrapPreservesSchemaVersion()
    {
        echo "\n--- Config::bootstrap preserves schema_version ---\n";

        // Create a temp config.json with schema_version
        $tempDir = $this->testDir . '/settings';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $configPath = $tempDir . '/config.json';
        file_put_contents($configPath, json_encode(array(
            'schema_version' => 5,
            'site' => array('name' => 'Test Site'),
        )));

        $result = Config::bootstrap($configPath);

        $this->assert(
            isset($result['schema_version']) && $result['schema_version'] === 5,
            'schema_version preserved through pruneToDefaults'
        );
        $this->assert(
            $result['site']['name'] === 'Test Site',
            'Regular config values still work'
        );

        @unlink($configPath);
    }

    private function testConfigPruneWithoutSchemaVersion()
    {
        echo "\n--- Config::bootstrap works without schema_version ---\n";

        $tempDir = $this->testDir . '/settings2';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $configPath = $tempDir . '/config.json';
        file_put_contents($configPath, json_encode(array(
            'site' => array('name' => 'No Version'),
        )));

        $result = Config::bootstrap($configPath);

        $this->assert(
            !isset($result['schema_version']),
            'schema_version absent when not in config.json'
        );
        $this->assert(
            $result['site']['name'] === 'No Version',
            'Config values loaded correctly'
        );

        @unlink($configPath);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Write a raw JSON file bypassing Database (simulates pre-existing data).
     */
    private function writeRawJson($collection, $id, $data)
    {
        $dir = $this->testDir . '/' . $collection;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $id . '.json', json_encode($data));
    }

    /**
     * Write a schema file as raw PHP (supports closures).
     */
    private function writeSchemaFile($collection, $phpCode)
    {
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        file_put_contents($schemaPath, $phpCode);
    }

    /**
     * Write a schema file via var_export (no closures).
     */
    private function createTestSchema($collection, $schema)
    {
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        $content = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $content);
    }

    private function printResults()
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Database Schema Test Results\n";
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
$test = new DatabaseSchemaTest();
$test->run();
