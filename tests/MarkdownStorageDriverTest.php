<?php
/**
 * MarkdownStorageDriver Tests
 * Tests for Markdown file storage driver implementation
 */

require_once __DIR__ . '/../core/bootstrap.php';

class MarkdownStorageDriverTest {
    private $testDir;
    private $driver;
    private $results = array();

    public function __construct() {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-md-storage-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        $this->driver = new MarkdownStorageDriver($this->testDir);
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
        echo "Running MarkdownStorageDriver Tests...\n\n";

        $this->testGetExtension();
        $this->testWriteAndRead();
        $this->testReadNonExistent();
        $this->testExists();
        $this->testDelete();
        $this->testDeleteNonExistent();
        $this->testYamlFrontmatter();
        $this->testYamlBooleanValues();
        $this->testYamlNumericValues();
        $this->testYamlSpecialCharacters();
        $this->testMarkdownContent();
        $this->testContentWithoutFrontmatter();
        $this->testHtmlToMarkdownConversion();
        $this->testReadCollection();
        $this->testReadCollectionEmpty();
        $this->testWriteCreatesDirectory();
        $this->testOverwriteExisting();

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
        $this->assert($ext === '.md', 'getExtension() returns .md');
    }

    private function testWriteAndRead() {
        $data = array(
            'title' => 'Test Document',
            'slug' => 'test-doc',
            'status' => 'published',
            'content' => 'This is test content'
        );

        $result = $this->driver->write('pages', 'test-1', $data);
        $this->assert($result === true, 'write() returns true on success');

        $read = $this->driver->read('pages', 'test-1');
        $this->assert($read !== null, 'read() returns data for existing document');
        $this->assert($read['title'] === 'Test Document', 'read() returns correct title');
        $this->assert($read['slug'] === 'test-doc', 'read() returns correct slug');
        $this->assert(isset($read['content']), 'read() includes content field');
    }

    private function testReadNonExistent() {
        $result = $this->driver->read('pages', 'non-existent-id');
        $this->assert($result === null, 'read() returns null for non-existent document');
    }

    private function testExists() {
        $this->driver->write('pages', 'exists-test', array('title' => 'Test', 'content' => 'Content'));

        $exists = $this->driver->exists('pages', 'exists-test');
        $this->assert($exists === true, 'exists() returns true for existing document');

        $notExists = $this->driver->exists('pages', 'does-not-exist');
        $this->assert($notExists === false, 'exists() returns false for non-existent document');
    }

    private function testDelete() {
        $this->driver->write('pages', 'delete-test', array('title' => 'To Delete', 'content' => 'Content'));

        $result = $this->driver->delete('pages', 'delete-test');
        $this->assert($result === true, 'delete() returns true on success');

        $exists = $this->driver->exists('pages', 'delete-test');
        $this->assert($exists === false, 'delete() removes document from storage');
    }

    private function testDeleteNonExistent() {
        $result = $this->driver->delete('pages', 'never-existed');
        $this->assert($result === false, 'delete() returns false for non-existent document');
    }

    private function testYamlFrontmatter() {
        $data = array(
            'title' => 'YAML Test',
            'author' => 'Test Author',
            'date' => '2024-01-01',
            'content' => 'Test content'
        );

        $this->driver->write('posts', 'yaml-test', $data);

        // Check the raw file content
        $filePath = $this->testDir . '/posts/yaml-test.md';
        $rawContent = file_get_contents($filePath);

        $this->assert(strpos($rawContent, '---') === 0, 'File starts with YAML frontmatter delimiter');
        $this->assert(strpos($rawContent, 'title: YAML Test') !== false, 'YAML contains title field');
        $this->assert(strpos($rawContent, 'author: Test Author') !== false, 'YAML contains author field');
        $this->assert(strpos($rawContent, 'date: 2024-01-01') !== false, 'YAML contains date field');

        $read = $this->driver->read('posts', 'yaml-test');
        $this->assert($read['title'] === 'YAML Test', 'YAML frontmatter parsed correctly');
        $this->assert($read['author'] === 'Test Author', 'YAML author field parsed correctly');
    }

    private function testYamlBooleanValues() {
        $data = array(
            'title' => 'Boolean Test',
            'published' => true,
            'featured' => false,
            'content' => 'Content'
        );

        $this->driver->write('posts', 'bool-test', $data);

        $read = $this->driver->read('posts', 'bool-test');
        $this->assert($read['published'] === true, 'Boolean true value preserved');
        $this->assert($read['featured'] === false, 'Boolean false value preserved');
    }

    private function testYamlNumericValues() {
        $data = array(
            'title' => 'Numeric Test',
            'order' => 42,
            'rating' => 5,
            'content' => 'Content'
        );

        $this->driver->write('posts', 'numeric-test', $data);

        $read = $this->driver->read('posts', 'numeric-test');
        $this->assert($read['order'] === 42, 'Integer value preserved');
        $this->assert($read['rating'] === 5, 'Numeric value preserved');
        $this->assert(is_int($read['order']), 'Numeric value is parsed as integer');
    }

