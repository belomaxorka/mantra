<?php
/**
 * Config - Configuration management
 * Allows reading and writing configuration to JSON file
 */

class Config {
    private $configPath = '';
    private $config = array();
    
    public function __construct() {
        $this->configPath = MANTRA_CONTENT . '/settings/config.json';
        $this->load();
    }
    
    /**
     * Load configuration from file
     */
    private function load() {
        if (file_exists($this->configPath)) {
            $content = file_get_contents($this->configPath);
            $this->config = json_decode($content, true);
            if (!$this->config) {
                $this->config = array();
            }
        }
    }
    
    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        // Check in loaded JSON config first
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        
        // Fallback to application config
        $app = Application::getInstance();
        return $app->config($key, $default);
    }
    
    /**
     * Set configuration value
     */
    public function set($key, $value) {
        $this->config[$key] = $value;
        return $this->save();
    }
    
    /**
     * Set multiple configuration values
     */
    public function setMultiple($data) {
        $this->config = array_merge($this->config, $data);
        return $this->save();
    }
    
    /**
     * Get all configuration
     */
    public function all() {
        return $this->config;
    }
    
    /**
     * Save configuration to file
     */
    public function save() {
        $dir = dirname($this->configPath);
        
        // Create directory if not exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->configPath, $json) !== false;
    }
    
    /**
     * Delete configuration key
     */
    public function delete($key) {
        if (isset($this->config[$key])) {
            unset($this->config[$key]);
            return $this->save();
        }
        return false;
    }
    
    /**
     * Check if key exists
     */
    public function has($key) {
        return isset($this->config[$key]);
    }
}


