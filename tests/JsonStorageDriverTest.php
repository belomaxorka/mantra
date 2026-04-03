<?php
/**
 * JsonStorageDriver Tests (PHPUnit 10.x)
 * Tests for JSON file storage driver implementation
 */

use Storage\JsonStorageDriver;

class JsonStorageDriverTest extends MantraTestCase
{
    private $testDir;
    private $driver;

    protected function setUp(): void
    {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-json-storage-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        $this->driver = new JsonStorageDriver($this->testDir);
    }

    protected function tearDown(): void
    {
        // Cleanup test directory
        $this->removeDirectory($this->testDir);
    }

    public function testGetExtension(): void
    {
        $ext = $this->driver->getExtension();
        $this->assertSame('.json', $ext, 'getExtension() returns .json');
    }

    public function testWriteAndRead(): void
    {
        $data = array(
            'id' => 'test-1',
            'title' => 'Test Document',
            'content' => 'Test content',
            'created' => time()
        );

        $result = $this->driver->write('pages', 'test-1', $data);
        $this->assertTrue($result, 'write() returns true on success');

        $read = $this->driver->read('pages', 'test-1');
        $this->assertNotNull($read, 'read() returns data for existing document');
        $this->assertSame('Test Document', $read['title'], 'read() returns correct data');
        $this->assertSame('Test content', $read['content'], 'read() preserves all fields');
    }

    public function testReadNonExistent(): void
    {
        $result = $this->driver->read('pages', 'non-existent-id');
        $this->assertNull($result, 'read() returns null for non-existent document');
    }

    public function testExists(): void
    {
        $this->driver->write('pages', 'exists-test', array('title' => 'Test'));

        $exists = $this->driver->exists('pages', 'exists-test');
        $this->assertTrue($exists, 'exists() returns true for existing document');

        $notExists = $this->driver->exists('pages', 'does-not-exist');
        $this->assertFalse($notExists, 'exists() returns false for non-existent document');
    }

    public function testDelete(): void
    {
        $this->driver->write('pages', 'delete-test', array('title' => 'To Delete'));

        $result = $this->driver->delete('pages', 'delete-test');
        $this->assertTrue($result, 'delete() returns true on success');

        $exists = $this->driver->exists('pages', 'delete-test');
        $this->assertFalse($exists, 'delete() removes document from storage');
    }

    public function testDeleteNonExistent(): void
    {
        $result = $this->driver->delete('pages', 'never-existed');
        $this->assertFalse($result, 'delete() returns false for non-existent document');
    }

    public function testReadCollection(): void
    {
        // Write multiple documents
        $this->driver->write('posts', 'post-1', array('title' => 'Post 1', 'order' => 1));
        $this->driver->write('posts', 'post-2', array('title' => 'Post 2', 'order' => 2));
        $this->driver->write('posts', 'post-3', array('title' => 'Post 3', 'order' => 3));

        $collection = $this->driver->readCollection('posts');
        $this->assertIsArray($collection, 'readCollection() returns array');
        $this->assertCount(3, $collection, 'readCollection() returns all documents');
        $this->assertArrayHasKey('post-1', $collection, 'readCollection() includes first document');
        $this->assertArrayHasKey('post-2', $collection, 'readCollection() includes second document');
        $this->assertArrayHasKey('post-3', $collection, 'readCollection() includes third document');
        $this->assertSame('Post 1', $collection['post-1']['title'], 'readCollection() preserves document data');
    }

    public function testReadCollectionEmpty(): void
    {
        $collection = $this->driver->readCollection('non-existent-collection');
        $this->assertIsArray($collection, 'readCollection() returns array for non-existent collection');
        $this->assertCount(0, $collection, 'readCollection() returns empty array for non-existent collection');
    }

    public function testReadCollectionWithCorruptedFile(): void
    {
        $col = 'mixed-corrupt-' . time();
        $this->driver->write($col, 'valid-1', array('title' => 'Valid 1'));
        $this->driver->write($col, 'valid-2', array('title' => 'Valid 2'));

        // Create corrupted JSON file manually
        $corruptedPath = $this->testDir . '/' . $col . '/corrupted.json';
        file_put_contents($corruptedPath, '{invalid json content');

        $collection = $this->driver->readCollection($col);
        $this->assertIsArray($collection, 'readCollection() returns array despite corrupted file');
        $this->assertCount(2, $collection, 'readCollection() skips corrupted files');
        $this->assertArrayHasKey('valid-1', $collection, 'readCollection() includes valid documents');
        $this->assertArrayNotHasKey('corrupted', $collection, 'readCollection() excludes corrupted documents');
    }

