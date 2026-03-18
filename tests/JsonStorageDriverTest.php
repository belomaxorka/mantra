<?php
/**
 * JsonStorageDriver Tests
 * Tests for JSON file storage driver implementation
 */

require_once __DIR__ . '/../core/bootstrap.php';

class JsonStorageDriverTest {
    private $testDir;
    private $driver;
    private $results = array();

    public function __construct() {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-json-storage-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        $this->driver = new JsonStorageDriver($this->testDir);
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
        echo "Running JsonStorageDriver Tests...\n\n";

        $this->testGetExtension();
        $this->testWriteAndRead();
        $this->testReadNonExistent();
        $this->testExists();
        $this->testDelete();
        $this->testDeleteNonExistent();
        $this->testReadCollection();
        $this->testReadCollectionEmpty();
        $this->testReadCollectionWithCorruptedFile();
        $this->testWriteCreatesDirectory();
        $this->testOverwriteExisting();
        $this->testWriteInvalidData();
        $this->testConcurrentWrites();

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

    private function testGetExtension() {
        $ext = $this->driver->getExtension();
        $this->assert($ext === '.json', 'getExtension() returns .json');
    }

    private function testWriteAndRead() {
        $data = array(
            'id' => 'test-1',
            'title' => 'Test Document',
            'content' => 'Test content',
            'created' => time()
        );

        $result = $this->driver->write('pages', 'test-1', $data);
        $this->assert($result === true, 'write() returns true on success');

        $read = $this->driver->read('pages', 'test-1');
        $this->assert($read !== null, 'read() returns data for existing document');
        $this->assert($read['title'] === 'Test Document', 'read() returns correct data');
        $this->assert($read['content'] === 'Test content', 'read() preserves all fields');
    }

    private function testReadNonExistent() {
        $result = $this->driver->read('pages', 'non-existent-id');
        $this->assert($result === null, 'read() returns null for non-existent document');
    }

    private function testExists() {
        $this->driver->write('pages', 'exists-test', array('title' => 'Test'));

        $exists = $this->driver->exists('pages', 'exists-test');
        $this->assert($exists === true, 'exists() returns true for existing document');

        $notExists = $this->driver->exists('pages', 'does-not-exist');
        $this->assert($notExists === false, 'exists() returns false for non-existent document');
    }

    private function testDelete() {
        $this->driver->write('pages', 'delete-test', array('title' => 'To Delete'));

        $result = $this->driver->delete('pages', 'delete-test');
        $this->assert($result === true, 'delete() returns true on success');

        $exists = $this->driver->exists('pages', 'delete-test');
        $this->assert($exists === false, 'delete() removes document from storage');
    }

    private function testDeleteNonExistent() {
        $result = $this->driver->delete('pages', 'never-existed');
        $this->assert($result === false, 'delete() returns false for non-existent document');
    }

    private function testReadCollection() {
        // Write multiple documents
        $this->driver->write('posts', 'post-1', array('title' => 'Post 1', 'order' => 1));
        $this->driver->write('posts', 'post-2', array('title' => 'Post 2', 'order' => 2));
        $this->driver->write('posts', 'post-3', array('title' => 'Post 3', 'order' => 3));

        $collection = $this->driver->readCollection('posts');
        $this->assert(is_array($collection), 'readCollection() returns array');
        $this->assert(count($collection) === 3, 'readCollection() returns all documents');
        $this->assert(isset($collection['post-1']), 'readCollection() includes first document');
        $this->assert(isset($collection['post-2']), 'readCollection() includes second document');
        $this->assert(isset($collection['post-3']), 'readCollection() includes third document');
        $this->assert($collection['post-1']['title'] === 'Post 1', 'readCollection() preserves document data');
    }

    private function testReadCollectionEmpty() {
        $collection = $this->driver->readCollection('non-existent-collection');
        $this->assert(is_array($collection), 'readCollection() returns array for non-existent collection');
        $this->assert(count($collection) === 0, 'readCollection() returns empty array for non-existent collection');
    }

    private function testReadCollectionWithCorruptedFile() {
        // Write valid documents
        $this->driver->write('mixed', 'valid-1', array('title' => 'Valid 1'));
        $this->driver->write('mixed', 'valid-2', array('title' => 'Valid 2'));

        // Create corrupted JSON file manually
        $corruptedPath = $this->testDir . '/mixed/corrupted.json';
        file_put_contents($corruptedPath, '{invalid json content');

        $collection = $this->driver->readCollection('mixed');
        $this->assert(is_array($collection), 'readCollection() returns array despite corrupted file');
        $this->assert(count($collection) === 2, 'readCollection() skips corrupted files');
        $this->assert(isset($collection['valid-1']), 'readCollection() includes valid documents');
        $this->assert(!isset($collection['corrupted']), 'readCollection() excludes corrupted documents');
    }

    private function testWriteCreatesDirectory() {
        $newCollection = 'new-collection-' . time();
        $collectionPath = $this->testDir . '/' . $newCollection;

        $this->assert(!is_dir($collectionPath), 'Collection directory does not exist before write');

        $this->driver->write($newCollection, 'first-doc', array('title' => 'First'));

        $this->assert(is_dir($collectionPath), 'write() creates collection directory');
        $this->assert($this->driver->exists($newCollection, 'first-doc'), 'Document exists after write');
    }

    private function testOverwriteExisting() {
        $this->driver->write('pages', 'overwrite-test', array('title' => 'Original', 'version' => 1));

        $original = $this->driver->read('pages', 'overwrite-test');
        $this->assert($original['title'] === 'Original', 'Original data written correctly');

        $this->driver->write('pages', 'overwrite-test', array('title' => 'Updated', 'version' => 2));

        $updated = $this->driver->read('pages', 'overwrite-test');
        $this->assert($updated['title'] === 'Updated', 'write() overwrites existing document');
        $this->assert($updated['version'] === 2, 'write() replaces all data');
        $this->assert(!isset($updated['version']) || $updated['version'] !== 1, 'Old data is replaced');
    }

    private function testWriteInvalidData() {
        // Test with UTF-8 data (should work)
        $utf8Data = array('title' => 'Тест', 'content' => '日本語');
        $result = $this->driver->write('pages', 'utf8-test', $utf8Data);
        $this->assert($result === true, 'write() handles UTF-8 data correctly');

        $read = $this->driver->read('pages', 'utf8-test');
        $this->assert($read['title'] === 'Тест', 'UTF-8 data preserved correctly');

        // Test with special characters
        $specialData = array('title' => 'Test "quotes" and \'apostrophes\'', 'path' => '/path/to/file');
        $result = $this->driver->write('pages', 'special-test', $specialData);
        $this->assert($result === true, 'write() handles special characters');
    }

    private function testConcurrentWrites() {
        // Simulate concurrent writes by writing to the same document multiple times
        $id = 'concurrent-test';
        $iterations = 5;

        for ($i = 1; $i <= $iterations; $i++) {
            $result = $this->driver->write('pages', $id, array('iteration' => $i, 'timestamp' => microtime(true)));
            $this->assert($result === true, "Concurrent write iteration $i succeeded");
        }

        $final = $this->driver->read('pages', $id);
        $this->assert($final !== null, 'Document exists after concurrent writes');
        $this->assert($final['iteration'] === $iterations, 'Last write wins in concurrent scenario');
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
$test = new JsonStorageDriverTest();
$test->run();
