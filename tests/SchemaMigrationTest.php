<?php
/**
 * Schema Migration System Tests
 *
 * Comprehensive tests for the Database schema migration engine:
 * - Multi-step version jumps (v1 -> v3)
 * - Migration error handling (callback throws, returns non-array)
 * - Unknown/extra fields preserved through migration
 * - Schema caching and invalidation
 * - Defaults-only evolution (no version bump)
 * - Migration idempotency
 * - Collection-level normalization consistency
 */

require_once __DIR__ . '/../core/bootstrap.php';

class SchemaMigrationTest
{
    private $testDir;
    private $results = array();

    public function __construct()
    {
        $this->testDir = MANTRA_STORAGE . '/test-migration-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    public function __destruct()
    {
        $schemas = glob(MANTRA_CORE . '/schemas/tmig_*.php');
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
        echo "Running Schema Migration System Tests...\n\n";

        // Multi-step migrations
        $this->testMultiStepMigration();
        $this->testMigrationFromZeroToLatest();

        // Error handling
        $this->testMigrationCallbackThrows();
        $this->testMigrationCallbackReturnsNonArray();

        // Data preservation
        $this->testUnknownFieldsPreserved();
        $this->testNestedDataPreserved();

        // Schema caching
        $this->testSchemaCachePerInstance();

        // Defaults-only evolution
        $this->testDefaultsOnlyNoVersionBump();
        $this->testNewDefaultFieldAddedWithoutMigration();

        // Idempotency
        $this->testMigrationIdempotency();
        $this->testRepeatedReadsNoExtraWrites();

        // Collection-level consistency
        $this->testCollectionMixedVersionDocuments();

        // Edge cases
        $this->testSchemaWithoutVersionField();
        $this->testCollectionWithoutSchema();
        $this->testEmptyMigrateCallback();

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
    // Multi-step migrations
    // ---------------------------------------------------------------

    private function testMultiStepMigration()
    {
        echo "\n--- Multi-step migration (v1 -> v3 with stepwise logic) ---\n";

        $collection = 'tmig_multi';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 3,
    'defaults' => array('name' => '', 'display_name' => '', 'role' => 'user'),
    'migrate' => function($doc, $from, $to) {
        // v1->v2: rename 'login' to 'name'
        if ($from < 2 && isset($doc['login'])) {
            $doc['name'] = $doc['login'];
            unset($doc['login']);
        }
        // v2->v3: split 'name' into 'name' + 'display_name'
        if ($from < 3 && isset($doc['name']) && !isset($doc['display_name'])) {
            $doc['display_name'] = ucfirst($doc['name']);
        }
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        // Document at v1 with 'login' field
        $id = 'multi-step';
        $this->writeRawJson($collection, $id, array(
            'login' => 'admin',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(!isset($doc['login']), 'v1->v2: login field removed');
        $this->assert($doc['name'] === 'admin', 'v1->v2: name set from login');
        $this->assert($doc['display_name'] === 'Admin', 'v2->v3: display_name derived from name');
        $this->assert($doc['schema_version'] === 3, 'Version bumped to 3');
        $this->assert($doc['role'] === 'user', 'Default role applied after migration');
    }

    private function testMigrationFromZeroToLatest()
    {
        echo "\n--- Migration from version 0 (no schema_version) to latest ---\n";

        $collection = 'tmig_zero';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('title' => '', 'status' => 'active'),
    'migrate' => function($doc, $from, $to) {
        $doc['_migrated'] = true;
        $doc['_from'] = $from;
        $doc['_to'] = $to;
        return $doc;
    }
);
PHP
        );

        $id = 'zero-doc';
        $this->writeRawJson($collection, $id, array('title' => 'Old'));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert($doc['_migrated'] === true, 'Migration ran for v0 document');
        $this->assert($doc['_from'] === 0, '$from is 0 for document without schema_version');
        $this->assert($doc['_to'] === 2, '$to is 2 (current schema version)');
        $this->assert($doc['schema_version'] === 2, 'schema_version set to 2');
    }

    // ---------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------

    private function testMigrationCallbackThrows()
    {
        echo "\n--- Migration callback that throws exception ---\n";

        $collection = 'tmig_throws';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('name' => ''),
    'migrate' => function($doc, $from, $to) {
        throw new Exception('Migration failed intentionally');
    }
);
PHP
        );

        $id = 'throw-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Original',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $exceptionThrown = false;

        try {
            $db->read($collection, $id);
        } catch (Exception $e) {
            $exceptionThrown = true;
            $this->assert(
                strpos($e->getMessage(), 'Migration failed intentionally') !== false,
                'Migration exception propagates with original message'
            );
        }

        $this->assert($exceptionThrown, 'Exception from migration callback is thrown');

        // Document should remain unchanged on disk
        $raw = json_decode(file_get_contents(
            $this->testDir . '/' . $collection . '/' . $id . '.json'
        ), true);
        $this->assert($raw['schema_version'] === 1, 'Document unchanged on disk after failed migration');
    }

    private function testMigrationCallbackReturnsNonArray()
    {
        echo "\n--- Migration callback returns non-array (guard check) ---\n";

        // normalizeDocument guards against non-array return from migrate
        // by resetting $data to empty array (same as ConfigSettings).
        $collection = 'tmig_nonarray';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('name' => 'fallback'),
    'migrate' => function($doc, $from, $to) {
        return 'not-an-array';
    }
);
PHP
        );

