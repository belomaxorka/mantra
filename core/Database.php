<?php
/**
 * Database - Flat-file database abstraction
 * Provides CRUD operations for JSON-based storage
 */

class Database {
    private $basePath = '';
    
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
        
        $content = file_get_contents($path);
        return json_decode($content, true);
    }
    
    /**
     * Write data to file
     */
    public function write($collection, $id, $data) {
        // Validate collection name (prevent directory traversal)
        if (!$this->isValidCollectionName($collection)) {
            throw new Exception('Invalid collection name');
        }
        
        // Validate ID (prevent directory traversal)
        if (!$this->isValidId($id)) {
            throw new Exception('Invalid ID');
        }
        
        $path = $this->getPath($collection, $id);
        $dir = dirname($path);
        
        // Create directory if not exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Add metadata
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Write file atomically
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Failed to encode JSON');
        }
        
        $tempFile = $path . '.tmp';
        if (file_put_contents($tempFile, $json) === false) {
            return false;
        }
        
        return rename($tempFile, $path);
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
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if ($data) {
                $id = basename($file, '.json');
                $data['_id'] = $id;
                $items[] = $data;
            }
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
    
    /**
     * Generate unique ID
     */
    public function generateId() {
        return uniqid() . '-' . mt_rand(1000, 9999);
    }
}
