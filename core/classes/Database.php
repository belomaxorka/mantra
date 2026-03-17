<?php
/**
 * Database - Flat-file database abstraction
 * Provides CRUD operations for JSON-based storage
 */

class Database {
    private $basePath = '';
    private $jsonDriver = null;
    private $markdownDriver = null;

    // Schema cache: collection => schema array
    private $collectionSchemas = array();

    public function __construct($basePath = null) {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
        $this->jsonDriver = new JsonStorageDriver($this->basePath);
        $this->markdownDriver = new MarkdownStorageDriver($this->basePath);
    }

    /**
     * Get storage driver for collection
     * Only pages and posts use Markdown (if enabled), everything else uses JSON
     */
    private function getDriver($collection) {
        $format = config('content.format', 'json');

        // Only pages and posts can use Markdown
        $contentCollections = array('pages', 'posts');

        if ($format === 'markdown' && in_array($collection, $contentCollections)) {
            return $this->markdownDriver;
        }

        return $this->jsonDriver;
    }

    /**
     * Read data from file
     */
    public function read($collection, $id = null) {
        if ($id === null) {
            // Read all items in collection
            return $this->readCollection($collection);
        }

        $driver = $this->getDriver($collection);

        try {
            $data = $driver->read($collection, $id);
        } catch (Exception $e) {
            logger()->error('Failed to read document', array(
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage()
            ));
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
    public function write($collection, $id, $data) {
        // Validate collection name (prevent directory traversal)
        if (!$this->isValidCollectionName($collection)) {
            logger()->error('Invalid collection name', array('collection' => $collection));
            throw new Exception('Invalid collection name');
        }

        // Validate ID (prevent directory traversal)
        if (!$this->isValidId($id)) {
            logger()->error('Invalid ID', array('id' => $id));
            throw new Exception('Invalid ID');
        }

        // Clone data to avoid modifying original
        $data = array_merge(array(), $data);
        
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
                logger()->error('Schema validation failed', array(
                    'collection' => $collection,
                    'id' => $id,
                    'errors' => $e->getErrors()
                ));
                throw $e;
            }
        }

        // Add metadata
        // Preserve created_at from existing document if updating
        if (!isset($data['created_at'])) {
            $driver = $this->getDriver($collection);
            if ($driver->exists($collection, $id)) {
                $existing = $driver->read($collection, $id);
                if ($existing && isset($existing['created_at'])) {
                    $data['created_at'] = $existing['created_at'];
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                }
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

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
    private function writeRaw($collection, $id, $data) {
        $driver = $this->getDriver($collection);

        try {
            $result = $driver->write($collection, $id, $data);
        } catch (Exception $e) {
            logger()->error('Failed to write document', array(
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage()
            ));
            throw $e;
        }

        if ($result) {
            logger()->debug('Data written', array('collection' => $collection, 'id' => $id));
        }

        return $result;
    }
    
    /**
     * Validate collection name
     */
    private function isValidCollectionName($name) {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }
    
    /**
     * Validate ID
     */
    private function isValidId($id) {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
    }
    
    /**
     * Delete data file
     */
    public function delete($collection, $id) {
        $driver = $this->getDriver($collection);
        return $driver->delete($collection, $id);
    }

    /**
     * Check if item exists
     */
    public function exists($collection, $id) {
        $driver = $this->getDriver($collection);
        return $driver->exists($collection, $id);
    }
    
    /**
     * Read entire collection
     */
    private function readCollection($collection) {
        $driver = $this->getDriver($collection);
        $items = array();
        $documents = $driver->readCollection($collection);

        foreach ($documents as $id => $data) {
            $normalized = $this->normalizeDocument($collection, $id, $data);
            if ($normalized !== $data) {
                $this->write($collection, $id, $normalized);
                $data = $normalized;
            }

            $data['_id'] = $id;
            $items[] = $data;
        }

        return $items;
    }
    
    /**
     * Query collection with filters
     */
    public function query($collection, $filters = array(), $options = array()) {
        $items = $this->readCollection($collection);
        
        // Apply filters
        if (!empty($filters)) {
            $items = array_filter($items, function($item) use ($filters) {
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
            $sortOrder = isset($options['order']) ? $options['order'] : 'asc';
            
            usort($items, function($a, $b) use ($sortField, $sortOrder) {
                $valA = isset($a[$sortField]) ? $a[$sortField] : '';
                $valB = isset($b[$sortField]) ? $b[$sortField] : '';
                
                $cmp = strcmp($valA, $valB);
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }
        
        // Apply limit
        if (isset($options['limit'])) {
            $offset = isset($options['offset']) ? $options['offset'] : 0;
            $items = array_slice($items, $offset, $options['limit']);
        }
        
        return array_values($items);
    }
    
    
    private function getCollectionSchema($collection)
    {
        if (isset($this->collectionSchemas[$collection])) {
            return $this->collectionSchemas[$collection];
        }

        $schemaPath = MANTRA_CORE . '/schemas/' . $collection . '.php';
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
        if (!$schema || !is_array($schema)) {
            return $data;
        }


        $currentVersion = isset($schema['version']) ? (int)$schema['version'] : 0;
        $docVersion = isset($data['schema_version']) ? (int)$data['schema_version'] : 0;

        if (!isset($data['schema_version'])) {
            $data['schema_version'] = $currentVersion;
            $docVersion = $currentVersion;
        }

        if (!empty($schema['defaults']) && is_array($schema['defaults'])) {
            foreach ($schema['defaults'] as $key => $value) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }
        }

        // Optional migrator callback: function(array $doc, int $fromVersion, int $toVersion): array
        if ($docVersion < $currentVersion && !empty($schema['migrate']) && is_callable($schema['migrate'])) {
            $data = call_user_func($schema['migrate'], $data, $docVersion, $currentVersion);
            $data['schema_version'] = $currentVersion;
        } elseif ($docVersion < $currentVersion) {
            // No migrator: only bump the version + defaults.
            $data['schema_version'] = $currentVersion;
        }

        return $data;
    }

    /**
     * Generate unique ID (cryptographically secure)
     */
    public function generateId() {
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
        return uniqid() . '-' . mt_rand(1000, 9999);
    }
}
