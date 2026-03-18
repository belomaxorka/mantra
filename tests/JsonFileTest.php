<?php
/**
 * JsonFile Tests
 * Tests for low-level JSON file operations: locking, atomic writes, backups
 */

require_once __DIR__ . '/../core/bootstrap.php';

class JsonFileTest {
    private $testDir;
    private $results = array();

    public function __construct() {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-jsonfile-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
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
        echo "Running JsonFile Tests...\n\n";

        $this->testWriteAndRead();
        $this->testReadNonExistent();
        $this->testWriteCreatesDirectory();
        $this->testAtomicWrite();
        $this->testReadSafe();
        $this->testWriteSafe();
        $this->testLockFileCreation();
        $this->testInvalidJson();
        $this->testEmptyArray();
        $this->testNestedData();
        $this->testUnicodeData();
        $this->testLargeData();
        $this->testConcurrentReads();

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
        $path = $this->testDir . '/test.json';
        $data = array('key' => 'value', 'number' => 42);

        $result = JsonFile::write($path, $data);
        $this->assert($result === true, 'write() returns true on success');

        $read = JsonFile::read($path);
        $this->assert(is_array($read), 'read() returns array');
        $this->assert($read['key'] === 'value', 'read() returns correct data');
        $this->assert($read['number'] === 42, 'read() preserves data types');
    }

    private function testReadNonExistent() {
        $path = $this->testDir . '/non-existent.json';

        try {
            JsonFile::read($path);
            $this->assert(false, 'read() throws exception for non-existent file');
        } catch (JsonFileException $e) {
            $this->assert(true, 'read() throws JsonFileException for non-existent file');
            $this->assert($e->getPath() === $path, 'Exception contains file path');
        }
    }

    private function testWriteCreatesDirectory() {
        $path = $this->testDir . '/subdir/nested/file.json';
        $data = array('test' => 'data');

        $result = JsonFile::write($path, $data);
        $this->assert($result === true, 'write() creates nested directories');
        $this->assert(file_exists($path), 'File exists after write with nested path');
    }

    private function testAtomicWrite() {
        $path = $this->testDir . '/atomic.json';
        $data = array('version' => 1);

        JsonFile::write($path, $data);

        // Write again - should replace atomically
        $data['version'] = 2;
        JsonFile::write($path, $data);

        $read = JsonFile::read($path);
        $this->assert($read['version'] === 2, 'Atomic write replaces file correctly');

        // Check no temp files left behind
        $tempFiles = glob($this->testDir . '/*.tmp.*');
        $this->assert(count($tempFiles) === 0, 'No temporary files left after atomic write');
    }

    private function testReadSafe() {
        $path = $this->testDir . '/safe-read.json';

        // Test with non-existent file
        $result = JsonFile::readSafe($path, array('default' => true));
        $this->assert($result['default'] === true, 'readSafe() returns default for non-existent file');

        // Test with valid file
        JsonFile::write($path, array('data' => 'value'));
        $result = JsonFile::readSafe($path);
        $this->assert($result['data'] === 'value', 'readSafe() reads valid file');

        // Test with corrupted file
        file_put_contents($path, '{invalid json');
        $result = JsonFile::readSafe($path, array('fallback' => true));
        $this->assert($result['fallback'] === true, 'readSafe() returns default for corrupted file');
    }

    private function testWriteSafe() {
        $path = $this->testDir . '/safe-write.json';
        $data = array('test' => 'data');

        $result = JsonFile::writeSafe($path, $data);
        $this->assert($result === true, 'writeSafe() returns true on success');

        $read = JsonFile::read($path);
        $this->assert($read['test'] === 'data', 'writeSafe() writes data correctly');
    }

