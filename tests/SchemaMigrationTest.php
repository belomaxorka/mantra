<?php declare(strict_types=1);
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
 *
 * @requires PHPUnit 10.x
 */

class SchemaMigrationTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = MANTRA_STORAGE . '/test-migration-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0o755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestSchemas('tmig_*.php');
        $this->removeDirectory($this->testDir);
    }

    // ---------------------------------------------------------------
    // Multi-step migrations
    // ---------------------------------------------------------------

    public function testMultiStepMigration(): void
    {
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
        $this->writeRawJson($collection, $id, [
            'login' => 'admin',
            'schema_version' => 1,
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertArrayNotHasKey('login', $doc, 'v1->v2: login field removed');
        $this->assertSame('admin', $doc['name'], 'v1->v2: name set from login');
        $this->assertSame('Admin', $doc['display_name'], 'v2->v3: display_name derived from name');
        $this->assertSame(3, $doc['schema_version'], 'Version bumped to 3');
        $this->assertSame('user', $doc['role'], 'Default role applied after migration');
    }

    public function testMigrationFromZeroToLatest(): void
    {
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
        $this->writeRawJson($collection, $id, ['title' => 'Old']);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertTrue($doc['_migrated'], 'Migration ran for v0 document');
        $this->assertSame(0, $doc['_from'], '$from is 0 for document without schema_version');
        $this->assertSame(2, $doc['_to'], '$to is 2 (current schema version)');
        $this->assertSame(2, $doc['schema_version'], 'schema_version set to 2');
    }

    // ---------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------

    public function testMigrationCallbackThrows(): void
    {
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
        $this->writeRawJson($collection, $id, [
            'name' => 'Original',
            'schema_version' => 1,
        ]);

        $db = new Database($this->testDir);

        try {
            $db->read($collection, $id);
            $this->fail('Expected exception from migration callback');
        } catch (Exception $e) {
            $this->assertStringContainsString(
                'Migration failed intentionally',
                $e->getMessage(),
                'Migration exception propagates with original message',
            );
        }

        // Document should remain unchanged on disk
        $raw = json_decode(file_get_contents(
            $this->testDir . '/' . $collection . '/' . $id . '.json',
        ), true);
        $this->assertSame(1, $raw['schema_version'], 'Document unchanged on disk after failed migration');
    }

    public function testMigrationCallbackReturnsNonArray(): void
    {
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
        $this->writeRawJson($collection, $id, [
            'name' => 'Test',
            'schema_version' => 1,
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertIsArray($doc, 'Result is array despite broken migrate callback');
        $this->assertSame(2, $doc['schema_version'], 'schema_version still set after non-array guard');
        $this->assertSame('fallback', $doc['name'], 'Defaults fill empty document after non-array guard reset');
    }

    // ---------------------------------------------------------------
    // Data preservation
    // ---------------------------------------------------------------

    public function testUnknownFieldsPreserved(): void
    {
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
        $this->writeRawJson($collection, $id, [
            'name' => 'Test',
            'custom_module_field' => 'module-data',
            'nested_extra' => ['key' => 'value'],
            'schema_version' => 1,
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertSame('module-data', $doc['custom_module_field'], 'Custom string field preserved through migration');
        $this->assertIsArray($doc['nested_extra'], 'Nested extra field preserved through migration');
        $this->assertSame('value', $doc['nested_extra']['key'], 'Nested extra field preserved through migration');
        $this->assertSame(2, $doc['schema_version'], 'Version still bumped');
    }

    public function testNestedDataPreserved(): void
    {
        $collection = 'tmig_nested';
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => [
                'title' => '',
                'meta' => '',
            ],
        ]);

        $id = 'nested-doc';
        $this->writeRawJson($collection, $id, [
            'title' => 'Test',
            'meta' => ['og_title' => 'OG Test', 'og_desc' => 'Description'],
            'tags' => ['php', 'cms'],
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertIsArray($doc['meta'], 'Nested array field not overwritten by string default');
        $this->assertSame('OG Test', $doc['meta']['og_title'], 'Nested array field not overwritten by string default');
        $this->assertIsArray($doc['tags'], 'Non-schema array field preserved');
        $this->assertCount(2, $doc['tags'], 'Non-schema array field preserved');
    }

    // ---------------------------------------------------------------
    // Schema caching
    // ---------------------------------------------------------------

    public function testSchemaCachePerInstance(): void
    {
        $collection = 'tmig_cache';
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => ['name' => '', 'v1_field' => 'from_v1'],
        ]);

        $db = new Database($this->testDir);
        $id = 'cache-doc';

        // Write through db so defaults are applied
        $db->write($collection, $id, ['name' => 'Test']);
        $doc1 = $db->read($collection, $id);
        $this->assertSame('from_v1', $doc1['v1_field'], 'v1 default applied');

        // Update schema on disk to v2
        $this->createTestSchema($collection, [
            'version' => 2,
            'defaults' => ['name' => '', 'v1_field' => 'from_v1', 'v2_field' => 'from_v2'],
        ]);

        // Same db instance: still uses cached v1 schema
        $doc2 = $db->read($collection, $id);
        $this->assertArrayNotHasKey('v2_field', $doc2, 'Same instance does not see new schema (cache)');

        // New db instance: loads updated schema
        $db2 = new Database($this->testDir);
        $doc3 = $db2->read($collection, $id);
        $this->assertArrayHasKey('v2_field', $doc3, 'New instance loads updated schema and applies new defaults');
        $this->assertSame('from_v2', $doc3['v2_field'], 'New instance loads updated schema and applies new defaults');
        $this->assertSame(2, $doc3['schema_version'], 'Version bumped by new instance');
    }

    // ---------------------------------------------------------------
    // Defaults-only evolution
    // ---------------------------------------------------------------

    public function testDefaultsOnlyNoVersionBump(): void
    {
        // Schema stays at v1, but we add a new default field.
        // Documents at v1 should get the default without migration running.
        $collection = 'tmig_defonly';
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => ['name' => '', 'status' => 'active'],
        ]);

        $db1 = new Database($this->testDir);
        $id = 'def-doc';
        $db1->write($collection, $id, ['name' => 'Test']);

        // Now "evolve" schema: add new default, keep version 1
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => ['name' => '', 'status' => 'active', 'priority' => 'normal'],
        ]);

        $db2 = new Database($this->testDir);
        $doc = $db2->read($collection, $id);

        $this->assertSame('normal', $doc['priority'], 'New default field applied without version bump');
        $this->assertSame(1, $doc['schema_version'], 'schema_version unchanged (still v1)');
        $this->assertSame('active', $doc['status'], 'Existing default preserved');
    }

    public function testNewDefaultFieldAddedWithoutMigration(): void
    {
        $collection = 'tmig_newdef';
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => ['name' => ''],
        ]);

        $db1 = new Database($this->testDir);
        $id = 'newdef-doc';
        $db1->write($collection, $id, ['name' => 'Item']);

        // Bump version, add default, no migrate callback
        $this->createTestSchema($collection, [
            'version' => 2,
            'defaults' => ['name' => '', 'category' => 'uncategorized'],
        ]);

        $db2 = new Database($this->testDir);
        $doc = $db2->read($collection, $id);

        $this->assertSame('uncategorized', $doc['category'], 'New default applied on version bump');
        $this->assertSame(2, $doc['schema_version'], 'Version bumped from 1 to 2');
        $this->assertSame('Item', $doc['name'], 'Existing data preserved');
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function testMigrationIdempotency(): void
    {
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
        $this->writeRawJson($collection, $id, ['login' => 'root']);

        $db = new Database($this->testDir);

        // First read triggers migration
        $doc1 = $db->read($collection, $id);
        $this->assertSame('root', $doc1['username'], 'First migration correct');

        // Re-read from new instance: migration should NOT run again
        $db2 = new Database($this->testDir);
        $doc2 = $db2->read($collection, $id);

        $this->assertSame('root', $doc2['username'], 'Data stable after second read');
        $this->assertArrayNotHasKey('login', $doc2, 'Old field stays removed');
        $this->assertSame(2, $doc2['schema_version'], 'Version stays at 2');
    }

    public function testRepeatedReadsNoExtraWrites(): void
    {
        $collection = 'tmig_nowrite';
        $this->createTestSchema($collection, [
            'version' => 1,
            'defaults' => ['name' => '', 'status' => 'active'],
        ]);

        $db = new Database($this->testDir);
        $id = 'stable-doc';
        $db->write($collection, $id, ['name' => 'Stable']);

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

        $this->assertSame($mtime1, $mtime2, 'File not rewritten on read of stable document');
        $this->assertSame('Stable', $doc['name'], 'Data correct');
    }

    // ---------------------------------------------------------------
    // Collection-level consistency
    // ---------------------------------------------------------------

    public function testCollectionMixedVersionDocuments(): void
    {
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
        $this->writeRawJson($collection, 'doc-v2', [
            'name' => 'Already V2',
            'migrated' => true,
            'schema_version' => 2,
        ]);

        // v1 document (needs migration)
        $this->writeRawJson($collection, 'doc-v1', [
            'name' => 'Still V1',
            'schema_version' => 1,
        ]);

        // v0 document (pre-schema)
        $this->writeRawJson($collection, 'doc-v0', [
            'name' => 'Pre-schema',
        ]);

        $db = new Database($this->testDir);
        $items = $db->query($collection, [], ['sort' => 'name']);

        $this->assertCount(3, $items, 'All 3 documents returned');

        $byId = [];
        foreach ($items as $item) {
            $byId[$item['_id']] = $item;
        }

        $this->assertTrue($byId['doc-v2']['migrated'], 'V2 doc unchanged');
        $this->assertSame(2, $byId['doc-v2']['schema_version'], 'V2 doc version unchanged');

        $this->assertTrue($byId['doc-v1']['migrated'], 'V1 doc migrated');
        $this->assertSame(2, $byId['doc-v1']['schema_version'], 'V1 doc version bumped');

        $this->assertTrue($byId['doc-v0']['migrated'], 'V0 doc migrated');
        $this->assertSame(2, $byId['doc-v0']['schema_version'], 'V0 doc version set');
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testSchemaWithoutVersionField(): void
    {
        $this->createTestSchema('tmig_nover', [
            'defaults' => ['name' => '', 'color' => 'blue'],
        ]);

        $id = 'nover-doc';
        $this->writeRawJson('tmig_nover', $id, ['name' => 'Test']);

        $db = new Database($this->testDir);
        $doc = $db->read('tmig_nover', $id);

        $this->assertSame('blue', $doc['color'], 'Defaults applied even without schema version');
        $this->assertSame('Test', $doc['name'], 'Original data preserved');
    }

    public function testCollectionWithoutSchema(): void
    {
        $collection = 'tmig_noschema';
        // No schema file created

        $id = 'raw-doc';
        $this->writeRawJson($collection, $id, [
            'foo' => 'bar',
            'count' => 42,
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read($collection, $id);

        $this->assertSame('bar', $doc['foo'], 'Data readable without schema');
        $this->assertSame(42, $doc['count'], 'Numeric data preserved');
        $this->assertArrayNotHasKey('schema_version', $doc, 'No schema_version added');
        $this->assertArrayHasKey('_id', $doc, '_id still set');
    }

    public function testEmptyMigrateCallback(): void
    {
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
        $this->writeRawJson('tmig_noop', $id, [
            'name' => 'Unchanged',
            'schema_version' => 1,
        ]);

        $db = new Database($this->testDir);
        $doc = $db->read('tmig_noop', $id);

        $this->assertSame('Unchanged', $doc['name'], 'Data preserved through no-op migration');
        $this->assertSame(3, $doc['schema_version'], 'Version still bumped to current');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Write a raw JSON file bypassing Database (simulates pre-existing data).
     */
    private function writeRawJson($collection, $id, $data): void
    {
        $dir = $this->testDir . '/' . $collection;
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($dir . '/' . $id . '.json', json_encode($data));
    }
}