    private function testYamlSpecialCharacters() {
        $data = array(
            'title' => 'Special: Characters & Symbols',
            'description' => 'Text with "quotes" and colons:',
            'content' => 'Content'
        );

        $this->driver->write('posts', 'special-test', $data);

        $read = $this->driver->read('posts', 'special-test');
        $this->assert($read['title'] === 'Special: Characters & Symbols', 'Special characters in title preserved');
        $this->assert($read['description'] === 'Text with "quotes" and colons:', 'Quotes and colons preserved');
    }

    private function testMarkdownContent() {
        $data = array(
            'title' => 'Markdown Test',
            'content' => '# Heading

This is a paragraph with **bold** and *italic* text.

- List item 1
- List item 2'
        );

        $this->driver->write('posts', 'markdown-test', $data);

        $read = $this->driver->read('posts', 'markdown-test');
        $this->assert(isset($read['content']), 'Content field exists');
        $this->assert(isset($read['_markdown']), 'Original markdown preserved in _markdown field');

        // Content should be converted to HTML
        $this->assert(strpos($read['content'], '<h1>') !== false || strpos($read['content'], '<p>') !== false, 'Markdown converted to HTML');
    }

    private function testContentWithoutFrontmatter() {
        // Manually create a markdown file without frontmatter
        $filePath = $this->testDir . '/posts/no-frontmatter.md';
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $markdownContent = '# Simple Markdown

This is content without YAML frontmatter.';

        file_put_contents($filePath, $markdownContent);

        $read = $this->driver->read('posts', 'no-frontmatter');
        $this->assert($read !== null, 'read() handles files without frontmatter');
        $this->assert(isset($read['content']), 'Content field exists for files without frontmatter');
        $this->assert(isset($read['_markdown']), 'Original markdown preserved');
    }

    private function testHtmlToMarkdownConversion() {
        $data = array(
            'title' => 'HTML Test',
            'content' => '<h1>HTML Heading</h1><p>This is <strong>HTML</strong> content.</p>'
        );

        $this->driver->write('posts', 'html-test', $data);

        // Check the raw file to see if HTML was converted to Markdown
        $filePath = $this->testDir . '/posts/html-test.md';
        $rawContent = file_get_contents($filePath);

        $this->assert(strpos($rawContent, '---') === 0, 'File has YAML frontmatter');

        // The content section should be markdown, not HTML
        $contentSection = substr($rawContent, strpos($rawContent, "---\n\n") + 5);
        $this->assert(strlen($contentSection) > 0, 'Content section exists');
    }

    private function testReadCollection() {
        // Write multiple documents
        $this->driver->write('articles', 'article-1', array('title' => 'Article 1', 'content' => 'Content 1'));
        $this->driver->write('articles', 'article-2', array('title' => 'Article 2', 'content' => 'Content 2'));
        $this->driver->write('articles', 'article-3', array('title' => 'Article 3', 'content' => 'Content 3'));

        $collection = $this->driver->readCollection('articles');
        $this->assert(is_array($collection), 'readCollection() returns array');
        $this->assert(count($collection) === 3, 'readCollection() returns all documents');
        $this->assert(isset($collection['article-1']), 'readCollection() includes first document');
        $this->assert(isset($collection['article-2']), 'readCollection() includes second document');
        $this->assert(isset($collection['article-3']), 'readCollection() includes third document');
        $this->assert($collection['article-1']['title'] === 'Article 1', 'readCollection() preserves document data');
    }

    private function testReadCollectionEmpty() {
        $collection = $this->driver->readCollection('non-existent-collection');
        $this->assert(is_array($collection), 'readCollection() returns array for non-existent collection');
        $this->assert(count($collection) === 0, 'readCollection() returns empty array for non-existent collection');
    }

    private function testWriteCreatesDirectory() {
        $newCollection = 'new-collection-' . time();
        $collectionPath = $this->testDir . '/' . $newCollection;

        $this->assert(!is_dir($collectionPath), 'Collection directory does not exist before write');

        $this->driver->write($newCollection, 'first-doc', array('title' => 'First', 'content' => 'Content'));

        $this->assert(is_dir($collectionPath), 'write() creates collection directory');
        $this->assert($this->driver->exists($newCollection, 'first-doc'), 'Document exists after write');
    }

    private function testOverwriteExisting() {
        $this->driver->write('pages', 'overwrite-test', array('title' => 'Original', 'version' => 1, 'content' => 'Original content'));

        $original = $this->driver->read('pages', 'overwrite-test');
        $this->assert($original['title'] === 'Original', 'Original data written correctly');

        $this->driver->write('pages', 'overwrite-test', array('title' => 'Updated', 'version' => 2, 'content' => 'Updated content'));

        $updated = $this->driver->read('pages', 'overwrite-test');
        $this->assert($updated['title'] === 'Updated', 'write() overwrites existing document');
        $this->assert($updated['version'] === 2, 'write() replaces all data');
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
$test = new MarkdownStorageDriverTest();
$test->run();
