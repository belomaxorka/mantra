<?php
/**
 * Database Tests
 * Tests for Database class, schema validation, and defaults
 */

require_once __DIR__ . '/../core/bootstrap.php';

class DatabaseTest {
    private $testDir;
    private $db;
    private $results = array();
    
    public function __construct() {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-db-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        $this->db = new Database($this->testDir);
    }
    
    public function __destruct() {
        // Cleanup test directory
        $this->removeDirectory($this->testDir);
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    public function run() {
        echo "Running Database Tests...\n\n";
        
        $this->testWriteAndRead();
        $this->testDefaultsApplied();
        $this->testValidationRequired();
        $this->testValidationEmail();
        $this->testValidationEnum();
        $this->testSchemaVersion();
        $this->testOptionalEmail();
        $this->testSchemaMigration();
        
        // Extended validation tests
        $this->testValidationTypes();
        $this->testValidationStringLength();
        $this->testValidationPattern();
        $this->testValidationNumericRange();
        $this->testValidationUrl();
        $this->testValidationDate();
        $this->testValidationBoolean();
        $this->testValidationArray();
        $this->testSanitization();
        $this->testValidationMultipleErrors();
        
        $this->printResults();
    }
    
    private function assert($condition, $message) {
        if ($condition) {
            $this->results[] = array('status' => 'PASS', 'message' => $message);
            echo "✓ PASS: $message\n";
        } else {
            $this->results[] = array('status' => 'FAIL', 'message' => $message);
            echo "✗ FAIL: $message\n";
        }
    }
    
    private function testWriteAndRead() {
        echo "\n--- Test: Write and Read ---\n";
        
        // Create test schema
        $this->createTestSchema('test_items', array(
            'version' => 1,
            'defaults' => array(
                'name' => '',
                'count' => 0
            ),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true),
                'count' => array('type' => 'integer', 'required' => false)
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        $id = $db->generateId();
        $data = array('name' => 'Test Item', 'count' => 5);
        
        $written = $db->write('test_items', $id, $data);
        $this->assert($written === true, 'Write operation returns true');
        
        $read = $db->read('test_items', $id);
        $this->assert($read !== null, 'Read returns data');
        $this->assert($read['name'] === 'Test Item', 'Read data matches written data');
        $this->assert($read['_id'] === $id, 'Read data includes _id');
    }
    
    private function testDefaultsApplied() {
        echo "\n--- Test: Defaults Applied ---\n";
        
        $this->createTestSchema('test_defaults', array(
            'version' => 1,
            'defaults' => array(
                'name' => '',
                'status' => 'active',
                'count' => 0
            ),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true),
                'status' => array('type' => 'string', 'required' => true),
                'count' => array('type' => 'integer', 'required' => false)
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        $id = $db->generateId();
        // Only provide name, status and count should get defaults
        $data = array('name' => 'Test');
        
        $written = $db->write('test_defaults', $id, $data);
        $this->assert($written === true, 'Write with partial data succeeds');
        
        $read = $db->read('test_defaults', $id);
        $this->assert($read['status'] === 'active', 'Default status applied');
        $this->assert($read['count'] === 0, 'Default count applied');
    }
    
    private function testValidationRequired() {
        echo "\n--- Test: Validation - Required Fields ---\n";
        
        $this->createTestSchema('test_required', array(
            'version' => 1,
            'defaults' => array('name' => ''),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true)
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        $id = $db->generateId();
        $data = array(); // Missing required field
        
        $exceptionThrown = false;
        try {
            $db->write('test_required', $id, $data);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
            $errors = $e->getErrors();
            $this->assert(isset($errors['name']), 'Validation error for missing required field');
        }
        
        $this->assert($exceptionThrown, 'Exception thrown for missing required field');
    }
    
    private function testValidationEmail() {
        echo "\n--- Test: Validation - Email Format ---\n";
        
        $this->createTestSchema('test_email', array(
            'version' => 1,
            'defaults' => array('email' => ''),
            'fields' => array(
                'email' => array('type' => 'email', 'required' => false)
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        // Test valid email
        $id1 = $db->generateId();
        $data1 = array('email' => 'user@example.com');
        $written1 = $db->write('test_email', $id1, $data1);
        $this->assert($written1 === true, 'Valid email passes validation');
        
        // Test invalid email
        $id2 = $db->generateId();
        $data2 = array('email' => 'invalid-email');
        
        $exceptionThrown = false;
        try {
            $db->write('test_email', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        
        $this->assert($exceptionThrown, 'Invalid email fails validation');
        
        // Test empty email (should pass since not required)
        $id3 = $db->generateId();
        $data3 = array('email' => '');
        $written3 = $db->write('test_email', $id3, $data3);
        $this->assert($written3 === true, 'Empty email passes when not required');
    }
    
    private function testValidationEnum() {
        echo "\n--- Test: Validation - Enum Values ---\n";
        
        $this->createTestSchema('test_enum', array(
            'version' => 1,
            'defaults' => array('role' => 'viewer'),
            'fields' => array(
                'role' => array(
                    'type' => 'enum',
                    'values' => array('admin', 'editor', 'viewer'),
                    'required' => true
                )
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        // Test valid enum
        $id1 = $db->generateId();
        $data1 = array('role' => 'admin');
        $written1 = $db->write('test_enum', $id1, $data1);
        $this->assert($written1 === true, 'Valid enum value passes');
        
        // Test invalid enum
        $id2 = $db->generateId();
        $data2 = array('role' => 'superadmin');
        
        $exceptionThrown = false;
        try {
            $db->write('test_enum', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        
        $this->assert($exceptionThrown, 'Invalid enum value fails validation');
    }
    
    private function testSchemaVersion() {
        echo "\n--- Test: Schema Version ---\n";
        
        $this->createTestSchema('test_version', array(
            'version' => 2,
            'defaults' => array('name' => ''),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true)
            )
        ));
        
        // Create new Database instance to clear schema cache
        $db = new Database($this->testDir);
        
        $id = $db->generateId();
        $data = array('name' => 'Test');
        
        $db->write('test_version', $id, $data);
        $read = $db->read('test_version', $id);
        
        $this->assert(isset($read['schema_version']), 'Schema version is set');
        $this->assert($read['schema_version'] === 2, 'Schema version matches schema');
    }
    
    private function testOptionalEmail() {
        echo "\n--- Test: Optional Email (Real User Schema) ---\n";
        
        // Test with actual users schema
        $id = $this->db->generateId();
        $auth = new Auth();
        
        // Create user without email (like in install.php)
        $userData = array(
            'username' => 'testuser',
            'password' => $auth->hashPassword('testpass123'),
            'role' => 'admin'
        );
        
        $written = $this->db->write('users', $id, $userData);
        $this->assert($written === true, 'User created without email');
        
        $read = $this->db->read('users', $id);
        $this->assert($read !== null, 'User can be read back');
        $this->assert($read['username'] === 'testuser', 'Username is correct');
        $this->assert($read['email'] === '', 'Email defaults to empty string');
        $this->assert($read['role'] === 'admin', 'Role is correct');
    }
    
    private function testSchemaMigration() {
        echo "\n--- Test: Schema Migration on Read ---\n";
        
        // Create initial schema v1
        $this->createTestSchema('test_migrate', array(
            'version' => 1,
            'defaults' => array('name' => ''),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true)
            )
        ));
        
        $db1 = new Database($this->testDir);
        $id = $db1->generateId();
        
        // Write document with v1 schema
        $db1->write('test_migrate', $id, array('name' => 'Old Document'));
        
        // Update schema to v2 with new field
        $this->createTestSchema('test_migrate', array(
            'version' => 2,
            'defaults' => array(
                'name' => '',
                'status' => 'migrated'
            ),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true),
                'status' => array('type' => 'string', 'required' => true)
            )
        ));
        
        // Read with new schema - should apply new defaults
        $db2 = new Database($this->testDir);
        $read = $db2->read('test_migrate', $id);
        
        $this->assert($read['name'] === 'Old Document', 'Original data preserved');
        $this->assert($read['status'] === 'migrated', 'New field added from defaults on read');
        $this->assert($read['schema_version'] === 2, 'Schema version updated on read');
        
        // Read again - should still have the migrated field
        $read2 = $db2->read('test_migrate', $id);
        $this->assert($read2['status'] === 'migrated', 'Migrated field persisted');
    }
    
    private function testValidationTypes() {
        echo "\n--- Test: Validation - Type Checking ---\n";
        
        $this->createTestSchema('test_types', array(
            'version' => 1,
            'defaults' => array(
                'text' => '',
                'number' => 0,
                'flag' => false
            ),
            'fields' => array(
                'text' => array('type' => 'string', 'required' => false),
                'number' => array('type' => 'integer', 'required' => false),
                'flag' => array('type' => 'boolean', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid types
        $id1 = $db->generateId();
        $data1 = array('text' => 'hello', 'number' => 42, 'flag' => true);
        $written1 = $db->write('test_types', $id1, $data1);
        $this->assert($written1 === true, 'Valid types pass validation');
        
        // Invalid string type
        $id2 = $db->generateId();
        $data2 = array('text' => 123);
        $exceptionThrown = false;
        try {
            $db->write('test_types', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Invalid string type fails validation');
        
        // Invalid integer type
        $id3 = $db->generateId();
        $data3 = array('number' => 'not a number');
        $exceptionThrown = false;
        try {
            $db->write('test_types', $id3, $data3);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Invalid integer type fails validation');
    }
    
    private function testValidationStringLength() {
        echo "\n--- Test: Validation - String Length ---\n";
        
        $this->createTestSchema('test_length', array(
            'version' => 1,
            'defaults' => array('username' => ''),
            'fields' => array(
                'username' => array(
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                    'maxLength' => 10
                )
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid length
        $id1 = $db->generateId();
        $data1 = array('username' => 'john');
        $written1 = $db->write('test_length', $id1, $data1);
        $this->assert($written1 === true, 'Valid length passes validation');
        
        // Too short
        $id2 = $db->generateId();
        $data2 = array('username' => 'ab');
        $exceptionThrown = false;
        try {
            $db->write('test_length', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'String too short fails validation');
        
        // Too long
        $id3 = $db->generateId();
        $data3 = array('username' => 'verylongusername');
        $exceptionThrown = false;
        try {
            $db->write('test_length', $id3, $data3);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'String too long fails validation');
    }
    
    private function testValidationPattern() {
        echo "\n--- Test: Validation - Regex Pattern ---\n";
        
        $this->createTestSchema('test_pattern', array(
            'version' => 1,
            'defaults' => array('username' => ''),
            'fields' => array(
                'username' => array(
                    'type' => 'string',
                    'required' => true,
                    'pattern' => '/^[a-zA-Z0-9_-]+$/'
                )
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid pattern
        $id1 = $db->generateId();
        $data1 = array('username' => 'user_name-123');
        $written1 = $db->write('test_pattern', $id1, $data1);
        $this->assert($written1 === true, 'Valid pattern passes validation');
        
        // Invalid pattern (contains spaces)
        $id2 = $db->generateId();
        $data2 = array('username' => 'user name');
        $exceptionThrown = false;
        try {
            $db->write('test_pattern', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Invalid pattern fails validation');
        
        // Invalid pattern (special chars)
        $id3 = $db->generateId();
        $data3 = array('username' => 'user@name!');
        $exceptionThrown = false;
        try {
            $db->write('test_pattern', $id3, $data3);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Special characters fail pattern validation');
    }
    
    private function testValidationNumericRange() {
        echo "\n--- Test: Validation - Numeric Range ---\n";
        
        $this->createTestSchema('test_range', array(
            'version' => 1,
            'defaults' => array('age' => 0, 'score' => 0.0),
            'fields' => array(
                'age' => array(
                    'type' => 'integer',
                    'required' => false,
                    'min' => 18,
                    'max' => 100
                ),
                'score' => array(
                    'type' => 'number',
                    'required' => false,
                    'min' => 0.0,
                    'max' => 10.0
                )
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid range
        $id1 = $db->generateId();
        $data1 = array('age' => 25, 'score' => 7.5);
        $written1 = $db->write('test_range', $id1, $data1);
        $this->assert($written1 === true, 'Valid range passes validation');
        
        // Below minimum
        $id2 = $db->generateId();
        $data2 = array('age' => 15);
        $exceptionThrown = false;
        try {
            $db->write('test_range', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Value below minimum fails validation');
        
        // Above maximum
        $id3 = $db->generateId();
        $data3 = array('score' => 15.5);
        $exceptionThrown = false;
        try {
            $db->write('test_range', $id3, $data3);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Value above maximum fails validation');
    }
    
    private function testValidationUrl() {
        echo "\n--- Test: Validation - URL Format ---\n";
        
        $this->createTestSchema('test_url', array(
            'version' => 1,
            'defaults' => array('website' => ''),
            'fields' => array(
                'website' => array('type' => 'url', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid URL
        $id1 = $db->generateId();
        $data1 = array('website' => 'https://example.com');
        $written1 = $db->write('test_url', $id1, $data1);
        $this->assert($written1 === true, 'Valid URL passes validation');
        
        // Invalid URL
        $id2 = $db->generateId();
        $data2 = array('website' => 'not-a-url');
        $exceptionThrown = false;
        try {
            $db->write('test_url', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Invalid URL fails validation');
    }
    
    private function testValidationDate() {
        echo "\n--- Test: Validation - Date Format ---\n";
        
        $this->createTestSchema('test_date', array(
            'version' => 1,
            'defaults' => array('published' => ''),
            'fields' => array(
                'published' => array('type' => 'date', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid dates
        $id1 = $db->generateId();
        $data1 = array('published' => '2026-03-17');
        $written1 = $db->write('test_date', $id1, $data1);
        $this->assert($written1 === true, 'Valid date (Y-m-d) passes validation');
        
        $id2 = $db->generateId();
        $data2 = array('published' => '2026-03-17 14:30:00');
        $written2 = $db->write('test_date', $id2, $data2);
        $this->assert($written2 === true, 'Valid datetime passes validation');
        
        // Invalid date
        $id3 = $db->generateId();
        $data3 = array('published' => 'not-a-date');
        $exceptionThrown = false;
        try {
            $db->write('test_date', $id3, $data3);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'Invalid date fails validation');
    }
    
    private function testValidationBoolean() {
        echo "\n--- Test: Validation - Boolean Type ---\n";
        
        $this->createTestSchema('test_bool', array(
            'version' => 1,
            'defaults' => array('active' => false),
            'fields' => array(
                'active' => array('type' => 'boolean', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid boolean
        $id1 = $db->generateId();
        $data1 = array('active' => true);
        $written1 = $db->write('test_bool', $id1, $data1);
        $this->assert($written1 === true, 'Valid boolean passes validation');
        
        // Invalid boolean (string)
        $id2 = $db->generateId();
        $data2 = array('active' => 'yes');
        $exceptionThrown = false;
        try {
            $db->write('test_bool', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'String instead of boolean fails validation');
    }
    
    private function testValidationArray() {
        echo "\n--- Test: Validation - Array Type ---\n";
        
        $this->createTestSchema('test_array', array(
            'version' => 1,
            'defaults' => array('tags' => array()),
            'fields' => array(
                'tags' => array('type' => 'array', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Valid array
        $id1 = $db->generateId();
        $data1 = array('tags' => array('php', 'cms', 'web'));
        $written1 = $db->write('test_array', $id1, $data1);
        $this->assert($written1 === true, 'Valid array passes validation');
        
        // Invalid array (string)
        $id2 = $db->generateId();
        $data2 = array('tags' => 'not-an-array');
        $exceptionThrown = false;
        try {
            $db->write('test_array', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        $this->assert($exceptionThrown, 'String instead of array fails validation');
    }
    
    private function testSanitization() {
        echo "\n--- Test: Sanitization ---\n";
        
        $this->createTestSchema('test_sanitize', array(
            'version' => 1,
            'defaults' => array('name' => '', 'description' => ''),
            'fields' => array(
                'name' => array('type' => 'string', 'required' => true),
                'description' => array('type' => 'string', 'required' => false)
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Test trimming whitespace
        $id1 = $db->generateId();
        $data1 = array('name' => '  trimmed  ', 'description' => "\t\nspaces\n\t");
        $db->write('test_sanitize', $id1, $data1);
        $read1 = $db->read('test_sanitize', $id1);
        $this->assert($read1['name'] === 'trimmed', 'Whitespace trimmed from strings');
        $this->assert($read1['description'] === 'spaces', 'Tabs and newlines trimmed');
        
        // Test null byte removal
        $id2 = $db->generateId();
        $data2 = array('name' => "test\0null");
        $db->write('test_sanitize', $id2, $data2);
        $read2 = $db->read('test_sanitize', $id2);
        $this->assert(strpos($read2['name'], "\0") === false, 'Null bytes removed');
        
        // Test nested array sanitization
        $this->createTestSchema('test_sanitize_nested', array(
            'version' => 1,
            'defaults' => array('data' => array()),
            'fields' => array(
                'data' => array('type' => 'array', 'required' => false)
            )
        ));
        
        $db3 = new Database($this->testDir);
        $id3 = $db3->generateId();
        $data3 = array('data' => array('key' => '  value  '));
        $db3->write('test_sanitize_nested', $id3, $data3);
        $read3 = $db3->read('test_sanitize_nested', $id3);
        $this->assert($read3['data']['key'] === 'value', 'Nested array values sanitized');
    }
    
    private function testValidationMultipleErrors() {
        echo "\n--- Test: Validation - Multiple Errors ---\n";
        
        $this->createTestSchema('test_multi_errors', array(
            'version' => 1,
            'defaults' => array(
                'username' => '',
                'email' => '',
                'age' => 0
            ),
            'fields' => array(
                'username' => array(
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3
                ),
                'email' => array(
                    'type' => 'email',
                    'required' => true
                ),
                'age' => array(
                    'type' => 'integer',
                    'required' => true,
                    'min' => 18
                )
            )
        ));
        
        $db = new Database($this->testDir);
        
        // Multiple validation errors
        $id = $db->generateId();
        $data = array(
            'username' => 'ab',  // Too short
            'email' => 'invalid',  // Invalid email
            'age' => 15  // Below minimum
        );
        
        $exceptionThrown = false;
        $errorCount = 0;
        try {
            $db->write('test_multi_errors', $id, $data);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
            $errors = $e->getErrors();
            $errorCount = count($errors);
        }
        
        $this->assert($exceptionThrown, 'Multiple validation errors throw exception');
        $this->assert($errorCount === 3, 'All three validation errors reported');
    }
    
    private function createTestSchema($collection, $schema) {
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        $schemaContent = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $schemaContent);
    }
    
    private function printResults() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Results Summary\n";
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
            echo "\n✓ All tests passed!\n";
        } else {
            echo "\n✗ Some tests failed!\n";
        }
    }
}

// Run tests
$test = new DatabaseTest();
$test->run();
