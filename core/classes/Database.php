<?php declare(strict_types=1);

/**
 * Database - Flat-file database abstraction
 * Provides CRUD operations for JSON-based storage
 */

use Storage\JsonStorageDriver;
use Storage\MarkdownStorageDriver;
use Storage\FileIO;
use Storage\FileTransaction;
use Storage\RevisionStore;

class UniqueConstraintViolationException extends RuntimeException
{
    private $field;

    public function __construct($collection, $field)
    {
        parent::__construct("Duplicate value for unique field '{$collection}.{$field}'");
        $this->field = $field;
    }

    public function getField()
    {
        return $this->field;
    }
}

class Database
{
    private $basePath = '';
    private $jsonDriver = null;
    private $markdownDriver = null;
    private $revisionStore = null;
    private $transactionRoot = null;

    // Schema cache: collection => schema array
    private $collectionSchemas = [];

    // Module-registered schemas: collection => file path
    private $registeredSchemas = [];

    // In-request collection cache: collection => items array
    private $collectionCache = [];

    public function __construct($basePath = null, $revisionStore = null)
    {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
        $this->jsonDriver = new JsonStorageDriver($this->basePath);
        $this->markdownDriver = new MarkdownStorageDriver($this->basePath);
        $revisionRoot = $this->basePath === MANTRA_CONTENT
            ? null
            : ($this->basePath . '/.revisions');
        $this->transactionRoot = $this->basePath === MANTRA_CONTENT
            ? null
            : ($this->basePath . '/.transactions');
        $this->revisionStore = $revisionStore ?? new RevisionStore($revisionRoot);
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
        $this->assertValidCollectionName($collection);
        if ($id === null) {
            // Read all items in collection
            return $this->readCollection($collection);
        }

        $this->assertValidId($id);

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
            $normalized = $this->persistNormalization($collection, $id, $driver);
            if ($normalized === null) {
                return null;
            }
            $normalized['_id'] = $id;
            return $normalized;
        }

