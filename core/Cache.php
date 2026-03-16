<?php
/**
 * Cache - Simple file-based caching system
 */

class Cache {
    private $cachePath = '';
    private $enabled = true;
    private $lifetime = 3600;
    
    public function __construct() {
        $this->cachePath = MANTRA_STORAGE . '/cache';
        
        $app = Application::getInstance();
        $this->enabled = $app->config('cache.enabled', true);
        $this->lifetime = $app->config('cache.lifetime', 3600);
        
        // Create cache directory
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key, $default = null) {
        if (!$this->enabled) {
            return $default;
        }
        
        $path = $this->getPath($key);
        
        if (!file_exists($path)) {
            return $default;
        }
        
        // Check if expired
        if (time() - filemtime($path) > $this->lifetime) {
            unlink($path);
            return $default;
        }
        
        $content = file_get_contents($path);
        return unserialize($content);
    }
    
    /**
     * Set cache value
     */
    public function set($key, $value, $lifetime = null) {
        if (!$this->enabled) {
            return false;
        }
        
        $path = $this->getPath($key);
        $content = serialize($value);
        
        return file_put_contents($path, $content) !== false;
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        $path = $this->getPath($key);
        
        if (file_exists($path)) {
            return unlink($path);
        }
        
        return false;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $files = glob($this->cachePath . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Remember - get from cache or execute callback
     */
    public function remember($key, $callback, $lifetime = null) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = call_user_func($callback);
        $this->set($key, $value, $lifetime);
        
        return $value;
    }
    
    /**
     * Get cache file path
     */
    private function getPath($key) {
        $hash = md5($key);
        return $this->cachePath . '/' . $hash . '.cache';
    }
}