    public function testReadCollectionIgnoresNonJsonFiles(): void
    {
        $col = 'mixed-files-' . time();
        $this->driver->write($col, 'doc-1', array('title' => 'Doc 1'));

        // Create non-json files that should be ignored
        $colPath = $this->testDir . '/' . $col;
        file_put_contents($colPath . '/notes.txt', 'plain text');
        file_put_contents($colPath . '/doc-1.json.lock', '');
        file_put_contents($colPath . '/doc-1.json.tmp.abc123', '{}');

        $collection = $this->driver->readCollection($col);
        $this->assertCount(1, $collection, 'readCollection() ignores non-.json files');
        $this->assertArrayHasKey('doc-1', $collection, 'readCollection() returns only .json documents');
    }

    public function testWriteCreatesDirectory(): void
    {
        $newCollection = 'new-collection-' . time();
        $collectionPath = $this->testDir . '/' . $newCollection;

        $this->assertDirectoryDoesNotExist($collectionPath, 'Collection directory does not exist before write');

        $this->driver->write($newCollection, 'first-doc', array('title' => 'First'));

        $this->assertDirectoryExists($collectionPath, 'write() creates collection directory');
        $this->assertTrue($this->driver->exists($newCollection, 'first-doc'), 'Document exists after write');
    }

    public function testOverwriteExisting(): void
    {
        $col = 'overwrite-' . time();
        $this->driver->write($col, 'ow-test', array('title' => 'Original', 'version' => 1, 'old_field' => 'gone'));

        $original = $this->driver->read($col, 'ow-test');
        $this->assertSame('Original', $original['title'], 'Original data written correctly');
        $this->assertArrayHasKey('old_field', $original, 'Original has old_field');

        // Overwrite without old_field
        $this->driver->write($col, 'ow-test', array('title' => 'Updated', 'version' => 2));

        $updated = $this->driver->read($col, 'ow-test');
        $this->assertSame('Updated', $updated['title'], 'write() overwrites existing document');
        $this->assertSame(2, $updated['version'], 'write() replaces version');
        $this->assertArrayNotHasKey('old_field', $updated, 'Old-only fields are removed after overwrite');
    }

    public function testEmptyDocument(): void
    {
        $col = 'empty-doc-' . time();
        $this->driver->write($col, 'empty', array());

        $read = $this->driver->read($col, 'empty');
        $this->assertIsArray($read, 'Empty document read returns array');
        $this->assertCount(0, $read, 'Empty document has zero fields');
    }

    public function testDataTypePreservation(): void
    {
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

        $this->assertSame('hello', $read['string'], 'String type preserved');
        $this->assertSame(42, $read['integer'], 'Integer type preserved');
        $this->assertTrue(abs($read['float'] - 3.14) < 0.001, 'Float type preserved');
        $this->assertTrue($read['bool_true'], 'Boolean true preserved');
        $this->assertFalse($read['bool_false'], 'Boolean false preserved');
        $this->assertNull($read['null_val'], 'Null value preserved');
        $this->assertSame(array(1, 2, 3), $read['array_list'], 'Array list preserved');
        $this->assertSame('c', $read['nested']['a']['b'], 'Nested structure preserved');
    }

    public function testWriteUtf8Data(): void
    {
        $col = 'utf8-' . time();
        $data = array('title' => 'Тест', 'content' => '日本語', 'special' => 'Test "quotes" & \'apostrophes\'');

        $result = $this->driver->write($col, 'utf8', $data);
        $this->assertTrue($result, 'write() handles UTF-8 data');

        $read = $this->driver->read($col, 'utf8');
        $this->assertSame('Тест', $read['title'], 'Cyrillic data preserved');
        $this->assertSame('日本語', $read['content'], 'CJK data preserved');
        $this->assertSame('Test "quotes" & \'apostrophes\'', $read['special'], 'Special characters preserved');
    }

    public function testRepeatedWrites(): void
    {
        $col = 'repeated-w-' . time();
        $id = 'rw-test';
        $iterations = 5;

        for ($i = 1; $i <= $iterations; $i++) {
            $this->driver->write($col, $id, array('iteration' => $i));
        }

        $final = $this->driver->read($col, $id);
        $this->assertNotNull($final, 'Document exists after repeated writes');
        $this->assertSame($iterations, $final['iteration'], 'Last write wins after repeated writes');
    }
}
