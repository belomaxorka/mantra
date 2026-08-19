<?php declare(strict_types=1);

/**
 * Immutable collection metadata registered with CollectionRegistry.
 */
final class CollectionDefinition
{
    private $name;
    private $schemaPath;
    private $schema = null;
    private $schemaLoaded = false;

    public function __construct($name, $schemaPath)
    {
        if (!is_string($name) || preg_match('/^[a-zA-Z0-9_-]+$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid collection name');
        }
        if (!is_string($schemaPath) || $schemaPath === '') {
            throw new InvalidArgumentException('Collection schema path must be a non-empty string');
        }

        $this->name = $name;
        $this->schemaPath = $schemaPath;
    }

    public function name()
    {
        return $this->name;
    }

    public function schemaPath()
    {
        return $this->schemaPath;
    }

    /**
     * Load the schema once per definition.
     *
     * A missing file is not cached so a module may register its definition
     * before its files are installed atomically.
     */
    public function schema()
    {
        if ($this->schemaLoaded) {
            return $this->schema;
        }
        if (!is_file($this->schemaPath)) {
            return null;
        }

        $schema = require $this->schemaPath;
        $this->schema = is_array($schema) ? $schema : null;
        $this->schemaLoaded = true;

        return $this->schema;
    }
}
