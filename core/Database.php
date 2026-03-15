<?php
/**
 * Database - Flat-file database abstraction
 * Provides CRUD operations for JSON-based storage
 */

class Database {
    private $basePath = '';

    // Schema cache: collection => schema array
    private $collectionSchemas = array();

    public function __construct($basePath = null) {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
    }

    /**
     * Read data from file
     */
    public function read($collection, $id = null) {
        $path = $this->getPath($collection, $id);

        if ($id === null) {
            // Read all items in collection
            return $this->readCollection($collection);
        }

        if (!file_exists($path)) {
            return null;
        }

        try {
            $data = JsonFile::read($path);
        } catch (JsonFileException $e) {
            logger()->error('Failed to read JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        }

        $normalized = $this->normalizeDocument($collection, $id, $data);
        if ($normalized !== $data) {
            // Persist migrated defaults.
            $this->write($collection, $id, $normalized);
            return $normalized;
        }

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

        $path = $this->getPath($collection, $id);

        // Add metadata
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Ensure schema version is present (for future migrations)
        if (!isset($data['schema_version'])) {
            $schema = $this->getCollectionSchema($collection);
            if ($schema) {
                $data['schema_version'] = (int)$schema['version'];
            }
        }

        try {
            $result = JsonFile::write($path, $data);
        } catch (JsonFileException $e) {
            logger()->error('Failed to write JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
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
        $path = $this->getPath($collection, $id);
        
        if (file_exists($path)) {
            return unlink($path);
        }
        
        return false;
    }
    
    /**
     * Check if item exists
     */
    public function exists($collection, $id) {
        $path = $this->getPath($collection, $id);
        return file_exists($path);
    }
    
    /**
     * Read entire collection
     */
    private function readCollection($collection) {
        $collectionPath = $this->basePath . '/' . $collection;

        if (!is_dir($collectionPath)) {
            return array();
        }

        $items = array();
        $files = glob($collectionPath . '/*.json');

        foreach ($files as $file) {
            $id = basename($file, '.json');

            try {
                $data = JsonFile::read($file);
            } catch (JsonFileException $e) {
                logger()->error('Failed to read JSON document in collection', array(
                    'collection' => $collection,
                    'id' => $id,
                    'path' => $file,
                    'error' => $e->getMessage()
                ));
                // Skip corrupted/unrecoverable documents.
                continue;
            }

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
    
    /**
     * Get file path
     */
    private function getPath($collection, $id = null) {
        if ($id === null) {
            return $this->basePath . '/' . $collection;
        }
        return $this->basePath . '/' . $collection . '/' . $id . '.json';
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
     * Generate unique ID
     */
    public function generateId() {
        return uniqid() . '-' . mt_rand(1000, 9999);
    }
}
