<?php declare(strict_types=1);

/**
 * Base test case for Mantra CMS tests
 *
 * Provides shared utilities used across multiple test classes:
 * filesystem helpers, schema creation, and raw JSON writing.
 */

use PHPUnit\Framework\TestCase;

abstract class MantraTestCase extends TestCase
{
    /**
     * Recursively remove a directory and all its contents.
     */
    protected function removeDirectory($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Ensure the core schemas directory exists.
     */
    protected function ensureSchemasDir(): void
    {
        $dir = MANTRA_CORE . '/schemas';
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
    }

    /**
     * Create a test schema file via var_export (no closures).
     */
    protected function createTestSchema($collection, $schema): void
    {
        $this->ensureSchemasDir();
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        $schemaContent = "<?php\nreturn " . var_export($schema, true) . ";\n";
        file_put_contents($schemaPath, $schemaContent);
    }

    /**
     * Write a schema file with raw PHP code (supports closures).
     */
    protected function writeSchemaFile($collection, $phpCode): void
    {
        $this->ensureSchemasDir();
        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        file_put_contents($schemaPath, $phpCode);
    }

    /**
     * Clean up test schema files matching a glob pattern.
     */
    protected function cleanupTestSchemas($pattern): void
    {
        $schemas = glob(MANTRA_CORE . '/schemas/' . $pattern);
        foreach ($schemas as $schema) {
            @unlink($schema);
        }
    }
}
