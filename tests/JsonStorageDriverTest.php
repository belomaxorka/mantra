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
        $this->testReadCollectionIgnoresNonJsonFiles();
        $this->testWriteCreatesDirectory();
        $this->testOverwriteExisting();
        $this->testEmptyDocument();
        $this->testDataTypePreservation();
        $this->testWriteUtf8Data();
        $this->testRepeatedWrites();

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
        $col = 'mixed-corrupt-' . time();
        $this->driver->write($col, 'valid-1', array('title' => 'Valid 1'));
        $this->driver->write($col, 'valid-2', array('title' => 'Valid 2'));

        // Create corrupted JSON file manually
        $corruptedPath = $this->testDir . '/' . $col . '/corrupted.json';
        file_put_contents($corruptedPath, '{invalid json content');

        $collection = $this->driver->readCollection($col);
        $this->assert(is_array($collection), 'readCollection() returns array despite corrupted file');
        $this->assert(count($collection) === 2, 'readCollection() skips corrupted files');
        $this->assert(isset($collection['valid-1']), 'readCollection() includes valid documents');
        $this->assert(!isset($collection['corrupted']), 'readCollection() excludes corrupted documents');
    }

    private function testReadCollectionIgnoresNonJsonFiles() {
        $col = 'mixed-files-' . time();
        $this->driver->write($col, 'doc-1', array('title' => 'Doc 1'));

        // Create non-json files that should be ignored
        $colPath = $this->testDir . '/' . $col;
        file_put_contents($colPath . '/notes.txt', 'plain text');
        file_put_contents($colPath . '/doc-1.json.lock', '');
        file_put_contents($colPath . '/doc-1.json.tmp.abc123', '{}');

        $collection = $this->driver->readCollection($col);
        $this->assert(count($collection) === 1, 'readCollection() ignores non-.json files');
        $this->assert(isset($collection['doc-1']), 'readCollection() returns only .json documents');
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
        $col = 'overwrite-' . time();
        $this->driver->write($col, 'ow-test', array('title' => 'Original', 'version' => 1, 'old_field' => 'gone'));

        $original = $this->driver->read($col, 'ow-test');
        $this->assert($original['title'] === 'Original', 'Original data written correctly');
        $this->assert(isset($original['old_field']), 'Original has old_field');

        // Overwrite without old_field
        $this->driver->write($col, 'ow-test', array('title' => 'Updated', 'version' => 2));

        $updated = $this->driver->read($col, 'ow-test');
        $this->assert($updated['title'] === 'Updated', 'write() overwrites existing document');
        $this->assert($updated['version'] === 2, 'write() replaces version');
        $this->assert(!isset($updated['old_field']), 'Old-only fields are removed after overwrite');
    }

    private function testEmptyDocument() {
        $col = 'empty-doc-' . time();
        $this->driver->write($col, 'empty', array());

        $read = $this->driver->read($col, 'empty');
        $this->assert(is_array($read), 'Empty document read returns array');
        $this->assert(count($read) === 0, 'Empty document has zero fields');
    }

    private function testDataTypePreservation() {
        $col = 'types-' . time();
        $data = array(
            'string' => 'hello',
            'integer' => 42,
            'float' => 3.14,
            'bool_true' => true,
            'bool_false' => false,
            'null_val' => null,
            'array_list' => array(1, 2, 3),
            'nested' => array('a' => array('b' => 'c')),
        );

        $this->driver->write($col, 'types', $data);
        $read = $this->driver->read($col, 'types');

        $this->assert($read['string'] === 'hello', 'String type preserved');
        $this->assert($read['integer'] === 42, 'Integer type preserved');
        $this->assert(abs($read['float'] - 3.14) < 0.001, 'Float type preserved');
        $this->assert($read['bool_true'] === true, 'Boolean true preserved');
        $this->assert($read['bool_false'] === false, 'Boolean false preserved');
        $this->assert($read['null_val'] === null, 'Null value preserved');
        $this->assert($read['array_list'] === array(1, 2, 3), 'Array list preserved');
        $this->assert($read['nested']['a']['b'] === 'c', 'Nested structure preserved');
    }

    private function testWriteUtf8Data() {
        $col = 'utf8-' . time();
        $data = array('title' => 'Тест', 'content' => '日本語', 'special' => 'Test "quotes" & \'apostrophes\'');

        $result = $this->driver->write($col, 'utf8', $data);
        $this->assert($result === true, 'write() handles UTF-8 data');

        $read = $this->driver->read($col, 'utf8');
        $this->assert($read['title'] === 'Тест', 'Cyrillic data preserved');
        $this->assert($read['content'] === '日本語', 'CJK data preserved');
        $this->assert($read['special'] === 'Test "quotes" & \'apostrophes\'', 'Special characters preserved');
    }

    private function testRepeatedWrites() {
        $col = 'repeated-w-' . time();
        $id = 'rw-test';
        $iterations = 5;

        for ($i = 1; $i <= $iterations; $i++) {
            $this->driver->write($col, $id, array('iteration' => $i));
        }

        $final = $this->driver->read($col, $id);
        $this->assert($final !== null, 'Document exists after repeated writes');
        $this->assert($final['iteration'] === $iterations, 'Last write wins after repeated writes');
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
