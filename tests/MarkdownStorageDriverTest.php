<?php declare(strict_types=1);

/**
 * MarkdownStorageDriver Tests (PHPUnit 10.x)
 * Tests for Markdown file storage driver implementation
 */

use Storage\MarkdownStorageDriver;

class MarkdownStorageDriverTest extends MantraTestCase
{
    private $testDir;
    private $driver;

    protected function setUp(): void
    {
        // Use temporary test directory
        $this->testDir = MANTRA_STORAGE . '/test-md-storage-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0o755, true);
        }
        $this->driver = new MarkdownStorageDriver($this->testDir);
    }

    protected function tearDown(): void
    {
        // Cleanup test directory
        $this->removeDirectory($this->testDir);
    }

    public function testGetExtension(): void
    {
        $ext = $this->driver->getExtension();
        $this->assertSame('.md', $ext, 'getExtension() returns .md');
    }

    public function testWriteAndRead(): void
    {
        $data = [
            'title' => 'Test Document',
            'slug' => 'test-doc',
            'status' => 'published',
            'content' => 'This is test content',
        ];

        $result = $this->driver->write('pages', 'test-1', $data);
        $this->assertTrue($result, 'write() returns true on success');

        $read = $this->driver->read('pages', 'test-1');
        $this->assertNotNull($read, 'read() returns data for existing document');
        $this->assertSame('Test Document', $read['title'], 'read() returns correct title');
        $this->assertSame('test-doc', $read['slug'], 'read() returns correct slug');
        $this->assertStringContainsString('This is test content', $read['content'], 'read() returns correct content text');
    }

    public function testReadNonExistent(): void
    {
        $result = $this->driver->read('pages', 'non-existent-id');
        $this->assertNull($result, 'read() returns null for non-existent document');
    }

    public function testExists(): void
    {
        $this->driver->write('pages', 'exists-test', ['title' => 'Test', 'content' => 'Content']);

        $exists = $this->driver->exists('pages', 'exists-test');
        $this->assertTrue($exists, 'exists() returns true for existing document');

        $notExists = $this->driver->exists('pages', 'does-not-exist');
        $this->assertFalse($notExists, 'exists() returns false for non-existent document');
    }

    public function testDelete(): void
    {
        $this->driver->write('pages', 'delete-test', ['title' => 'To Delete', 'content' => 'Content']);

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

    public function testYamlFrontmatter(): void
    {
        $data = [
            'title' => 'YAML Test',
            'author' => 'Test Author',
            'date' => '2024-01-01',
            'content' => 'Test content',
        ];

        $this->driver->write('posts', 'yaml-test', $data);

        // Check the raw file content
        $filePath = $this->testDir . '/posts/yaml-test.md';
        $rawContent = file_get_contents($filePath);

        $this->assertSame(0, strpos($rawContent, '---'), 'File starts with YAML frontmatter delimiter');
        $this->assertStringContainsString('title: YAML Test', $rawContent, 'YAML contains title field');
        $this->assertStringContainsString('author: Test Author', $rawContent, 'YAML contains author field');
        $this->assertStringContainsString('date: 2024-01-01', $rawContent, 'YAML contains date field');

        $read = $this->driver->read('posts', 'yaml-test');
        $this->assertSame('YAML Test', $read['title'], 'YAML frontmatter parsed correctly');
        $this->assertSame('Test Author', $read['author'], 'YAML author field parsed correctly');
    }

    public function testYamlBooleanValues(): void
    {
        $data = [
            'title' => 'Boolean Test',
            'published' => true,
            'featured' => false,
            'content' => 'Content',
        ];

        $this->driver->write('posts', 'bool-test', $data);

        $read = $this->driver->read('posts', 'bool-test');
        $this->assertTrue($read['published'], 'Boolean true value preserved');
        $this->assertFalse($read['featured'], 'Boolean false value preserved');
    }

    public function testYamlNumericValues(): void
    {
        $data = [
            'title' => 'Numeric Test',
            'order' => 42,
            'rating' => 5,
            'content' => 'Content',
        ];

        $this->driver->write('posts', 'numeric-test', $data);

        $read = $this->driver->read('posts', 'numeric-test');
        $this->assertSame(42, $read['order'], 'Integer value preserved');
        $this->assertSame(5, $read['rating'], 'Numeric value preserved');
        $this->assertIsInt($read['order'], 'Numeric value is parsed as integer');
    }

    public function testYamlSpecialCharacters(): void
    {
        $data = [
            'title' => 'Special: Characters & Symbols',
            'description' => 'Text with "quotes" and colons:',
            'content' => 'Content',
        ];

        $this->driver->write('posts', 'special-test', $data);

        $read = $this->driver->read('posts', 'special-test');
        $this->assertSame('Special: Characters & Symbols', $read['title'], 'Special characters in title preserved');
        $this->assertSame('Text with "quotes" and colons:', $read['description'], 'Quotes and colons preserved');
    }

    public function testYamlUtf8Values(): void
    {
        $col = 'utf8-yaml-' . time();
        $data = [
            'title' => 'Привет мир',
            'author' => '日本語テスト',
            'content' => 'Body text',
        ];

        $this->driver->write($col, 'utf8-yaml', $data);
        $read = $this->driver->read($col, 'utf8-yaml');

        $this->assertSame('Привет мир', $read['title'], 'Cyrillic value preserved in YAML');
        $this->assertSame('日本語テスト', $read['author'], 'CJK value preserved in YAML');
    }

    public function testMarkdownContent(): void
    {
        $col = 'md-content-' . time();
        $markdownBody = "# Heading\n\nThis is a paragraph with **bold** and *italic* text.\n\n- List item 1\n- List item 2";
        $data = [
            'title' => 'Markdown Test',
            'content' => $markdownBody,
        ];

        $this->driver->write($col, 'md-test', $data);

        $read = $this->driver->read($col, 'md-test');
        $this->assertArrayHasKey('content', $read, 'Content field exists');
        $this->assertArrayHasKey('_markdown', $read, 'Original markdown preserved in _markdown field');

        // Content should be converted to HTML
        $this->assertTrue(
            str_contains($read['content'], '<h1>') || str_contains($read['content'], '<p>'),
            'Markdown converted to HTML',
        );
        $this->assertStringContainsString('bold', $read['content'], 'HTML content contains original text');
    }

    public function testMarkdownFieldRoundtrip(): void
    {
        $col = 'md-roundtrip-' . time();
        $markdown = 'Simple paragraph with **bold** text.';
        $data = [
            'title' => 'Roundtrip',
            'content' => $markdown,
        ];

        $this->driver->write($col, 'rt-test', $data);
        $read = $this->driver->read($col, 'rt-test');

        $this->assertStringContainsString(
            'bold',
            $read['_markdown'],
            '_markdown field contains original text',
        );
        $this->assertStringNotContainsString(
            '<',
            $read['_markdown'],
            '_markdown field does not contain HTML tags',
        );
    }

    public function testEmptyContent(): void
    {
        $col = 'empty-content-' . time();
        $data = ['title' => 'No Body', 'content' => ''];

        $this->driver->write($col, 'empty-c', $data);
        $read = $this->driver->read($col, 'empty-c');

        $this->assertSame('No Body', $read['title'], 'Title preserved with empty content');
        $this->assertArrayHasKey('content', $read, 'Content field exists even when empty');
    }

    public function testContentWithoutFrontmatter(): void
    {
        // Manually create a markdown file without frontmatter
        $filePath = $this->testDir . '/posts/no-frontmatter.md';
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $markdownContent = '# Simple Markdown

This is content without YAML frontmatter.';

        file_put_contents($filePath, $markdownContent);

        $read = $this->driver->read('posts', 'no-frontmatter');
        $this->assertNotNull($read, 'read() handles files without frontmatter');
        $this->assertArrayHasKey('content', $read, 'Content field exists for files without frontmatter');
        $this->assertArrayHasKey('_markdown', $read, 'Original markdown preserved');
    }

    public function testContentWithHorizontalRule(): void
    {
        // Markdown horizontal rules use --- which conflicts with YAML frontmatter delimiter
        $col = 'hr-test-' . time();
        $data = [
            'title' => 'HR Test',
            'content' => "First section\n\n---\n\nSecond section",
        ];

        $this->driver->write($col, 'hr-doc', $data);
        $read = $this->driver->read($col, 'hr-doc');

        $this->assertSame('HR Test', $read['title'], 'Title preserved when content has ---');
        $this->assertTrue(
            str_contains($read['_markdown'], 'First section') && str_contains($read['_markdown'], 'Second section'),
            'Both sections preserved when content contains ---',
        );
    }

    public function testHtmlToMarkdownConversion(): void
    {
        $col = 'html-conv-' . time();
        $data = [
            'title' => 'HTML Test',
            'content' => '<h1>HTML Heading</h1><p>This is <strong>HTML</strong> content.</p>',
        ];

        $this->driver->write($col, 'html-test', $data);

        // Check the raw file to see if HTML was converted to Markdown
        $filePath = $this->testDir . '/' . $col . '/html-test.md';
        $rawContent = file_get_contents($filePath);

        $this->assertSame(0, strpos($rawContent, '---'), 'File has YAML frontmatter');

        // Extract content section (after second ---)
        $parts = explode("---\n", $rawContent, 3);
        $contentSection = isset($parts[2]) ? trim($parts[2]) : '';

        $this->assertNotEmpty($contentSection, 'Content section exists');
        $this->assertStringNotContainsString('<h1>', $contentSection, 'HTML h1 tag converted to markdown');
        $this->assertStringNotContainsString('<p>', $contentSection, 'HTML p tag converted to markdown');
        $this->assertStringContainsString('HTML Heading', $contentSection, 'Content text preserved after conversion');
    }

    public function testReadCollection(): void
    {
        // Write multiple documents
        $this->driver->write('articles', 'article-1', ['title' => 'Article 1', 'content' => 'Content 1']);
        $this->driver->write('articles', 'article-2', ['title' => 'Article 2', 'content' => 'Content 2']);
        $this->driver->write('articles', 'article-3', ['title' => 'Article 3', 'content' => 'Content 3']);

        $collection = $this->driver->readCollection('articles');
        $this->assertIsArray($collection, 'readCollection() returns array');
        $this->assertCount(3, $collection, 'readCollection() returns all documents');
        $this->assertArrayHasKey('article-1', $collection, 'readCollection() includes first document');
        $this->assertArrayHasKey('article-2', $collection, 'readCollection() includes second document');
        $this->assertArrayHasKey('article-3', $collection, 'readCollection() includes third document');
        $this->assertSame('Article 1', $collection['article-1']['title'], 'readCollection() preserves document data');
    }

    public function testReadCollectionEmpty(): void
    {
        $collection = $this->driver->readCollection('non-existent-collection');
        $this->assertIsArray($collection, 'readCollection() returns array for non-existent collection');
        $this->assertCount(0, $collection, 'readCollection() returns empty array for non-existent collection');
    }

    public function testReadCollectionWithCorruptedFile(): void
    {
        $col = 'mixed-corrupt-md-' . time();
        $this->driver->write($col, 'valid-1', ['title' => 'Valid 1', 'content' => 'Content 1']);
        $this->driver->write($col, 'valid-2', ['title' => 'Valid 2', 'content' => 'Content 2']);

        // Create a file with invalid content that will cause parseMarkdown to fail
        // Write a binary file that might cause issues
        $corruptedPath = $this->testDir . '/' . $col . '/corrupted.md';
        file_put_contents($corruptedPath, "\x00\x01\x02\x03");

        $collection = $this->driver->readCollection($col);
        $this->assertIsArray($collection, 'readCollection() returns array despite corrupted file');
        // The corrupted file may or may not parse (binary is still "content"), so check valid docs are present
        $this->assertArrayHasKey('valid-1', $collection, 'readCollection() includes valid documents');
        $this->assertArrayHasKey('valid-2', $collection, 'readCollection() includes second valid document');
    }

    public function testWriteCreatesDirectory(): void
    {
        $newCollection = 'new-collection-' . time();
        $collectionPath = $this->testDir . '/' . $newCollection;

        $this->assertDirectoryDoesNotExist($collectionPath, 'Collection directory does not exist before write');

        $this->driver->write($newCollection, 'first-doc', ['title' => 'First', 'content' => 'Content']);

        $this->assertDirectoryExists($collectionPath, 'write() creates collection directory');
        $this->assertTrue($this->driver->exists($newCollection, 'first-doc'), 'Document exists after write');
    }

    public function testCountFilesEmpty(): void
    {
        $this->assertSame(0, $this->driver->countFiles('nonexistent'));
    }

    public function testCountFiles(): void
    {
        $this->driver->write('counted', 'a', ['title' => 'A', 'content' => 'test']);
        $this->driver->write('counted', 'b', ['title' => 'B', 'content' => 'test']);

        $this->assertSame(2, $this->driver->countFiles('counted'));
    }

    public function testListIdsEmpty(): void
    {
        $this->assertSame([], $this->driver->listIds('nonexistent'));
    }

    public function testListIds(): void
    {
        $this->driver->write('listed', 'first', ['title' => 'First', 'content' => 'x']);
        $this->driver->write('listed', 'second', ['title' => 'Second', 'content' => 'y']);

        $ids = $this->driver->listIds('listed');
        sort($ids);

        $this->assertCount(2, $ids);
        $this->assertSame('first', $ids[0]);
        $this->assertSame('second', $ids[1]);
    }

    public function testOverwriteExisting(): void
    {
        $this->driver->write('pages', 'overwrite-test', ['title' => 'Original', 'version' => 1, 'content' => 'Original content']);

        $original = $this->driver->read('pages', 'overwrite-test');
        $this->assertSame('Original', $original['title'], 'Original data written correctly');

        $this->driver->write('pages', 'overwrite-test', ['title' => 'Updated', 'version' => 2, 'content' => 'Updated content']);

        $updated = $this->driver->read('pages', 'overwrite-test');
        $this->assertSame('Updated', $updated['title'], 'write() overwrites existing document');
        $this->assertSame(2, $updated['version'], 'write() replaces all data');
    }
}
