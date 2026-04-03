<?php declare(strict_types=1);

/**
 * Database Tests
 * Tests for Database class, schema validation, and defaults
 */
class DatabaseTest extends MantraTestCase
{
    private $testDir;
    private $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-db-' . time() . '-' . random_int(1000, 9999);
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0o755, true);
        }
        $this->db = new Database($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestSchemas('test_*.php');
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testWriteAndRead(): void
    {
        $this->createTestSchema('test_items', [
            'version' => 1,
            'defaults' => [
                'name' => '',
                'count' => 0,
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'count' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();
        $data = ['name' => 'Test Item', 'count' => 5];

        $written = $db->write('test_items', $id, $data);
        $this->assertTrue($written, 'Write operation returns true');

        $read = $db->read('test_items', $id);
        $this->assertNotNull($read, 'Read returns data');
        $this->assertSame('Test Item', $read['name'], 'Read data matches written data');
        $this->assertSame($id, $read['_id'], 'Read data includes _id');
    }

    public function testDefaultsApplied(): void
    {
        $this->createTestSchema('test_defaults', [
            'version' => 1,
            'defaults' => [
                'name' => '',
                'status' => 'active',
                'count' => 0,
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'status' => ['type' => 'string', 'required' => true],
                'count' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();
        // Only provide name, status and count should get defaults
        $data = ['name' => 'Test'];

        $written = $db->write('test_defaults', $id, $data);
        $this->assertTrue($written, 'Write with partial data succeeds');

        $read = $db->read('test_defaults', $id);
        $this->assertSame('active', $read['status'], 'Default status applied');
        $this->assertSame(0, $read['count'], 'Default count applied');
    }

    public function testValidationRequired(): void
    {
        $this->createTestSchema('test_required', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();
        $data = []; // Missing required field

        try {
            $db->write('test_required', $id, $data);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors(), 'Validation error for missing required field');
        }
    }

    public function testValidationEmail(): void
    {
        $this->createTestSchema('test_email', [
            'version' => 1,
            'defaults' => ['email' => ''],
            'fields' => [
                'email' => ['type' => 'email', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Test valid email
        $id1 = $db->generateId();
        $data1 = ['email' => 'user@example.com'];
        $written1 = $db->write('test_email', $id1, $data1);
        $this->assertTrue($written1, 'Valid email passes validation');

        // Test invalid email
        $id2 = $db->generateId();
        $data2 = ['email' => 'invalid-email'];

        try {
            $db->write('test_email', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid email fails validation');
        }

        // Test empty email (should pass since not required)
        $id3 = $db->generateId();
        $data3 = ['email' => ''];
        $written3 = $db->write('test_email', $id3, $data3);
        $this->assertTrue($written3, 'Empty email passes when not required');
    }

    public function testValidationEnum(): void
    {
        $this->createTestSchema('test_enum', [
            'version' => 1,
            'defaults' => ['role' => 'viewer'],
            'fields' => [
                'role' => [
                    'type' => 'enum',
                    'values' => ['admin', 'editor', 'viewer'],
                    'required' => true,
                ],
            ],
        ]);

        $db = new Database($this->testDir);

        // Test valid enum
        $id1 = $db->generateId();
        $data1 = ['role' => 'admin'];
        $written1 = $db->write('test_enum', $id1, $data1);
        $this->assertTrue($written1, 'Valid enum value passes');

        // Test invalid enum
        $id2 = $db->generateId();
        $data2 = ['role' => 'superadmin'];

        try {
            $db->write('test_enum', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid enum value fails validation');
        }
    }

    public function testSchemaVersion(): void
    {
        $this->createTestSchema('test_version', [
            'version' => 2,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();
        $data = ['name' => 'Test'];

        $db->write('test_version', $id, $data);
        $read = $db->read('test_version', $id);

        $this->assertArrayHasKey('schema_version', $read, 'Schema version is set');
        $this->assertSame(2, $read['schema_version'], 'Schema version matches schema');
    }

    public function testOptionalEmail(): void
    {
        // Create test schema mimicking users schema
        $this->createTestSchema('test_users', [
            'version' => 1,
            'defaults' => [
                'username' => '',
                'email' => '',
                'password' => '',
                'role' => 'editor',
            ],
            'fields' => [
                'username' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                    'maxLength' => 50,
                ],
                'email' => [
                    'type' => 'email',
                    'required' => false,
                    'maxLength' => 255,
                ],
                'password' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 60,
                ],
                'role' => [
                    'type' => 'enum',
                    'values' => ['admin', 'editor', 'viewer'],
                    'required' => true,
                ],
            ],
        ]);

        $db = new Database($this->testDir);
        $id = $db->generateId();
        $auth = new Auth();

        // Create user without email (like in install.php)
        $userData = [
            'username' => 'testuser',
            'password' => $auth->hashPassword('testpass123'),
            'role' => 'admin',
        ];

        $written = $db->write('test_users', $id, $userData);
        $this->assertTrue($written, 'User created without email');

        $read = $db->read('test_users', $id);
        $this->assertNotNull($read, 'User can be read back');
        $this->assertSame('testuser', $read['username'], 'Username is correct');
        $this->assertSame('', $read['email'], 'Email defaults to empty string');
        $this->assertSame('admin', $read['role'], 'Role is correct');
    }

    public function testSchemaMigration(): void
    {
        // Create initial schema v1
        $this->createTestSchema('test_migrate', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db1 = new Database($this->testDir);
        $id = $db1->generateId();

        // Write document with v1 schema
        $db1->write('test_migrate', $id, ['name' => 'Old Document']);

        // Update schema to v2 with new field
        $this->createTestSchema('test_migrate', [
            'version' => 2,
            'defaults' => [
                'name' => '',
                'status' => 'migrated',
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'status' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Read with new schema - should apply new defaults
        $db2 = new Database($this->testDir);
        $read = $db2->read('test_migrate', $id);

        $this->assertSame('Old Document', $read['name'], 'Original data preserved');
        $this->assertSame('migrated', $read['status'], 'New field added from defaults on read');
        $this->assertSame(2, $read['schema_version'], 'Schema version updated on read');

        // Read again - should still have the migrated field
        $read2 = $db2->read('test_migrate', $id);
        $this->assertSame('migrated', $read2['status'], 'Migrated field persisted');
    }

    public function testValidationTypes(): void
    {
        $this->createTestSchema('test_types', [
            'version' => 1,
            'defaults' => [
                'text' => '',
                'number' => 0,
                'flag' => false,
            ],
            'fields' => [
                'text' => ['type' => 'string', 'required' => false],
                'number' => ['type' => 'integer', 'required' => false],
                'flag' => ['type' => 'boolean', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid types
        $id1 = $db->generateId();
        $data1 = ['text' => 'hello', 'number' => 42, 'flag' => true];
        $written1 = $db->write('test_types', $id1, $data1);
        $this->assertTrue($written1, 'Valid types pass validation');

        // Invalid string type
        $id2 = $db->generateId();
        $data2 = ['text' => 123];
        try {
            $db->write('test_types', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid string type fails validation');
        }

        // Invalid integer type
        $id3 = $db->generateId();
        $data3 = ['number' => 'not a number'];
        try {
            $db->write('test_types', $id3, $data3);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid integer type fails validation');
        }
    }

    public function testValidationStringLength(): void
    {
        $this->createTestSchema('test_length', [
            'version' => 1,
            'defaults' => ['username' => ''],
            'fields' => [
                'username' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                    'maxLength' => 10,
                ],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid length
        $id1 = $db->generateId();
        $data1 = ['username' => 'john'];
        $written1 = $db->write('test_length', $id1, $data1);
        $this->assertTrue($written1, 'Valid length passes validation');

        // Too short
        $id2 = $db->generateId();
        $data2 = ['username' => 'ab'];
        try {
            $db->write('test_length', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'String too short fails validation');
        }

        // Too long
        $id3 = $db->generateId();
        $data3 = ['username' => 'verylongusername'];
        try {
            $db->write('test_length', $id3, $data3);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'String too long fails validation');
        }
    }

    public function testValidationPattern(): void
    {
        $this->createTestSchema('test_pattern', [
            'version' => 1,
            'defaults' => ['username' => ''],
            'fields' => [
                'username' => [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '/^[a-zA-Z0-9_-]+$/',
                ],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid pattern
        $id1 = $db->generateId();
        $data1 = ['username' => 'user_name-123'];
        $written1 = $db->write('test_pattern', $id1, $data1);
        $this->assertTrue($written1, 'Valid pattern passes validation');

        // Invalid pattern (contains spaces)
        $id2 = $db->generateId();
        $data2 = ['username' => 'user name'];
        try {
            $db->write('test_pattern', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid pattern fails validation');
        }

        // Invalid pattern (special chars)
        $id3 = $db->generateId();
        $data3 = ['username' => 'user@name!'];
        try {
            $db->write('test_pattern', $id3, $data3);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Special characters fail pattern validation');
        }
    }

    public function testValidationNumericRange(): void
    {
        $this->createTestSchema('test_range', [
            'version' => 1,
            'defaults' => ['age' => 0, 'score' => 0.0],
            'fields' => [
                'age' => [
                    'type' => 'integer',
                    'required' => false,
                    'min' => 18,
                    'max' => 100,
                ],
                'score' => [
                    'type' => 'number',
                    'required' => false,
                    'min' => 0.0,
                    'max' => 10.0,
                ],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid range
        $id1 = $db->generateId();
        $data1 = ['age' => 25, 'score' => 7.5];
        $written1 = $db->write('test_range', $id1, $data1);
        $this->assertTrue($written1, 'Valid range passes validation');

        // Below minimum
        $id2 = $db->generateId();
        $data2 = ['age' => 15];
        try {
            $db->write('test_range', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Value below minimum fails validation');
        }

        // Above maximum
        $id3 = $db->generateId();
        $data3 = ['score' => 15.5];
        try {
            $db->write('test_range', $id3, $data3);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Value above maximum fails validation');
        }
    }

    public function testValidationUrl(): void
    {
        $this->createTestSchema('test_url', [
            'version' => 1,
            'defaults' => ['website' => ''],
            'fields' => [
                'website' => ['type' => 'url', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid URL
        $id1 = $db->generateId();
        $data1 = ['website' => 'https://example.com'];
        $written1 = $db->write('test_url', $id1, $data1);
        $this->assertTrue($written1, 'Valid URL passes validation');

        // Invalid URL
        $id2 = $db->generateId();
        $data2 = ['website' => 'not-a-url'];
        try {
            $db->write('test_url', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid URL fails validation');
        }
    }

    public function testValidationDate(): void
    {
        $this->createTestSchema('test_date', [
            'version' => 1,
            'defaults' => ['published' => ''],
            'fields' => [
                'published' => ['type' => 'date', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid dates
        $id1 = $db->generateId();
        $data1 = ['published' => '2026-03-17'];
        $written1 = $db->write('test_date', $id1, $data1);
        $this->assertTrue($written1, 'Valid date (Y-m-d) passes validation');

        $id2 = $db->generateId();
        $data2 = ['published' => '2026-03-17 14:30:00'];
        $written2 = $db->write('test_date', $id2, $data2);
        $this->assertTrue($written2, 'Valid datetime passes validation');

        // Invalid date
        $id3 = $db->generateId();
        $data3 = ['published' => 'not-a-date'];
        try {
            $db->write('test_date', $id3, $data3);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'Invalid date fails validation');
        }
    }

    public function testValidationBoolean(): void
    {
        $this->createTestSchema('test_bool', [
            'version' => 1,
            'defaults' => ['active' => false],
            'fields' => [
                'active' => ['type' => 'boolean', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid boolean
        $id1 = $db->generateId();
        $data1 = ['active' => true];
        $written1 = $db->write('test_bool', $id1, $data1);
        $this->assertTrue($written1, 'Valid boolean passes validation');

        // Invalid boolean (string)
        $id2 = $db->generateId();
        $data2 = ['active' => 'yes'];
        try {
            $db->write('test_bool', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'String instead of boolean fails validation');
        }
    }

    public function testValidationArray(): void
    {
        $this->createTestSchema('test_array', [
            'version' => 1,
            'defaults' => ['tags' => []],
            'fields' => [
                'tags' => ['type' => 'array', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Valid array
        $id1 = $db->generateId();
        $data1 = ['tags' => ['php', 'cms', 'web']];
        $written1 = $db->write('test_array', $id1, $data1);
        $this->assertTrue($written1, 'Valid array passes validation');

        // Invalid array (string)
        $id2 = $db->generateId();
        $data2 = ['tags' => 'not-an-array'];
        try {
            $db->write('test_array', $id2, $data2);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertTrue(true, 'String instead of array fails validation');
        }
    }

    public function testSanitization(): void
    {
        $this->createTestSchema('test_sanitize', [
            'version' => 1,
            'defaults' => ['name' => '', 'description' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Test trimming whitespace
        $id1 = $db->generateId();
        $data1 = ['name' => '  trimmed  ', 'description' => "\t\nspaces\n\t"];
        $db->write('test_sanitize', $id1, $data1);
        $read1 = $db->read('test_sanitize', $id1);
        $this->assertSame('trimmed', $read1['name'], 'Whitespace trimmed from strings');
        $this->assertSame('spaces', $read1['description'], 'Tabs and newlines trimmed');

        // Test null byte removal
        $id2 = $db->generateId();
        $data2 = ['name' => "test\0null"];
        $db->write('test_sanitize', $id2, $data2);
        $read2 = $db->read('test_sanitize', $id2);
        $this->assertStringNotContainsString("\0", $read2['name'], 'Null bytes removed');

        // Test nested array sanitization
        $this->createTestSchema('test_sanitize_nested', [
            'version' => 1,
            'defaults' => ['data' => []],
            'fields' => [
                'data' => ['type' => 'array', 'required' => false],
            ],
        ]);

        $db3 = new Database($this->testDir);
        $id3 = $db3->generateId();
        $data3 = ['data' => ['key' => '  value  ']];
        $db3->write('test_sanitize_nested', $id3, $data3);
        $read3 = $db3->read('test_sanitize_nested', $id3);
        $this->assertSame('value', $read3['data']['key'], 'Nested array values sanitized');
    }

    public function testDelete(): void
    {
        $this->createTestSchema('test_delete', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        // Create and delete
        $id = $db->generateId();
        $db->write('test_delete', $id, ['name' => 'To Delete']);

        $exists = $db->exists('test_delete', $id);
        $this->assertTrue($exists, 'Document exists after creation');

        $deleted = $db->delete('test_delete', $id);
        $this->assertTrue($deleted, 'Delete operation returns true');

        $existsAfter = $db->exists('test_delete', $id);
        $this->assertFalse($existsAfter, 'Document does not exist after deletion');

        $read = $db->read('test_delete', $id);
        $this->assertNull($read, 'Read returns null for deleted document');
    }

    public function testExists(): void
    {
        $this->createTestSchema('test_exists', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();

        $existsBefore = $db->exists('test_exists', $id);
        $this->assertFalse($existsBefore, 'Non-existent document returns false');

        $db->write('test_exists', $id, ['name' => 'Test']);

        $existsAfter = $db->exists('test_exists', $id);
        $this->assertTrue($existsAfter, 'Existing document returns true');
    }

    public function testQuery(): void
    {
        $this->createTestSchema('test_query', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        // Create multiple items
        $db->write('test_query', 'item1', ['name' => 'First']);
        $db->write('test_query', 'item2', ['name' => 'Second']);
        $db->write('test_query', 'item3', ['name' => 'Third']);

        $results = $db->query('test_query');

        $this->assertCount(3, $results, 'Query returns all items');
        $this->assertArrayHasKey('_id', $results[0], 'Query results include _id');
    }

    public function testQueryWithFilters(): void
    {
        $this->createTestSchema('test_filter', [
            'version' => 1,
            'defaults' => ['name' => '', 'status' => 'active'],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'status' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_filter', 'f1', ['name' => 'Active 1', 'status' => 'active']);
        $db->write('test_filter', 'f2', ['name' => 'Inactive', 'status' => 'inactive']);
        $db->write('test_filter', 'f3', ['name' => 'Active 2', 'status' => 'active']);

        $results = $db->query('test_filter', ['status' => 'active']);

        $this->assertCount(2, $results, 'Filter returns correct count');
        $this->assertSame('active', $results[0]['status'], 'Filtered results match criteria');
        $this->assertSame('active', $results[1]['status'], 'All filtered results match');
    }

    public function testQueryWithSort(): void
    {
        $this->createTestSchema('test_sort', [
            'version' => 1,
            'defaults' => ['name' => '', 'order' => 0],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'order' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_sort', 's1', ['name' => 'Charlie', 'order' => 3]);
        $db->write('test_sort', 's2', ['name' => 'Alice', 'order' => 1]);
        $db->write('test_sort', 's3', ['name' => 'Bob', 'order' => 2]);

        // Sort ascending
        $resultsAsc = $db->query('test_sort', [], ['sort' => 'name', 'order' => 'asc']);
        $this->assertSame('Alice', $resultsAsc[0]['name'], 'Sort ascending works');
        $this->assertSame('Charlie', $resultsAsc[2]['name'], 'Sort ascending order correct');

        // Sort descending
        $resultsDesc = $db->query('test_sort', [], ['sort' => 'name', 'order' => 'desc']);
        $this->assertSame('Charlie', $resultsDesc[0]['name'], 'Sort descending works');
        $this->assertSame('Alice', $resultsDesc[2]['name'], 'Sort descending order correct');
    }

    public function testQueryWithLimit(): void
    {
        $this->createTestSchema('test_limit', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        for ($i = 1; $i <= 10; $i++) {
            $db->write('test_limit', 'item' . $i, ['name' => 'Item ' . $i]);
        }

        // Test limit
        $limited = $db->query('test_limit', [], ['limit' => 3]);
        $this->assertCount(3, $limited, 'Limit restricts result count');

        // Test offset
        $offset = $db->query('test_limit', [], ['limit' => 3, 'offset' => 5]);
        $this->assertCount(3, $offset, 'Offset with limit returns correct count');
    }

    public function testMetadata(): void
    {
        $this->createTestSchema('test_metadata', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $id = $db->generateId();
        $db->write('test_metadata', $id, ['name' => 'Test']);
        $read = $db->read('test_metadata', $id);

        $this->assertArrayHasKey('created_at', $read, 'created_at is set');
        $this->assertArrayHasKey('updated_at', $read, 'updated_at is set');
        $this->assertNotEmpty($read['created_at'], 'created_at has value');
        $this->assertNotEmpty($read['updated_at'], 'updated_at has value');

        $createdAt = $read['created_at'];
        $updatedAt = $read['updated_at'];

        // Update document (wait 1 second to ensure different timestamp)
        sleep(1);
        $db->write('test_metadata', $id, ['name' => 'Updated']);
        $readUpdated = $db->read('test_metadata', $id);

        $this->assertSame($createdAt, $readUpdated['created_at'], 'created_at unchanged on update');
        $this->assertNotSame($updatedAt, $readUpdated['updated_at'], 'updated_at changed on update');
        $this->assertGreaterThan($createdAt, $readUpdated['updated_at'], 'updated_at is after created_at');
    }

    public function testSecurity(): void
    {
        $db = new Database($this->testDir);

        // Test invalid collection name (directory traversal)
        try {
            $db->write('../evil', 'test', ['data' => 'hack']);
            $this->fail('Expected Exception for directory traversal in collection name');
        } catch (Exception $e) {
            $this->assertStringContainsString('Invalid collection name', $e->getMessage(), 'Correct error message for invalid collection');
        }

        // Test invalid ID (directory traversal)
        $this->createTestSchema('test_security', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        try {
            $db->write('test_security', '../evil-id', ['name' => 'hack']);
            $this->fail('Expected Exception for directory traversal in ID');
        } catch (Exception $e) {
            $this->assertStringContainsString('Invalid ID', $e->getMessage(), 'Correct error message for invalid ID');
        }

        // Test valid collection and ID
        $validWrite = $db->write('test_security', 'valid-id_123', ['name' => 'Safe']);
        $this->assertTrue($validWrite, 'Valid collection and ID names work');
    }

    public function testValidationMultipleErrors(): void
    {
        $this->createTestSchema('test_multi_errors', [
            'version' => 1,
            'defaults' => [
                'username' => '',
                'email' => '',
                'age' => 0,
            ],
            'fields' => [
                'username' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                ],
                'email' => [
                    'type' => 'email',
                    'required' => true,
                ],
                'age' => [
                    'type' => 'integer',
                    'required' => true,
                    'min' => 18,
                ],
            ],
        ]);

        $db = new Database($this->testDir);

        // Multiple validation errors
        $id = $db->generateId();
        $data = [
            'username' => 'ab',  // Too short
            'email' => 'invalid',  // Invalid email
            'age' => 15,  // Below minimum
        ];

        $errorCount = 0;
        try {
            $db->write('test_multi_errors', $id, $data);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $errors = $e->getErrors();
            $errorCount = count($errors);
        }

        $this->assertSame(3, $errorCount, 'All three validation errors reported');
    }

    public function testSecurityExtended(): void
    {
        $this->createTestSchema('test_security_ext', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        // Test absolute path in collection
        try {
            $db->write('/etc/passwd', 'test', ['name' => 'hack']);
            $this->fail('Expected Exception for absolute path in collection');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Absolute path in collection blocked');
        }

        // Test null byte in collection name
        try {
            $db->write("test\0null", 'test', ['name' => 'hack']);
            $this->fail('Expected Exception for null byte in collection name');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Null byte in collection name blocked');
        }

        // Test null byte in ID
        try {
            $db->write('test_security_ext', "test\0null", ['name' => 'hack']);
            $this->fail('Expected Exception for null byte in ID');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Null byte in ID blocked');
        }

        // Test special characters in collection
        try {
            $db->write('test@collection!', 'test', ['name' => 'hack']);
            $this->fail('Expected Exception for special characters in collection');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Special characters in collection blocked');
        }

        // Test special characters in ID
        try {
            $db->write('test_security_ext', 'test@id!', ['name' => 'hack']);
            $this->fail('Expected Exception for special characters in ID');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Special characters in ID blocked');
        }

        // Test backslash path traversal
        try {
            $db->write('..\\evil', 'test', ['name' => 'hack']);
            $this->fail('Expected Exception for backslash path traversal');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Backslash path traversal blocked');
        }
    }

    public function testErrorHandling(): void
    {
        $this->createTestSchema('test_errors', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        // Test reading non-existent document
        $result = $db->read('test_errors', 'nonexistent');
        $this->assertNull($result, 'Reading non-existent document returns null');

        // Test deleting non-existent document (should not throw)
        $deleted = $db->delete('test_errors', 'nonexistent');
        $this->assertFalse($deleted, 'Deleting non-existent document returns false');

        // Test corrupted JSON recovery
        $id = $db->generateId();
        $db->write('test_errors', $id, ['name' => 'Test']);

        // Corrupt the JSON file
        $collectionDir = $this->testDir . '/test_errors';
        $jsonFile = $collectionDir . '/' . $id . '.json';
        if (file_exists($jsonFile)) {
            file_put_contents($jsonFile, '{invalid json}');

            // Try to read corrupted file - should throw or return null
            try {
                $result = $db->read('test_errors', $id);
                // If no exception, result should be null
                $this->assertNull($result, 'Corrupted JSON returns null or throws exception');
            } catch (Exception $e) {
                $this->assertTrue(true, 'Corrupted JSON throws exception');
            }
        }

        // Test invalid schema file
        $invalidSchemaPath = MANTRA_CORE . '/schemas/test_invalid_schema.php';
        file_put_contents($invalidSchemaPath, "<?php\nreturn 'not an array';\n");

        $db2 = new Database($this->testDir);
        // Should handle invalid schema gracefully
        $id2 = $db2->generateId();
        $written = $db2->write('test_invalid_schema', $id2, ['data' => 'test']);
        $this->assertTrue($written, 'Invalid schema handled gracefully');

        @unlink($invalidSchemaPath);
    }

    public function testLogging(): void
    {
        // Check if logging is enabled and configured
        $logDir = MANTRA_STORAGE . '/logs';
        if (!is_dir($logDir)) {
            $this->markTestSkipped('Logging directory not configured');
        }

        $this->createTestSchema('test_logging', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        $errorLogFile = $logDir . '/error-' . date('Y-m-d') . '.log';

        // Trigger a validation error (should be logged to error log)
        $id = $db->generateId();
        $errorLogged = false;
        try {
            $db->write('test_logging', $id, []); // Missing required field
        } catch (SchemaValidationException $e) {
            // Check if error was logged
            if (file_exists($errorLogFile)) {
                $logContent = file_get_contents($errorLogFile);
                $errorLogged = str_contains($logContent, 'Schema validation failed')
                    && str_contains($logContent, 'test_logging');
            }
        }
        $this->assertTrue($errorLogged, 'Validation errors are logged to error log');

        // Trigger an invalid collection error (should be logged to error log)
        $securityErrorLogged = false;
        try {
            $db->write('../invalid', 'test', ['name' => 'test']);
        } catch (Exception $e) {
            // Check if error was logged
            if (file_exists($errorLogFile)) {
                $logContent = file_get_contents($errorLogFile);
                $securityErrorLogged = str_contains($logContent, 'Invalid collection name');
            }
        }
        $this->assertTrue($securityErrorLogged, 'Security errors are logged to error log');

        // Test successful write
        $id2 = $db->generateId();
        $db->write('test_logging', $id2, ['name' => 'Test']);
        $this->assertTrue(true, 'Successful operations complete without errors');
    }

    public function testSchemaMigrationWithCallback(): void
    {
        // Create initial schema v1 with old field name
        $this->createTestSchema('test_migrate_cb', [
            'version' => 1,
            'defaults' => ['old_name' => ''],
            'fields' => [
                'old_name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db1 = new Database($this->testDir);
        $id = $db1->generateId();

        // Write document with v1 schema
        $db1->write('test_migrate_cb', $id, ['old_name' => 'Test Value']);

        // Update schema to v2 with migrate callback that renames field
        // Must write closure directly as PHP code, not via var_export
        $schemaPath = MANTRA_CORE . '/schemas/test_migrate_cb.php';
        $schemaContent = <<<'PHP'
            <?php
            return array(
                'version' => 2,
                'defaults' => array('new_name' => ''),
                'fields' => array(
                    'new_name' => array('type' => 'string', 'required' => true)
                ),
                'migrate' => function($doc, $from, $to) {
                    if ($from < 2 && isset($doc['old_name'])) {
                        $doc['new_name'] = $doc['old_name'];
                        unset($doc['old_name']);
                    }
                    $doc['schema_version'] = 2;
                    return $doc;
                }
            );
            PHP;
        file_put_contents($schemaPath, $schemaContent);

        // Read with new schema - should trigger migration
        $db2 = new Database($this->testDir);
        $read = $db2->read('test_migrate_cb', $id);

        $this->assertArrayNotHasKey('old_name', $read, 'Old field removed by migration');
        $this->assertArrayHasKey('new_name', $read, 'New field added by migration');
        $this->assertSame('Test Value', $read['new_name'], 'Data preserved during migration');
        $this->assertSame(2, $read['schema_version'], 'Schema version updated to 2');

        // Read again - should not re-migrate
        $read2 = $db2->read('test_migrate_cb', $id);
        $this->assertSame('Test Value', $read2['new_name'], 'Migration persisted correctly');
    }

    public function testCollectionAutoCreation(): void
    {
        $this->createTestSchema('test_autocreate', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        // Verify collection directory doesn't exist
        $collectionPath = $this->testDir . '/test_autocreate';
        if (is_dir($collectionPath)) {
            $this->removeDirectory($collectionPath);
        }
        $this->assertDirectoryDoesNotExist($collectionPath, 'Collection directory does not exist initially');

        // Write to non-existent collection
        $id = $db->generateId();
        $written = $db->write('test_autocreate', $id, ['name' => 'Auto Created']);

        $this->assertTrue($written, 'Write to non-existent collection succeeds');
        $this->assertDirectoryExists($collectionPath, 'Collection directory created automatically');

        // Verify data was written
        $read = $db->read('test_autocreate', $id);
        $this->assertNotNull($read, 'Data readable from auto-created collection');
        $this->assertSame('Auto Created', $read['name'], 'Data correct in auto-created collection');
    }

    public function testReadCollectionWithPartialErrors(): void
    {
        $this->createTestSchema('test_partial', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        // Create multiple valid documents
        $db->write('test_partial', 'valid1', ['name' => 'Valid 1']);
        $db->write('test_partial', 'valid2', ['name' => 'Valid 2']);
        $db->write('test_partial', 'valid3', ['name' => 'Valid 3']);

        // Corrupt one document
        $collectionPath = $this->testDir . '/test_partial';
        $corruptedFile = $collectionPath . '/valid2.json';
        file_put_contents($corruptedFile, '{invalid json content}');

        // Query collection - should skip corrupted file
        $results = $db->query('test_partial');

        $this->assertCount(2, $results, 'Query returns only valid documents');

        $names = [];
        foreach ($results as $item) {
            $names[] = $item['name'];
        }
        $this->assertContains('Valid 1', $names, 'First valid document included');
        $this->assertContains('Valid 3', $names, 'Third valid document included');
        $this->assertNotContains('Valid 2', $names, 'Corrupted document excluded');
    }

    public function testFileSizeLimit(): void
    {
        $this->createTestSchema('test_filesize', [
            'version' => 1,
            'defaults' => ['data' => ''],
            'fields' => ['data' => ['type' => 'string', 'required' => false]],
        ]);

        $db = new Database($this->testDir);

        // Create data larger than 10MB limit
        $largeData = str_repeat('x', 11 * 1024 * 1024); // 11MB

        $id = $db->generateId();

        try {
            $db->write('test_filesize', $id, ['data' => $largeData]);
            $this->fail('Expected Exception for oversized data');
        } catch (Exception $e) {
            $this->assertStringContainsString('exceeds maximum limit', $e->getMessage(), 'Correct exception message for size limit');
        }

        // Verify file was not created
        $exists = $db->exists('test_filesize', $id);
        $this->assertFalse($exists, 'Oversized document not written to disk');
    }

    public function testQueryNonExistentCollection(): void
    {
        $db = new Database($this->testDir);

        // Query collection that doesn't exist
        $results = $db->query('nonexistent_collection');

        $this->assertIsArray($results, 'Query returns array for non-existent collection');
        $this->assertCount(0, $results, 'Query returns empty array for non-existent collection');

        // Query with filters on non-existent collection
        $resultsFiltered = $db->query('nonexistent_collection', ['status' => 'active']);
        $this->assertCount(0, $resultsFiltered, 'Filtered query returns empty array');

        // Read from non-existent collection
        $read = $db->read('nonexistent_collection', 'some-id');
        $this->assertNull($read, 'Read returns null for non-existent collection');

        // Exists check on non-existent collection
        $exists = $db->exists('nonexistent_collection', 'some-id');
        $this->assertFalse($exists, 'Exists returns false for non-existent collection');
    }

    public function testEmptyStringRequiredField(): void
    {
        $this->createTestSchema('test_empty_required', [
            'version' => 1,
            'defaults' => ['name' => '', 'description' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Test empty string for required field - should fail
        $id = $db->generateId();

        try {
            $db->write('test_empty_required', $id, ['name' => '']);
            $this->fail('Expected SchemaValidationException');
        } catch (SchemaValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors(), 'Validation error for empty required field');
        }

        // Test empty string for optional field - should pass
        $id2 = $db->generateId();
        $written = $db->write('test_empty_required', $id2, ['name' => 'Valid Name', 'description' => '']);
        $this->assertTrue($written, 'Empty string allowed for optional field');

        $read = $db->read('test_empty_required', $id2);
        $this->assertSame('', $read['description'], 'Empty string preserved for optional field');
    }

    public function testMetadataOverride(): void
    {
        $this->createTestSchema('test_metadata_override', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
        ]);

        $db = new Database($this->testDir);

        // Try to manually set created_at and updated_at
        $id = $db->generateId();
        $customCreatedAt = '2020-01-01 00:00:00';
        $customUpdatedAt = '2020-01-01 00:00:00';

        $db->write('test_metadata_override', $id, [
            'name' => 'Test',
            'created_at' => $customCreatedAt,
            'updated_at' => $customUpdatedAt,
        ]);

        $read = $db->read('test_metadata_override', $id);

        // Check if custom timestamps were preserved or overwritten
        $this->assertSame($customCreatedAt, $read['created_at'], 'Manually provided created_at is preserved on create');
        $this->assertNotSame($customUpdatedAt, $read['updated_at'], 'updated_at is always set to current time');

        // Update document with custom created_at - should preserve original
        sleep(1);
        $newCustomCreatedAt = '2021-01-01 00:00:00';
        $db->write('test_metadata_override', $id, [
            'name' => 'Updated',
            'created_at' => $newCustomCreatedAt,
        ]);

        $readUpdated = $db->read('test_metadata_override', $id);

        // On update, created_at is immutable - original value is preserved, ignoring provided value
        $this->assertSame($customCreatedAt, $readUpdated['created_at'], 'Original created_at preserved on update (provided value ignored)');
        $this->assertGreaterThan($read['updated_at'], $readUpdated['updated_at'], 'updated_at updated to current time');
    }

    public function testLargeCollectionPerformance(): void
    {
        $this->createTestSchema('test_performance', [
            'version' => 1,
            'defaults' => ['name' => '', 'value' => 0],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'value' => ['type' => 'integer', 'required' => false],
            ],
        ]);

        $db = new Database($this->testDir);

        // Create 100 documents
        $itemCount = 100;
        $startTime = microtime(true);

        for ($i = 1; $i <= $itemCount; $i++) {
            $db->write('test_performance', 'item' . $i, [
                'name' => 'Item ' . $i,
                'value' => $i,
            ]);
        }

        $writeTime = microtime(true) - $startTime;
        $this->assertLessThan(10, $writeTime, 'Writing ' . $itemCount . ' documents completes in reasonable time');

        // Query all documents
        $startTime = microtime(true);
        $results = $db->query('test_performance');
        $queryTime = microtime(true) - $startTime;

        $this->assertCount($itemCount, $results, 'Query returns all ' . $itemCount . ' documents');
        $this->assertLessThan(5, $queryTime, 'Querying ' . $itemCount . ' documents completes in reasonable time');

        // Query with filter
        $startTime = microtime(true);
        $filtered = $db->query('test_performance', [], ['sort' => 'value', 'order' => 'desc', 'limit' => 10]);
        $filterTime = microtime(true) - $startTime;

        $this->assertCount(10, $filtered, 'Filtered query returns correct count');
        $this->assertSame($itemCount, $filtered[0]['value'], 'Numeric sorting works correctly (desc order)');
        $this->assertSame($itemCount - 9, $filtered[9]['value'], 'Numeric sorting order is correct');
        $this->assertLessThan(5, $filterTime, 'Filtered query completes in reasonable time');

        // Individual reads
        $startTime = microtime(true);
        for ($i = 1; $i <= 10; $i++) {
            $db->read('test_performance', 'item' . $i);
        }
        $readTime = microtime(true) - $startTime;

        $this->assertLessThan(2, $readTime, 'Individual reads complete in reasonable time');
    }

    public function testCountWithoutFilters(): void
    {
        $this->createTestSchema('test_count', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_count', 'a', ['name' => 'Alpha']);
        $db->write('test_count', 'b', ['name' => 'Beta']);
        $db->write('test_count', 'c', ['name' => 'Gamma']);

        $this->assertSame(3, $db->count('test_count'));
    }

    public function testCountWithFilters(): void
    {
        $this->createTestSchema('test_count_f', [
            'version' => 1,
            'defaults' => ['name' => '', 'status' => 'draft'],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'status' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_count_f', 'a', ['name' => 'A', 'status' => 'published']);
        $db->write('test_count_f', 'b', ['name' => 'B', 'status' => 'draft']);
        $db->write('test_count_f', 'c', ['name' => 'C', 'status' => 'published']);

        $this->assertSame(2, $db->count('test_count_f', ['status' => 'published']));
        $this->assertSame(1, $db->count('test_count_f', ['status' => 'draft']));
    }

    public function testCountEmptyCollection(): void
    {
        $db = new Database($this->testDir);
        $this->assertSame(0, $db->count('nonexistent'));
    }

    public function testListIds(): void
    {
        $this->createTestSchema('test_list', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_list', 'x1', ['name' => 'One']);
        $db->write('test_list', 'x2', ['name' => 'Two']);

        $ids = $db->listIds('test_list');
        sort($ids);

        $this->assertCount(2, $ids);
        $this->assertSame('x1', $ids[0]);
        $this->assertSame('x2', $ids[1]);
    }

    public function testCollectionCachePreventsDuplicateReads(): void
    {
        $this->createTestSchema('test_cache', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        for ($i = 1; $i <= 50; $i++) {
            $db->write('test_cache', 'item' . $i, ['name' => 'Item ' . $i]);
        }

        // First call reads from disk, second uses cache — should be faster
        $start1 = microtime(true);
        $result1 = $db->query('test_cache');
        $time1 = microtime(true) - $start1;

        $start2 = microtime(true);
        $result2 = $db->query('test_cache');
        $time2 = microtime(true) - $start2;

        $this->assertCount(50, $result1);
        $this->assertCount(50, $result2);
        // Cached read should be significantly faster
        $this->assertLessThan($time1, $time2, 'Cached collection read is faster');
    }

    public function testCacheInvalidatedOnWrite(): void
    {
        $this->createTestSchema('test_cache_inv', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_cache_inv', 'a', ['name' => 'Alpha']);
        $results1 = $db->query('test_cache_inv');
        $this->assertCount(1, $results1);

        // Write a new document — cache should be invalidated
        $db->write('test_cache_inv', 'b', ['name' => 'Beta']);
        $results2 = $db->query('test_cache_inv');
        $this->assertCount(2, $results2);
    }

    public function testCacheInvalidatedOnDelete(): void
    {
        $this->createTestSchema('test_cache_del', [
            'version' => 1,
            'defaults' => ['name' => ''],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $db = new Database($this->testDir);

        $db->write('test_cache_del', 'a', ['name' => 'Alpha']);
        $db->write('test_cache_del', 'b', ['name' => 'Beta']);
        $results1 = $db->query('test_cache_del');
        $this->assertCount(2, $results1);

        // Delete — cache should be invalidated
        $db->delete('test_cache_del', 'a');
        $results2 = $db->query('test_cache_del');
        $this->assertCount(1, $results2);
    }
}
