<?php declare(strict_types=1);

/**
 * Process-wide collection contract with optional per-database isolation.
 *
 * Core definitions are sealed. Modules may add collections, but conflicting
 * registrations never replace an existing contract implicitly.
 */
final class CollectionRegistry
{
    private static $shared = null;

    private $coreSchemaPath;
    private $definitions = [];
    private $coreDefinitions = [];

    public function __construct($coreSchemaPath = null)
    {
        $this->coreSchemaPath = $coreSchemaPath ?? (MANTRA_CORE . '/schemas');
    }

    public static function shared()
    {
        if (self::$shared === null) {
            self::$shared = new self();
        }

        return self::$shared;
    }

    public function register($collection, $schemaPath)
    {
        return $this->registerDefinition(new CollectionDefinition($collection, $schemaPath));
    }

    public function registerDefinition(CollectionDefinition $definition)
    {
        $collection = $definition->name();
        $coreSchemaPath = $this->coreSchemaPath . '/' . $collection . '.php';
        if (is_file($coreSchemaPath)) {
            throw new LogicException("Core collection '{$collection}' cannot be overridden");
        }

        if (isset($this->definitions[$collection])) {
            $registered = $this->definitions[$collection];
            if ($registered->schemaPath() === $definition->schemaPath()) {
                return $registered;
            }

            throw new LogicException("Collection '{$collection}' is already registered");
        }

        $this->definitions[$collection] = $definition;
        return $definition;
    }

    /** Remove a module-owned collection definition. */
    public function unregister($collection): void
    {
        $this->assertValidCollectionName($collection);
        unset($this->definitions[$collection]);
    }

    public function definition($collection)
    {
        $this->assertValidCollectionName($collection);

        if (isset($this->definitions[$collection])) {
            return $this->definitions[$collection];
        }
        if (isset($this->coreDefinitions[$collection])) {
            return $this->coreDefinitions[$collection];
        }

        $schemaPath = $this->coreSchemaPath . '/' . $collection . '.php';
        if (!is_file($schemaPath)) {
            return null;
        }

        $definition = new CollectionDefinition($collection, $schemaPath);
        $this->coreDefinitions[$collection] = $definition;
        return $definition;
    }

    public function schema($collection)
    {
        $definition = $this->definition($collection);
        return $definition ? $definition->schema() : null;
    }

    public function has($collection)
    {
        return $this->definition($collection) !== null;
    }

    private function assertValidCollectionName($collection): void
    {
        if (!is_string($collection) || preg_match('/^[a-zA-Z0-9_-]+$/', $collection) !== 1) {
            throw new InvalidArgumentException('Invalid collection name');
        }
    }
}
