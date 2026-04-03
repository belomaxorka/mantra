<?php declare(strict_types=1);

/**
 * Database - Flat-file database abstraction
 * Provides CRUD operations for JSON-based storage
 */

use Storage\JsonStorageDriver;
use Storage\MarkdownStorageDriver;

class Database
{
    private $basePath = '';
    private $jsonDriver = null;
    private $markdownDriver = null;

    // Schema cache: collection => schema array
    private $collectionSchemas = [];

    // Module-registered schemas: collection => file path
    private $registeredSchemas = [];

    // In-request collection cache: collection => items array
    private $collectionCache = [];

    public function __construct($basePath = null)
    {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
        $this->jsonDriver = new JsonStorageDriver($this->basePath);
        $this->markdownDriver = new MarkdownStorageDriver($this->basePath);
    }

    /**
     * Get storage driver for collection
     * Only pages and posts use Markdown (if enabled), everything else uses JSON
     */
    private function getDriver($collection)
    {
        $format = config('content.format', 'json');

        // Only pages and posts can use Markdown
        $contentCollections = ['pages', 'posts'];

        if ($format === 'markdown' && in_array($collection, $contentCollections)) {
            return $this->markdownDriver;
        }

        return $this->jsonDriver;
    }

    /**
     * Read data from file
     */
    public function read($collection, $id = null)
    {
        if ($id === null) {
            // Read all items in collection
            return $this->readCollection($collection);
        }

        $driver = $this->getDriver($collection);

        try {
            $data = $driver->read($collection, $id);
        } catch (Exception $e) {
            logger()->error('Failed to read document', [
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($data === null) {
            return null;
        }

        $normalized = $this->normalizeDocument($collection, $id, $data);
        if ($normalized !== $data) {
            // Persist migrated defaults using raw write to avoid recursion
            $this->writeRaw($collection, $id, $normalized);
            $normalized['_id'] = $id;
            return $normalized;
        }

        $data['_id'] = $id;
        return $data;
    }

    /**
     * Write data to file
     */
    public function write($collection, $id, $data)
    {
        // Invalidate in-request cache for this collection
        unset($this->collectionCache[$collection]);

        // Validate collection name (prevent directory traversal)
        if (!$this->isValidCollectionName($collection)) {
            logger()->error('Invalid collection name', ['collection' => $collection]);
            throw new Exception('Invalid collection name');
        }

        // Validate ID (prevent directory traversal)
        if (!$this->isValidId($id)) {
            logger()->error('Invalid ID', ['id' => $id]);
            throw new Exception('Invalid ID');
        }

        // Clone data to avoid modifying original
        $data = array_merge([], $data);

        // Sanitize input data
        $data = SchemaValidator::sanitize($data);

        // Get schema and apply defaults BEFORE validation
        $schema = $this->getCollectionSchema($collection);
        if ($schema && !empty($schema['defaults']) && is_array($schema['defaults'])) {
            foreach ($schema['defaults'] as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

        // Validate against schema
        if ($schema && isset($schema['fields'])) {
            try {
                SchemaValidator::validateOrThrow($data, $schema);
            } catch (SchemaValidationException $e) {
                logger()->error('Schema validation failed', [
                    'collection' => $collection,
                    'id' => $id,
                    'errors' => $e->getErrors(),
                ]);
                throw $e;
            }
        }

        // Add metadata
        // Always preserve created_at from existing document on update (immutable)
        $driver = $this->getDriver($collection);
        if ($driver->exists($collection, $id)) {
            // Updating existing document - preserve original created_at
            $existing = $driver->read($collection, $id);
            if ($existing && isset($existing['created_at'])) {
                $data['created_at'] = $existing['created_at'];
            } else {
                // Existing document missing created_at - set it now
                $data['created_at'] = clock()->timestamp();
            }
        } else {
            // New document - use provided created_at or generate new
            if (!isset($data['created_at'])) {
                $data['created_at'] = clock()->timestamp();
            }
        }
        $data['updated_at'] = clock()->timestamp();

        // Ensure schema version is present (for future migrations)
        if (!isset($data['schema_version'])) {
            if ($schema) {
                $data['schema_version'] = (int)$schema['version'];
            }
        }

        return $this->writeRaw($collection, $id, $data);
    }

    /**
     * Write data without normalization (internal use)
     */
    private function writeRaw($collection, $id, $data)
    {
        $driver = $this->getDriver($collection);

        try {
            $result = $driver->write($collection, $id, $data);
        } catch (Exception $e) {
            logger()->error('Failed to write document', [
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($result) {
            logger()->debug('Data written', ['collection' => $collection, 'id' => $id]);
        }

        return $result;
    }

    /**
     * Validate collection name
     */
    private function isValidCollectionName($name)
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }

    /**
     * Validate ID
     */
    private function isValidId($id)
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
    }

    /**
     * Delete data file
     */
    public function delete($collection, $id)
    {
        unset($this->collectionCache[$collection]);
        $driver = $this->getDriver($collection);
        return $driver->delete($collection, $id);
    }

    /**
     * Check if item exists
     */
    public function exists($collection, $id)
    {
        $driver = $this->getDriver($collection);
        return $driver->exists($collection, $id);
    }

    /**
     * Read entire collection (cached per request)
     */
    private function readCollection($collection)
    {
        if (isset($this->collectionCache[$collection])) {
            return $this->collectionCache[$collection];
        }

        $driver = $this->getDriver($collection);
        $items = [];
        $documents = $driver->readCollection($collection);

        foreach ($documents as $id => $data) {
            $normalized = $this->normalizeDocument($collection, $id, $data);
            if ($normalized !== $data) {
                $this->writeRaw($collection, $id, $normalized);
                $data = $normalized;
            }

            $data['_id'] = $id;
            $items[] = $data;
        }

        $this->collectionCache[$collection] = $items;
        return $items;
    }

    /**
     * Query collection with filters
     */
    public function query($collection, $filters = [], $options = [])
    {
        $items = $this->readCollection($collection);

        // Apply filters
        if (!empty($filters)) {
            $items = array_filter($items, function ($item) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (!isset($item[$key]) || $item[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Apply sorting
        if (isset($options['sort'])) {
            $sortField = $options['sort'];
            $sortOrder = $options['order'] ?? 'asc';

            usort($items, function ($a, $b) use ($sortField, $sortOrder) {
                $valA = $a[$sortField] ?? '';
                $valB = $b[$sortField] ?? '';

                // Type-aware comparison
                if (is_numeric($valA) && is_numeric($valB)) {
                    // Numeric comparison
                    $cmp = ($valA < $valB) ? -1 : (($valA > $valB) ? 1 : 0);
                } else {
                    // String comparison
                    $cmp = strcmp($valA, $valB);
                }

                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }

        // Apply limit
        if (isset($options['limit'])) {
            $offset = $options['offset'] ?? 0;
            $items = array_slice($items, $offset, $options['limit']);
        }

        return array_values($items);
    }

    /**
     * Count documents in a collection, optionally filtered.
     *
     * Without filters, counts files on disk without reading contents (fast path).
     * With filters, reads the collection and counts matching documents.
     *
     * @param string $collection Collection name
     * @param array $filters Key-value equality filters (same as query())
     * @return int
     */
    public function count($collection, $filters = [])
    {
        // Fast path: no filters — count files without reading contents
        if (empty($filters)) {
            $driver = $this->getDriver($collection);
            return $driver->countFiles($collection);
        }

        // Filtered count requires reading documents
        $items = $this->readCollection($collection);

        $count = 0;
        foreach ($items as $item) {
            $match = true;
            foreach ($filters as $key => $value) {
                if (!isset($item[$key]) || $item[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * List document IDs in a collection without reading contents.
     *
     * @param string $collection Collection name
     * @return array Array of document IDs
     */
    public function listIds($collection)
    {
        $driver = $this->getDriver($collection);
        return $driver->listIds($collection);
    }

    /**
     * Register a schema path for a collection (used by modules).
     */
    public function registerSchema($collection, $schemaPath): void
    {
        $this->registeredSchemas[$collection] = $schemaPath;
        unset($this->collectionSchemas[$collection]);
    }

    private function getCollectionSchema($collection)
    {
        if (isset($this->collectionSchemas[$collection])) {
            return $this->collectionSchemas[$collection];
        }

        // Check module-registered schemas first, then core fallback
        if (isset($this->registeredSchemas[$collection])) {
            $schemaPath = $this->registeredSchemas[$collection];
        } else {
            $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
        }
        if (!file_exists($schemaPath)) {
            $this->collectionSchemas[$collection] = null;
            return null;
        }

        $schema = require $schemaPath;
        if (!is_array($schema)) {
            $this->collectionSchemas[$collection] = null;
            return null;
        }

        $this->collectionSchemas[$collection] = $schema;
        return $schema;
    }

    /**
     * Apply per-collection defaults and schema version migrations.
     */
    private function normalizeDocument($collection, $id, $data)
    {
        $schema = $this->getCollectionSchema($collection);
        if (!$schema) {
            return $data;
        }


        $currentVersion = isset($schema['version']) ? (int)$schema['version'] : 0;
        $docVersion = isset($data['schema_version']) ? (int)$data['schema_version'] : 0;

        // Run migration BEFORE applying defaults so that migrate callbacks
        // operate on the raw document (defaults won't shadow old field names).
        if ($docVersion < $currentVersion && !empty($schema['migrate']) && is_callable($schema['migrate'])) {
            $data = call_user_func($schema['migrate'], $data, $docVersion, $currentVersion);
            if (!is_array($data)) {
                $data = [];
            }
            $data['schema_version'] = $currentVersion;
        } elseif ($docVersion < $currentVersion) {
            // No migrator: only bump the version.
            $data['schema_version'] = $currentVersion;
        }

        // Apply defaults for any still-missing fields (after migration).
        if (!empty($schema['defaults']) && is_array($schema['defaults'])) {
            foreach ($schema['defaults'] as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Generate unique ID (cryptographically secure)
     */
    public function generateId()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(8)) . '-' . dechex(time());
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(8, $strong);
            if ($strong) {
                return bin2hex($bytes) . '-' . dechex(time());
            }
        }

        // Fallback (less secure)
        return uniqid() . '-' . random_int(1000, 9999);
    }
}
