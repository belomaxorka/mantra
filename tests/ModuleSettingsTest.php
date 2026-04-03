<?php declare(strict_types=1);

/**
 * ModuleSettings Tests (PHPUnit 10.x)
 *
 * Tests for schema-driven module settings:
 * - Schema loading from module directory
 * - Defaults from tabs
 * - Migration with callback
 * - Overrides-only saving with unknown keys preservation
 * - get/set/has methods
 * - Module without schema file
 */
class ModuleSettingsTest extends MantraTestCase
{
    private $testModule = 'tmig_testmod';

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure directories exist
        $moduleDir = MANTRA_MODULES . '/' . $this->testModule;
        if (!is_dir($moduleDir)) {
            mkdir($moduleDir, 0o755, true);
        }

        $settingsDir = MANTRA_CONTENT . '/settings';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0o755, true);
        }
    }

    protected function tearDown(): void
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
        $patterns = [
            MANTRA_CONTENT . '/settings/' . $this->testModule . '.json',
            MANTRA_CONTENT . '/settings/tmig_noschema.json',
        ];
        foreach ($patterns as $p) {
            if (file_exists($p)) {
                @unlink($p);
            }
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Schema and defaults
    // ---------------------------------------------------------------

    public function testSchemaLoading(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'api_key', 'type' => 'text', 'default' => ''],
                    ],
                ],
            ],
        ]);

        $ms = new Module\ModuleSettings($this->testModule);
        $schema = $ms->schema();

        $this->assertIsArray($schema, 'Schema loaded as array');
        $this->assertSame(1, $schema['version'], 'Schema version is 1');
        $this->assertNotEmpty($schema['tabs'], 'Schema has tabs');
    }

    public function testDefaultsFromTabs(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'api_key', 'type' => 'text', 'default' => ''],
                        ['path' => 'enabled', 'type' => 'toggle', 'default' => true],
                        ['path' => 'max_items', 'type' => 'number', 'default' => 25],
                    ],
                ],
            ],
        ]);

        // No settings file on disk
        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assertSame('', $ms->get('api_key'), 'Empty string default applied');
        $this->assertTrue($ms->get('enabled'), 'Boolean default applied');
        $this->assertSame(25, $ms->get('max_items'), 'Numeric default applied');
    }

    public function testDefaultsForNestedPaths(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'advanced',
                    'title' => 'Advanced',
                    'fields' => [
                        ['path' => 'cache.enabled', 'type' => 'toggle', 'default' => false],
                        ['path' => 'cache.ttl', 'type' => 'number', 'default' => 3600],
                    ],
                ],
            ],
        ]);

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);

        $this->assertFalse($ms->get('cache.enabled'), 'Nested boolean default applied');
        $this->assertSame(3600, $ms->get('cache.ttl'), 'Nested numeric default applied');
    }

    // ---------------------------------------------------------------
    // Migration
    // ---------------------------------------------------------------

    public function testMigrationWithCallback(): void
    {
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

        $this->writeSettingsFile([
            'analytics_id' => 'UA-12345',
            'schema_version' => 1,
        ]);

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assertFalse($ms->has('analytics_id'), 'Old field removed by migration');
        $this->assertSame(
            'UA-12345',
            $ms->get('tracker_id'),
            'Value migrated to new field name',
        );

        // Verify on disk
        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);
        $this->assertSame(2, $raw['schema_version'], 'schema_version bumped on disk');
    }

    public function testMigrationFromZero(): void
    {
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
        $this->writeSettingsFile(['color' => 'red']);

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assertSame('red', $ms->get('color'), 'Existing value preserved');
        $this->assertTrue($ms->get('_was_migrated'), 'Migration callback ran');
        $this->assertSame(0, $ms->get('_from'), '$from was 0 for missing schema_version');
    }

    public function testMigrationNotReapplied(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'value', 'type' => 'text', 'default' => 'default'],
                    ],
                ],
            ],
        ]);

        $this->writeSettingsFile(['value' => 'custom']);

        // First load
        $ms1 = new Module\ModuleSettings($this->testModule);
        $ms1->load();

        // Second load (fresh instance)
        $ms2 = new Module\ModuleSettings($this->testModule);
        $ms2->load();

        $this->assertSame(
            'custom',
            $ms2->get('value'),
            'Value stable across loads',
        );

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);
        $this->assertArrayHasKey('schema_version', $raw, 'schema_version persisted after first load');
        $this->assertSame(1, $raw['schema_version'], 'schema_version persisted after first load');
    }

    // ---------------------------------------------------------------
    // get/set/has
    // ---------------------------------------------------------------

    public function testGetSetHas(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'key1', 'type' => 'text', 'default' => 'val1'],
                        ['path' => 'key2', 'type' => 'text', 'default' => 'val2'],
                    ],
                ],
            ],
        ]);

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);

        $this->assertTrue($ms->has('key1'), 'has() true for default key');
        $this->assertFalse($ms->has('nonexistent'), 'has() false for missing key');
        $this->assertSame('val1', $ms->get('key1'), 'get() returns default');
        $this->assertSame('fb', $ms->get('missing', 'fb'), 'get() returns fallback');

        $ms->set('key1', 'updated');
        $this->assertSame('updated', $ms->get('key1'), 'set() updates value');
    }

    public function testSetMultiple(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'a', 'type' => 'text', 'default' => ''],
                        ['path' => 'b', 'type' => 'text', 'default' => ''],
                    ],
                ],
            ],
        ]);

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->setMultiple(['a' => 'x', 'b' => 'y']);
        $ms->save();

        $this->assertSame('x', $ms->get('a'), 'First value set');
        $this->assertSame('y', $ms->get('b'), 'Second value set');
    }

    // ---------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------

    public function testOverridesOnlySaving(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'color', 'type' => 'text', 'default' => 'blue'],
                        ['path' => 'size', 'type' => 'number', 'default' => 10],
                    ],
                ],
            ],
        ]);

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        // Only change color, leave size at default
        $ms->set('color', 'red');
        $ms->save();

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);

        $this->assertArrayHasKey('color', $raw, 'Override value saved');
        $this->assertSame('red', $raw['color'], 'Override value saved');
        $this->assertArrayNotHasKey('size', $raw, 'Default value NOT saved (overrides-only)');
        $this->assertArrayHasKey('schema_version', $raw, 'schema_version saved');
    }

    public function testUnknownKeysPreserved(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'name', 'type' => 'text', 'default' => ''],
                    ],
                ],
            ],
        ]);

        // Settings file with extra keys not in schema
        $this->writeSettingsFile([
            'name' => 'Test',
            'custom_plugin_data' => ['foo' => 'bar'],
            'legacy_flag' => true,
        ]);

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->set('name', 'Updated');
        $ms->save();

        $raw = json_decode(file_get_contents(MANTRA_CONTENT . '/settings/' . $this->testModule . '.json'), true);

        $this->assertSame('Updated', $raw['name'], 'Known field updated');
        $this->assertArrayHasKey('custom_plugin_data', $raw, 'Unknown nested key preserved');
        $this->assertSame('bar', $raw['custom_plugin_data']['foo'], 'Unknown nested key preserved');
        $this->assertArrayHasKey('legacy_flag', $raw, 'Unknown scalar key preserved');
        $this->assertTrue($raw['legacy_flag'], 'Unknown scalar key preserved');
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testModuleWithoutSchema(): void
    {
        $noSchemaModule = 'tmig_noschema';

        // Write settings file but no schema
        $settingsPath = MANTRA_CONTENT . '/settings/' . $noSchemaModule . '.json';
        file_put_contents($settingsPath, json_encode(['key' => 'value']));

        $ms = new Module\ModuleSettings($noSchemaModule);
        $schema = $ms->schema();

        $this->assertNull($schema, 'Schema is null for module without schema file');
        $this->assertSame('value', $ms->get('key'), 'Raw settings still readable');
    }

    public function testMissingSettingsFile(): void
    {
        $this->writeModuleSchema([
            'version' => 1,
            'tabs' => [
                [
                    'id' => 'general',
                    'title' => 'General',
                    'fields' => [
                        ['path' => 'mode', 'type' => 'text', 'default' => 'auto'],
                    ],
                ],
            ],
        ]);

        $this->removeSettingsFile();

        $ms = new Module\ModuleSettings($this->testModule);
        $ms->load();

        $this->assertSame(
            'auto',
            $ms->get('mode'),
            'Default value used when settings file missing',
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function writeModuleSchema($schema): void
    {
        $schemaPath = MANTRA_MODULES . '/' . $this->testModule . '/settings.schema.php';
        $content = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $content);
    }

    private function writeSettingsFile($data): void
    {
        $path = MANTRA_CONTENT . '/settings/' . $this->testModule . '.json';
        file_put_contents($path, json_encode($data));
    }

    private function removeSettingsFile(): void
    {
        $path = MANTRA_CONTENT . '/settings/' . $this->testModule . '.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
