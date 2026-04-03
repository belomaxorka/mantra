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
 *
 * @requires PHPUnit 10.x
 */

class DatabaseSchemaTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        $this->testDir = MANTRA_STORAGE . '/test-schema-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupTestSchemas('tschema_*.php');
        $this->removeDirectory($this->testDir);
    }

    // ---------------------------------------------------------------
    // Fix #1: migration runs for documents without schema_version
    // ---------------------------------------------------------------

    public function testMigrationRunsWithoutSchemaVersion(): void
    {
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

        $this->assertNotNull($doc, 'Pre-schema document is readable');
        $this->assertArrayHasKey('_migrated_from', $doc, 'Migration callback received $from=0 for missing schema_version');
        $this->assertSame(0, $doc['_migrated_from'], 'Migration callback received $from=0 for missing schema_version');
        $this->assertArrayHasKey('schema_version', $doc, 'schema_version bumped to current after migration');
        $this->assertSame(2, $doc['schema_version'], 'schema_version bumped to current after migration');
        $this->assertSame('Legacy', $doc['name'], 'Original data preserved');
        $this->assertSame('active', $doc['status'], 'Defaults applied after migration');
    }

    public function testMigrationCallbackForPreSchemaDoc(): void
    {
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

        $this->assertArrayNotHasKey('login', $doc, 'Old field "login" removed by migration');
        $this->assertArrayHasKey('username', $doc, 'Field renamed: login -> username with correct value');
        $this->assertSame('admin', $doc['username'], 'Field renamed: login -> username with correct value');
        $this->assertSame('admin@test.com', $doc['email'], 'Unrelated field preserved');
        $this->assertSame(2, $doc['schema_version'], 'schema_version set to 2');

        // Re-read: no double migration
        $doc2 = $db->read($collection, $id);
        $this->assertSame('admin', $doc2['username'], 'Re-read returns same migrated value');
    }

    public function testMigrationSkippedWhenVersionCurrent(): void
    {
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

        $this->assertArrayNotHasKey('_should_not_exist', $doc, 'Migration callback NOT invoked for up-to-date document');
        $this->assertSame(1, $doc['schema_version'], 'schema_version unchanged');
    }

    public function testMigrationBumpsVersionWithoutCallback(): void
    {
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

        $this->assertSame(3, $doc['schema_version'], 'schema_version bumped from 1 to 3 without callback');
        $this->assertSame('default_val', $doc['new_field'], 'New defaults applied');
    }

    // ---------------------------------------------------------------
    // Fix #1 (ordering): defaults applied AFTER migration
    // ---------------------------------------------------------------

    public function testDefaultsAppliedAfterMigration(): void
    {
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

        $this->assertArrayHasKey('_subtitle_was_absent', $doc, 'During migration, subtitle was NOT yet set by defaults');
        $this->assertTrue($doc['_subtitle_was_absent'], 'During migration, subtitle was NOT yet set by defaults');
        $this->assertSame('default subtitle', $doc['subtitle'], 'After migration, subtitle filled by defaults');
    }

    public function testMigrateFieldRenameNotShadowedByDefaults(): void
    {
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

        $this->assertSame('john_doe', $doc['username'], 'Rename migration works: username = "john_doe" (not empty string from defaults)');
        $this->assertArrayNotHasKey('login', $doc, 'Old login field removed');
        $this->assertSame('active', $doc['status'], 'Other defaults still applied');
    }

    // ---------------------------------------------------------------
    // Fix #2: readCollection normalization uses writeRaw
    // ---------------------------------------------------------------

    public function testReadCollectionNormalizationPreservesTimestamps(): void
    {
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

        $this->assertNotNull($found, 'Document found in collection');
        $this->assertSame('added', $found['new_field'], 'Defaults applied during collection normalization');
        $this->assertSame(2, $found['schema_version'], 'Schema version bumped during collection normalization');
        $this->assertSame($frozenTime, $found['updated_at'], 'updated_at NOT mutated by normalization (writeRaw used, not write)');
        $this->assertSame($frozenTime, $found['created_at'], 'created_at preserved during normalization');
    }

    // ---------------------------------------------------------------
    // Fix #3: Config::bootstrap preserves schema_version
    // ---------------------------------------------------------------

    public function testConfigBootstrapPreservesSchemaVersion(): void
    {
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

        $this->assertArrayHasKey('schema_version', $result, 'schema_version preserved through pruneToDefaults');
        $this->assertSame(5, $result['schema_version'], 'schema_version preserved through pruneToDefaults');
        $this->assertSame('Test Site', $result['site']['name'], 'Regular config values still work');

        @unlink($configPath);
    }

    public function testConfigPruneWithoutSchemaVersion(): void
    {
        $tempDir = $this->testDir . '/settings2';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $configPath = $tempDir . '/config.json';
        file_put_contents($configPath, json_encode(array(
            'site' => array('name' => 'No Version'),
        )));

        $result = Config::bootstrap($configPath);

        $this->assertArrayNotHasKey('schema_version', $result, 'schema_version absent when not in config.json');
        $this->assertSame('No Version', $result['site']['name'], 'Config values loaded correctly');

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
}
