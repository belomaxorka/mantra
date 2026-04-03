<?php declare(strict_types=1);
/**
 * ConfigSettings Tests (PHPUnit 10.x)
 *
 * Tests for schema-driven config.json management:
 * - Real v2 migration (admin sub-modules removal)
 * - Field defaults from tabs
 * - Overrides-only saving
 * - get/set/has methods
 * - schema_version handling
 */

class ConfigSettingsTest extends MantraTestCase
{
    private $configPath;
    private $originalConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = MANTRA_CONTENT . '/settings/config.json';

        // Backup original config.json
        if (file_exists($this->configPath)) {
            $this->originalConfig = file_get_contents($this->configPath);
        }
    }

    protected function tearDown(): void
    {
        // Restore original config.json
        if ($this->originalConfig !== null) {
            file_put_contents($this->configPath, $this->originalConfig);
        } elseif (file_exists($this->configPath)) {
            // Config didn't exist before; remove the test one
            @unlink($this->configPath);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Migration
    // ---------------------------------------------------------------

    public function testMigrationV1toV2AdminSubModules(): void
    {
        // Write a v1 config with admin sub-modules in modules.enabled
        $this->writeConfig([
            'schema_version' => 1,
            'modules' => [
                'enabled' => ['admin', 'admin-dashboard', 'admin-pages', 'admin-posts', 'admin-settings', 'seo'],
            ],
        ]);

        $cs = new ConfigSettings();
        $cs->load();

        $enabled = $cs->get('modules.enabled');
        $this->assertIsArray($enabled, 'modules.enabled is an array after migration');
        $this->assertContains('admin', $enabled, 'admin module preserved');
        $this->assertContains('seo', $enabled, 'seo module preserved');
        $this->assertNotContains('admin-dashboard', $enabled, 'admin-dashboard removed by v2 migration');
        $this->assertNotContains('admin-pages', $enabled, 'admin-pages removed by v2 migration');
        $this->assertNotContains('admin-posts', $enabled, 'admin-posts removed by v2 migration');
        $this->assertNotContains('admin-settings', $enabled, 'admin-settings removed by v2 migration');

        // Verify schema_version bumped
        $schema = $cs->schema();
        $currentVersion = $schema['version'] ?? 0;
        $this->assertSame(
            $currentVersion,
            $cs->get('schema_version'),
            'schema_version bumped to current (' . $currentVersion . ')',
        );
    }

    // ---------------------------------------------------------------
    // Field defaults
    // ---------------------------------------------------------------

    public function testFieldDefaultsFromTabs(): void
    {
        // Write a minimal config (missing most fields)
        $this->writeConfig([
            'site' => ['name' => 'Test Site'],
        ]);

        $cs = new ConfigSettings();
        $cs->load();

        // These should come from tab field defaults
        $this->assertSame(
            'UTC',
            $cs->get('locale.timezone'),
            'timezone default applied from schema tab',
        );
        $this->assertSame(
            10,
            $cs->get('content.posts_per_page'),
            'posts_per_page default applied from schema tab',
        );
        $this->assertSame(
            7200,
            $cs->get('session.lifetime'),
            'session.lifetime default applied from schema tab',
        );
        $this->assertTrue(
            $cs->get('debug.enabled'),
            'debug.enabled default applied from schema tab',
        );

        // Explicitly set value should not be overwritten
        $this->assertSame(
            'Test Site',
            $cs->get('site.name'),
            'Explicit site.name not overwritten by default',
        );
    }

    // ---------------------------------------------------------------
    // Overrides-only saving
    // ---------------------------------------------------------------

    public function testOverridesOnlySaving(): void
    {
        // Write config with one override
        $this->writeConfig([
            'site' => ['name' => 'Custom Name'],
        ]);

        $cs = new ConfigSettings();
        $cs->load();
        $cs->save();

        // Read raw file
        $raw = json_decode(file_get_contents($this->configPath), true);

        $this->assertArrayHasKey('site', $raw, 'Override value preserved in file');
        $this->assertSame('Custom Name', $raw['site']['name'], 'Override value preserved in file');
        $this->assertArrayNotHasKey('locale', $raw, 'Default timezone NOT written to file (overrides-only)');
        $this->assertArrayNotHasKey('session', $raw, 'Default session.lifetime NOT written to file (overrides-only)');
        $this->assertArrayHasKey('schema_version', $raw, 'schema_version IS written to file');
    }

    // ---------------------------------------------------------------
    // get/set/has
    // ---------------------------------------------------------------

    public function testGetSetHas(): void
    {
        $this->writeConfig([]);

        $cs = new ConfigSettings();

        $this->assertTrue($cs->has('site.name'), 'has() returns true for default key');
        $this->assertFalse($cs->has('nonexistent.path'), 'has() returns false for missing key');

        $this->assertSame(
            'Mantra CMS',
            $cs->get('site.name'),
            'get() returns default value',
        );
        $this->assertSame(
            'fallback',
            $cs->get('nonexistent', 'fallback'),
            'get() returns fallback for missing key',
        );

        $cs->set('site.name', 'New Name');
        $this->assertSame(
            'New Name',
            $cs->get('site.name'),
            'set() updates value in memory',
        );
    }

    public function testSetMultiple(): void
    {
        $this->writeConfig([]);

        $cs = new ConfigSettings();
        $cs->setMultiple([
            'site.name' => 'Bulk Name',
            'locale.timezone' => 'Europe/Moscow',
        ]);
        $cs->save();

        $this->assertSame('Bulk Name', $cs->get('site.name'), 'First value set');
        $this->assertSame('Europe/Moscow', $cs->get('locale.timezone'), 'Second value set');

        // Verify persistence
        $raw = json_decode(file_get_contents($this->configPath), true);
        $this->assertArrayHasKey('site', $raw, 'First value persisted');
        $this->assertSame('Bulk Name', $raw['site']['name'], 'First value persisted');
        $this->assertArrayHasKey('locale', $raw, 'Second value persisted');
        $this->assertSame('Europe/Moscow', $raw['locale']['timezone'], 'Second value persisted');
    }

    // ---------------------------------------------------------------
    // schema_version persistence
    // ---------------------------------------------------------------

    public function testSchemaVersionPreservedAfterSave(): void
    {
        $this->writeConfig(['site' => ['name' => 'SV Test']]);

        $cs = new ConfigSettings();
        $cs->load();
        $cs->set('site.name', 'SV Test Updated');
        $cs->save();

        // Read raw file
        $raw = json_decode(file_get_contents($this->configPath), true);
        $schema = $cs->schema();
        $expectedVersion = is_array($schema) && isset($schema['version']) ? $schema['version'] : 0;

        $this->assertArrayHasKey('schema_version', $raw, 'schema_version present in saved file');
        $this->assertSame(
            $expectedVersion,
            $raw['schema_version'],
            'schema_version (' . $expectedVersion . ') present in saved file',
        );
    }

    public function testMigrationNotReapplied(): void
    {
        // Write v1 config
        $this->writeConfig([
            'schema_version' => 1,
            'modules' => [
                'enabled' => ['admin', 'admin-dashboard', 'seo'],
            ],
        ]);

        // First load: migration runs
        $cs1 = new ConfigSettings();
        $cs1->load();
        $enabled1 = $cs1->get('modules.enabled');

        // Second load (new instance): migration should not re-run
        $cs2 = new ConfigSettings();
        $cs2->load();
        $enabled2 = $cs2->get('modules.enabled');

        $this->assertSame(
            $enabled1,
            $enabled2,
            'Second load produces same result (migration not re-applied)',
        );
        $this->assertNotContains(
            'admin-dashboard',
            $enabled2,
            'admin-dashboard still absent on second load',
        );
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testMissingConfigFile(): void
    {
        // Remove config.json
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }

        $cs = new ConfigSettings();
        $cs->load();

        $this->assertSame(
            'Mantra CMS',
            $cs->get('site.name'),
            'Default site.name used when config.json missing',
        );
        $this->assertSame(
            'UTC',
            $cs->get('locale.timezone'),
            'Default timezone used when config.json missing',
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function writeConfig($data): void
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($this->configPath, json_encode($data));
    }
}