        $data['_id'] = $id;
        return $data;
    }

    /**
     * Create a document with an immutable opaque identifier.
     *
     * Callers must not derive storage identifiers from mutable fields such as
     * slugs. Public URLs resolve by indexed document fields instead.
     *
     * @return string Created document ID
     */
    public function create($collection, $data)
    {
        unset($this->collectionCache[$collection]);
        $this->assertValidCollectionName($collection);

        return FileIO::withExclusiveLock(
            $this->basePath . '/' . $collection . '/.collection',
            function () use ($collection, $data) {
                $driver = $this->getDriver($collection);
                do {
                    $id = $this->generateId();
                } while ($driver->exists($collection, $id));

                if (!$this->writeLocked($collection, $id, $data)) {
                    throw new Exception('Failed to create document');
                }
                return $id;
            },
        );
    }

    /** Sanitize input according to the collection's registered schema. */
    public function sanitizeForCollection($collection, $data)
    {
        $data = SchemaValidator::sanitize($data);
        return SchemaValidator::sanitizeBySchema($data, $this->getCollectionSchema($collection));
    }

    /**
     * Write data to file
     */
    public function write($collection, $id, $data)
    {
        unset($this->collectionCache[$collection]);
        $this->assertValidCollectionName($collection);
        $this->assertValidId($id);

        return FileIO::withExclusiveLock(
            $this->basePath . '/' . $collection . '/.collection',
            fn() => $this->writeLocked($collection, $id, $data),
        );
    }

    /** Validate and persist while the collection-level lock is held. */
    private function writeLocked($collection, $id, $data)
    {
        $data = array_merge([], $data);
        unset($data['_id']);

        $data = $this->sanitizeForCollection($collection, $data);

        $schema = $this->getCollectionSchema($collection);
        if ($schema) {
            $data = SchemaMigrator::migrate($data, $schema);
        }
        if ($schema && !empty($schema['defaults']) && is_array($schema['defaults'])) {
            foreach ($schema['defaults'] as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

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

        $driver = $this->getDriver($collection);
        $this->assertUniqueConstraints($driver, $collection, $id, $data, $schema);

        $existing = null;
        if ($driver->exists($collection, $id)) {
            $existing = $driver->read($collection, $id);
            if ($existing && isset($existing['created_at'])) {
                $data['created_at'] = $existing['created_at'];
            } else {
                $data['created_at'] = clock()->timestamp();
            }
        } elseif (!isset($data['created_at'])) {
            $data['created_at'] = clock()->timestamp();
        }
        $data['updated_at'] = clock()->timestamp();

        if (!isset($data['schema_version']) && $schema) {
            $data['schema_version'] = (int)$schema['version'];
        }

        if (is_array($existing)) {
            $this->revisionStore->capture($collection, $id, $existing, 'update');
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
        return $this->deleteInternal($collection, $id, [], null);
    }

    /**
     * Delete a document and related project files as one recoverable unit.
     *
     * Document validation, revision capture, cache invalidation, and the
     * collection lock remain owned by Database; callers only supply related
     * file paths such as an upload binary.
     */
    public function deleteWithRelatedFiles($collection, $id, $relatedPaths = [])
    {
        return $this->deleteInternal($collection, $id, $relatedPaths, null);
    }

    /**
     * Conditionally delete while the collection lock is held.
     *
     * The predicate receives the target document and all current documents,
     * keyed by ID. It must not call back into this Database instance.
     */
    public function deleteIf($collection, $id, $predicate)
    {
        if (!is_callable($predicate)) {
            throw new InvalidArgumentException('Delete predicate must be callable');
        }
        return $this->deleteInternal($collection, $id, [], $predicate);
    }

    private function deleteInternal($collection, $id, $relatedPaths, $predicate)
    {
        $this->assertValidCollectionName($collection);
        $this->assertValidId($id);
        if (!is_array($relatedPaths)) {
            throw new InvalidArgumentException('Related delete paths must be an array');
        }
        unset($this->collectionCache[$collection]);
        $driver = $this->getDriver($collection);
        return FileIO::withExclusiveLock(
            $this->basePath . '/' . $collection . '/.collection',
            function () use ($driver, $collection, $id, $relatedPaths, $predicate) {
                $existing = $driver->read($collection, $id);
                if ($existing === null) {
                    return false;
                }
                if ($predicate !== null
                    && !$predicate($existing, $driver->readCollection($collection))) {
                    return false;
                }
                $this->revisionStore->capture($collection, $id, $existing, 'delete');

                if (!empty($relatedPaths)) {
                    $transaction = new FileTransaction($this->transactionRoot);
                    foreach ($relatedPaths as $path) {
                        $transaction->delete($path);
                    }
                    $transaction->delete($driver->pathFor($collection, $id));
                    return $transaction->commit();
                }

                return $driver->delete($collection, $id);
            },
        );
    }

    /**
     * Check if item exists
     */
    public function exists($collection, $id)
    {
        $this->assertValidCollectionName($collection);
        $this->assertValidId($id);
        $driver = $this->getDriver($collection);
        return $driver->exists($collection, $id);
    }

    /**
     * Check that a field value is unique in a collection.
     */
    public function isUnique($collection, $field, $value, $excludeId = null)
    {
        $this->assertValidCollectionName($collection);
        if (preg_match('/^[a-zA-Z0-9_-]+$/', (string)$field) !== 1) {
            throw new InvalidArgumentException('Invalid unique field name');
        }
        if ($excludeId !== null) {
            $this->assertValidId($excludeId);
        }
        $items = $this->query($collection, [(string)$field => $value]);
        foreach ($items as $item) {
            if ($excludeId !== null && ($item['_id'] ?? null) === $excludeId) {
                continue;
            }
            return false;
        }
        return true;
    }

    /**
     * Read entire collection (cached per request)
     */
    private function readCollection($collection)
    {
        $this->assertValidCollectionName($collection);
        if (isset($this->collectionCache[$collection])) {
            return $this->collectionCache[$collection];
        }

        $driver = $this->getDriver($collection);
        $items = [];
        $documents = $driver->readCollection($collection);

        foreach ($documents as $id => $data) {
            $normalized = $this->normalizeDocument($collection, $id, $data);
            if ($normalized !== $data) {
                $data = $this->persistNormalization($collection, $id, $driver);
                if ($data === null) {
                    continue;
                }
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
        $this->assertValidCollectionName($collection);
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
        $this->assertValidCollectionName($collection);
        $driver = $this->getDriver($collection);
        return $driver->listIds($collection);
    }

    /**
     * Register a schema path for a collection (used by modules).
     */
    public function registerSchema($collection, $schemaPath): void
    {
        $this->assertValidCollectionName($collection);
        $this->registeredSchemas[$collection] = $schemaPath;
        unset($this->collectionSchemas[$collection]);
    }

    public function revisions($collection, $id)
    {
        $this->assertValidCollectionName($collection);
        $this->assertValidId($id);
        return $this->revisionStore->all($collection, $id);
    }

    public function restoreRevision($collection, $id, $revisionId)
    {
        $this->assertValidCollectionName($collection);
        $this->assertValidId($id);
        $revision = $this->revisionStore->read($collection, $id, $revisionId);
        if (!is_array($revision) || !isset($revision['data']) || !is_array($revision['data'])) {
            return false;
        }
        return $this->write($collection, $id, $revision['data']);
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

        // The shared migrator validates every callback result and refuses to
        // downgrade documents written by a newer runtime.
        $data = SchemaMigrator::migrate($data, $schema);

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

    /** Re-read and migrate under the collection lock to avoid stale overwrite. */
    private function persistNormalization($collection, $id, $driver)
    {
        return FileIO::withExclusiveLock(
            $this->basePath . '/' . $collection . '/.collection',
            function () use ($collection, $id, $driver) {
                $latest = $driver->read($collection, $id);
                if ($latest === null) {
                    return null;
                }
                $normalized = $this->normalizeDocument($collection, $id, $latest);
                if ($normalized !== $latest) {
                    $this->revisionStore->capture($collection, $id, $latest, 'migration');
                    $driver->write($collection, $id, $normalized);
                }
                return $normalized;
            },
        );
    }

    /**
     * Generate unique ID (cryptographically secure)
     */
    public function generateId()
    {
        return bin2hex(random_bytes(8)) . '-' . dechex(time());
    }

    private function assertUniqueConstraints($driver, $collection, $id, $data, $schema): void
    {
        if (!is_array($schema) || empty($schema['unique']) || !is_array($schema['unique'])) {
            return;
        }

        $documents = $driver->readCollection($collection);
        foreach ($schema['unique'] as $key => $rule) {
            $field = is_int($key) ? (string)$rule : (string)$key;
            $options = is_int($key) || !is_array($rule) ? [] : $rule;
            if ($field === '' || !array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if ($value === '' || $value === null) {
                continue;
            }
            $caseInsensitive = !empty($options['case_insensitive']);

            foreach ($documents as $existingId => $document) {
                if ((string)$existingId === (string)$id || !array_key_exists($field, $document)) {
                    continue;
                }
                $existing = $document[$field];
                $duplicate = $caseInsensitive && is_string($value) && is_string($existing)
                    ? strcasecmp($existing, $value) === 0
                    : $existing === $value;
                if ($duplicate) {
                    throw new UniqueConstraintViolationException($collection, $field);
                }
            }
        }
    }

    private function assertValidCollectionName($name): void
    {
        if (!$this->isValidCollectionName($name)) {
            logger()->error('Invalid collection name', ['collection' => $name]);
            throw new InvalidArgumentException('Invalid collection name');
        }
    }

    private function assertValidId($id): void
    {
        if (!$this->isValidId($id)) {
            logger()->error('Invalid ID', ['id' => $id]);
            throw new InvalidArgumentException('Invalid ID');
        }
    }
}
