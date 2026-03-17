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
        
        $id = $this->db->generateId();
        $data = array('name' => 'Test Item', 'count' => 5);
        
        $written = $this->db->write('test_items', $id, $data);
        $this->assert($written === true, 'Write operation returns true');
        
        $read = $this->db->read('test_items', $id);
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
        
        $id = $this->db->generateId();
        // Only provide name, status and count should get defaults
        $data = array('name' => 'Test');
        
        $written = $this->db->write('test_defaults', $id, $data);
        $this->assert($written === true, 'Write with partial data succeeds');
        
        $read = $this->db->read('test_defaults', $id);
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
        
        $id = $this->db->generateId();
        $data = array(); // Missing required field
        
        $exceptionThrown = false;
        try {
            $this->db->write('test_required', $id, $data);
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
        
        // Test valid email
        $id1 = $this->db->generateId();
        $data1 = array('email' => 'user@example.com');
        $written1 = $this->db->write('test_email', $id1, $data1);
        $this->assert($written1 === true, 'Valid email passes validation');
        
        // Test invalid email
        $id2 = $this->db->generateId();
        $data2 = array('email' => 'invalid-email');
        
        $exceptionThrown = false;
        try {
            $this->db->write('test_email', $id2, $data2);
        } catch (SchemaValidationException $e) {
            $exceptionThrown = true;
        }
        
        $this->assert($exceptionThrown, 'Invalid email fails validation');
        
        // Test empty email (should pass since not required)
        $id3 = $this->db->generateId();
        $data3 = array('email' => '');
        $written3 = $this->db->write('test_email', $id3, $data3);
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
        
        // Test valid enum
        $id1 = $this->db->generateId();
        $data1 = array('role' => 'admin');
        $written1 = $this->db->write('test_enum', $id1, $data1);
        $this->assert($written1 === true, 'Valid enum value passes');
        
        // Test invalid enum
        $id2 = $this->db->generateId();
        $data2 = array('role' => 'superadmin');
        
        $exceptionThrown = false;
        try {
            $this->db->write('test_enum', $id2, $data2);
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
        
        $id = $this->db->generateId();
        $data = array('name' => 'Test');
        
        $this->db->write('test_version', $id, $data);
        $read = $this->db->read('test_version', $id);
        
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