    private function testLockFileCreation() {
        $path = $this->testDir . '/locked.json';
        $data = array('locked' => true);

        JsonFile::write($path, $data);

        // Lock file is kept for performance (cleaned up later by cleanOrphanedLocks)
        $lockPath = $path . '.lock';
        $this->assert(file_exists($lockPath), 'Lock file exists after write (for reuse)');

        // Test that we can still read/write with existing lock file
        $read = JsonFile::read($path);
        $this->assert($read['locked'] === true, 'Can read file with existing lock file');

        JsonFile::write($path, array('locked' => false));
        $read = JsonFile::read($path);
        $this->assert($read['locked'] === false, 'Can write file with existing lock file');
    }

    private function testInvalidJson() {
        $path = $this->testDir . '/invalid.json';
        file_put_contents($path, '{invalid: json content}');

        try {
            JsonFile::read($path);
            $this->assert(false, 'read() should throw exception for invalid JSON');
        } catch (JsonFileException $e) {
            $this->assert(true, 'read() throws JsonFileException for invalid JSON');
            $this->assert(strpos($e->getMessage(), 'Invalid JSON') !== false, 'Exception message mentions invalid JSON');
        }
    }

    private function testEmptyArray() {
        $path = $this->testDir . '/empty.json';
        $data = array();

        JsonFile::write($path, $data);
        $read = JsonFile::read($path);

        $this->assert(is_array($read), 'Empty array written and read correctly');
        $this->assert(count($read) === 0, 'Empty array has zero elements');
    }

    private function testNestedData() {
        $path = $this->testDir . '/nested.json';
        $data = array(
            'level1' => array(
                'level2' => array(
                    'level3' => array(
                        'value' => 'deep'
                    )
                )
            ),
            'array' => array(1, 2, 3, 4, 5)
        );

        JsonFile::write($path, $data);
        $read = JsonFile::read($path);

        $this->assert($read['level1']['level2']['level3']['value'] === 'deep', 'Nested data preserved correctly');
        $this->assert(count($read['array']) === 5, 'Nested arrays preserved correctly');
    }

    private function testUnicodeData() {
        $path = $this->testDir . '/unicode.json';
        $data = array(
            'russian' => 'Привет мир',
            'japanese' => '日本語',
            'emoji' => '🚀 🎉 ✨',
            'chinese' => '你好世界'
        );

        JsonFile::write($path, $data);
        $read = JsonFile::read($path);

        $this->assert($read['russian'] === 'Привет мир', 'Russian text preserved');
        $this->assert($read['japanese'] === '日本語', 'Japanese text preserved');
        $this->assert($read['emoji'] === '🚀 🎉 ✨', 'Emoji preserved');
        $this->assert($read['chinese'] === '你好世界', 'Chinese text preserved');
    }

    private function testLargeData() {
        $path = $this->testDir . '/large.json';

        // Create a reasonably large dataset (but under 10MB limit)
        $data = array();
        for ($i = 0; $i < 1000; $i++) {
            $data['item_' . $i] = array(
                'id' => $i,
                'title' => 'Item ' . $i,
                'description' => str_repeat('Lorem ipsum dolor sit amet. ', 10)
            );
        }

        $result = JsonFile::write($path, $data);
        $this->assert($result === true, 'Large data written successfully');

        $read = JsonFile::read($path);
        $this->assert(count($read) === 1000, 'Large data read correctly');
        $this->assert($read['item_500']['id'] === 500, 'Large data integrity maintained');
    }

    private function testConcurrentReads() {
        $path = $this->testDir . '/concurrent.json';
        $data = array('value' => 'shared');

        JsonFile::write($path, $data);

        // Simulate multiple concurrent reads
        $results = array();
        for ($i = 0; $i < 5; $i++) {
            $results[] = JsonFile::read($path);
        }

        $this->assert(count($results) === 5, 'Multiple concurrent reads succeeded');
        foreach ($results as $result) {
            $this->assert($result['value'] === 'shared', 'Concurrent read returned correct data');
        }
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
            echo "\n✗ Some tests failed.\n";
        }
    }
}

// Run tests
$test = new JsonFileTest();
$test->run();