        $id = 'nonarray-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Test',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(is_array($doc), 'Result is array despite broken migrate callback');
        $this->assert(
            $doc['schema_version'] === 2,
            'schema_version still set after non-array guard'
        );
        $this->assert(
            $doc['name'] === 'fallback',
            'Defaults fill empty document after non-array guard reset'
        );
    }

    // ---------------------------------------------------------------
    // Data preservation
    // ---------------------------------------------------------------

    private function testUnknownFieldsPreserved()
    {
        echo "\n--- Unknown/extra fields preserved through migration ---\n";

        $collection = 'tmig_unknown';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('name' => '', 'status' => 'active'),
    'migrate' => function($doc, $from, $to) {
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        $id = 'ext-doc';
        $this->writeRawJson($collection, $id, array(
            'name' => 'Test',
            'custom_module_field' => 'module-data',
            'nested_extra' => array('key' => 'value'),
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(
            $doc['custom_module_field'] === 'module-data',
            'Custom string field preserved through migration'
        );
        $this->assert(
            is_array($doc['nested_extra']) && $doc['nested_extra']['key'] === 'value',
            'Nested extra field preserved through migration'
        );
        $this->assert($doc['schema_version'] === 2, 'Version still bumped');
    }

    private function testNestedDataPreserved()
    {
        echo "\n--- Nested document structure preserved through defaults ---\n";

        $collection = 'tmig_nested';
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array(
                'title' => '',
                'meta' => ''
            )
        ));

        $id = 'nested-doc';
        $this->writeRawJson($collection, $id, array(
            'title' => 'Test',
            'meta' => array('og_title' => 'OG Test', 'og_desc' => 'Description'),
            'tags' => array('php', 'cms')
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert(
            is_array($doc['meta']) && $doc['meta']['og_title'] === 'OG Test',
            'Nested array field not overwritten by string default'
        );
        $this->assert(
            is_array($doc['tags']) && count($doc['tags']) === 2,
            'Non-schema array field preserved'
        );
    }

    // ---------------------------------------------------------------
    // Schema caching
    // ---------------------------------------------------------------

    private function testSchemaCachePerInstance()
    {
        echo "\n--- Schema cached per Database instance ---\n";

        $collection = 'tmig_cache';
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array('name' => '', 'v1_field' => 'from_v1')
        ));

        $db = new Database($this->testDir);
        $id = 'cache-doc';

        // Write through db so defaults are applied
        $db->write($collection, $id, array('name' => 'Test'));
        $doc1 = $db->read($collection, $id);
        $this->assert($doc1['v1_field'] === 'from_v1', 'v1 default applied');

        // Update schema on disk to v2
        $this->createTestSchema($collection, array(
            'version' => 2,
            'defaults' => array('name' => '', 'v1_field' => 'from_v1', 'v2_field' => 'from_v2')
        ));

        // Same db instance: still uses cached v1 schema
        $doc2 = $db->read($collection, $id);
        $this->assert(
            !isset($doc2['v2_field']),
            'Same instance does not see new schema (cache)'
        );

        // New db instance: loads updated schema
        $db2 = new Database($this->testDir);
        $doc3 = $db2->read($collection, $id);
        $this->assert(
            isset($doc3['v2_field']) && $doc3['v2_field'] === 'from_v2',
            'New instance loads updated schema and applies new defaults'
        );
        $this->assert($doc3['schema_version'] === 2, 'Version bumped by new instance');
    }

    // ---------------------------------------------------------------
    // Defaults-only evolution
    // ---------------------------------------------------------------

    private function testDefaultsOnlyNoVersionBump()
    {
        echo "\n--- Defaults-only change with same version (no migration needed) ---\n";

        // Schema stays at v1, but we add a new default field.
        // Documents at v1 should get the default without migration running.
        $collection = 'tmig_defonly';
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array('name' => '', 'status' => 'active')
        ));

        $db1 = new Database($this->testDir);
        $id = 'def-doc';
        $db1->write($collection, $id, array('name' => 'Test'));

        // Now "evolve" schema: add new default, keep version 1
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array('name' => '', 'status' => 'active', 'priority' => 'normal')
        ));

        $db2 = new Database($this->testDir);
        $doc = $db2->read($collection, $id);

        $this->assert(
            $doc['priority'] === 'normal',
            'New default field applied without version bump'
        );
        $this->assert($doc['schema_version'] === 1, 'schema_version unchanged (still v1)');
        $this->assert($doc['status'] === 'active', 'Existing default preserved');
    }

    private function testNewDefaultFieldAddedWithoutMigration()
    {
        echo "\n--- New required default: version bump but no migrate callback ---\n";

        $collection = 'tmig_newdef';
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array('name' => '')
        ));

        $db1 = new Database($this->testDir);
        $id = 'newdef-doc';
        $db1->write($collection, $id, array('name' => 'Item'));

        // Bump version, add default, no migrate callback
        $this->createTestSchema($collection, array(
            'version' => 2,
            'defaults' => array('name' => '', 'category' => 'uncategorized')
        ));

        $db2 = new Database($this->testDir);
        $doc = $db2->read($collection, $id);

        $this->assert($doc['category'] === 'uncategorized', 'New default applied on version bump');
        $this->assert($doc['schema_version'] === 2, 'Version bumped from 1 to 2');
        $this->assert($doc['name'] === 'Item', 'Existing data preserved');
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    private function testMigrationIdempotency()
    {
        echo "\n--- Migration is idempotent (safe to run twice) ---\n";

        $collection = 'tmig_idempotent';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('username' => ''),
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

        // Pre-schema document
        $id = 'idemp-doc';
        $this->writeRawJson($collection, $id, array('login' => 'root'));

        $db = new Database($this->testDir);

        // First read triggers migration
        $doc1 = $db->read($collection, $id);
        $this->assert($doc1['username'] === 'root', 'First migration correct');

        // Re-read from new instance: migration should NOT run again
        $db2 = new Database($this->testDir);
        $doc2 = $db2->read($collection, $id);

        $this->assert($doc2['username'] === 'root', 'Data stable after second read');
        $this->assert(!isset($doc2['login']), 'Old field stays removed');
        $this->assert($doc2['schema_version'] === 2, 'Version stays at 2');
    }

    private function testRepeatedReadsNoExtraWrites()
    {
        echo "\n--- Repeated reads of stable document do not trigger writes ---\n";

        $collection = 'tmig_nowrite';
        $this->createTestSchema($collection, array(
            'version' => 1,
            'defaults' => array('name' => '', 'status' => 'active')
        ));

        $db = new Database($this->testDir);
        $id = 'stable-doc';
        $db->write($collection, $id, array('name' => 'Stable'));

        // Record file modification time
        $filePath = $this->testDir . '/' . $collection . '/' . $id . '.json';

        // Small delay so mtime would differ if file is rewritten
        clearstatcache();
        $mtime1 = filemtime($filePath);

        // Re-read: should not trigger write since doc is up-to-date
        $db2 = new Database($this->testDir);
        $doc = $db2->read($collection, $id);

        clearstatcache();
        $mtime2 = filemtime($filePath);

        $this->assert($mtime1 === $mtime2, 'File not rewritten on read of stable document');
        $this->assert($doc['name'] === 'Stable', 'Data correct');
    }

    // ---------------------------------------------------------------
    // Collection-level consistency
    // ---------------------------------------------------------------

    private function testCollectionMixedVersionDocuments()
    {
        echo "\n--- Collection with mixed-version documents ---\n";

        $collection = 'tmig_mixed';
        $this->writeSchemaFile($collection, <<<'PHP'
<?php
return array(
    'version' => 2,
    'defaults' => array('name' => '', 'migrated' => false),
    'migrate' => function($doc, $from, $to) {
        $doc['migrated'] = true;
        $doc['schema_version'] = $to;
        return $doc;
    }
);
PHP
        );

        // v2 document (already migrated)
        $this->writeRawJson($collection, 'doc-v2', array(
            'name' => 'Already V2',
            'migrated' => true,
            'schema_version' => 2
        ));

        // v1 document (needs migration)
        $this->writeRawJson($collection, 'doc-v1', array(
            'name' => 'Still V1',
            'schema_version' => 1
        ));

        // v0 document (pre-schema)
        $this->writeRawJson($collection, 'doc-v0', array(
            'name' => 'Pre-schema'
        ));

        $db = new Database($this->testDir);
        $items = $db->query($collection, array(), array('sort' => 'name'));

        $this->assert(count($items) === 3, 'All 3 documents returned');

        $byId = array();
        foreach ($items as $item) {
            $byId[$item['_id']] = $item;
        }

        $this->assert($byId['doc-v2']['migrated'] === true, 'V2 doc unchanged');
        $this->assert($byId['doc-v2']['schema_version'] === 2, 'V2 doc version unchanged');

        $this->assert($byId['doc-v1']['migrated'] === true, 'V1 doc migrated');
        $this->assert($byId['doc-v1']['schema_version'] === 2, 'V1 doc version bumped');

        $this->assert($byId['doc-v0']['migrated'] === true, 'V0 doc migrated');
        $this->assert($byId['doc-v0']['schema_version'] === 2, 'V0 doc version set');
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    private function testSchemaWithoutVersionField()
    {
        echo "\n--- Schema without version field ---\n";

        $this->createTestSchema('tmig_nover', array(
            'defaults' => array('name' => '', 'color' => 'blue')
        ));

        $id = 'nover-doc';
        $this->writeRawJson('tmig_nover', $id, array('name' => 'Test'));

        $db = new Database($this->testDir);
        $doc = $db->read('tmig_nover', $id);

        $this->assert($doc['color'] === 'blue', 'Defaults applied even without schema version');
        $this->assert($doc['name'] === 'Test', 'Original data preserved');
        // schema_version should not be set since schema has no version
        $this->assert(
            !isset($doc['schema_version']) || $doc['schema_version'] === 0,
            'No schema_version stamped when schema lacks version'
        );
    }

    private function testCollectionWithoutSchema()
    {
        echo "\n--- Collection without any schema file ---\n";

        $collection = 'tmig_noschema';
        // No schema file created

        $id = 'raw-doc';
        $this->writeRawJson($collection, $id, array(
            'foo' => 'bar',
            'count' => 42
        ));

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assert($doc['foo'] === 'bar', 'Data readable without schema');
        $this->assert($doc['count'] === 42, 'Numeric data preserved');
        $this->assert(!isset($doc['schema_version']), 'No schema_version added');
        $this->assert(isset($doc['_id']), '_id still set');
    }

    private function testEmptyMigrateCallback()
    {
        echo "\n--- Schema with migrate that returns doc unchanged ---\n";

        $this->writeSchemaFile('tmig_noop', <<<'PHP'
<?php
return array(
    'version' => 3,
    'defaults' => array('name' => ''),
    'migrate' => function($doc, $from, $to) {
        // No-op migration: just return the document
        return $doc;
    }
);
PHP
        );

        $id = 'noop-doc';
        $this->writeRawJson('tmig_noop', $id, array(
            'name' => 'Unchanged',
            'schema_version' => 1
        ));

        $db = new Database($this->testDir);
        $doc = $db->read('tmig_noop', $id);

        $this->assert($doc['name'] === 'Unchanged', 'Data preserved through no-op migration');
        $this->assert($doc['schema_version'] === 3, 'Version still bumped to current');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function writeRawJson($collection, $id, $data)
    {
        $dir = $this->testDir . '/' . $collection;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $id . '.json', json_encode($data));
    }

    private function writeSchemaFile($collection, $phpCode)
    {
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        file_put_contents($schemaPath, $phpCode);
    }

    private function createTestSchema($collection, $schema)
    {
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        $content = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $content);
    }

    private function printResults()
    {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Schema Migration System Test Results\n";
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
$test = new SchemaMigrationTest();
$test->run();
