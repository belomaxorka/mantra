<?php
/**
 * ModuleManager - Manages module lifecycle
 * Loads, initializes, and provides access to modules
 */

class ModuleManager {
    private $modules = array();
    private $config = array();
    private $loadedModules = array();
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Load all enabled modules
     */
    public function loadModules() {
        $enabledModules = isset($this->config['enabled_modules']) 
            ? $this->config['enabled_modules'] 
            : array();
        
        foreach ($enabledModules as $moduleName) {
            $this->loadModule($moduleName);
        }
    }
    
    /**
     * Load a single module
     */
    public function loadModule($moduleName) {
        if (isset($this->loadedModules[$moduleName])) {
            return true; // Already loaded
        }
        
        $modulePath = MANTRA_MODULES . '/' . $moduleName;
        $manifestPath = $modulePath . '/module.json';
        $mainFile = $modulePath . '/' . ucfirst($moduleName) . 'Module.php';
        
        // Check if module exists
        if (!file_exists($manifestPath) || !file_exists($mainFile)) {
            return false;
        }
        
        // Load manifest
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            return false;
        }
        
        // Check dependencies
        if (isset($manifest['dependencies'])) {
            foreach ($manifest['dependencies'] as $dependency) {
                if (!$this->loadModule($dependency)) {
                    throw new Exception("Module '$moduleName' requires '$dependency'");
                }
            }
        }
        
        // Load module class
        require_once $mainFile;
        
        $className = ucfirst($moduleName) . 'Module';
        if (!class_exists($className)) {
            throw new Exception("Module class '$className' not found");
        }
        
        // Instantiate module
        $module = new $className($manifest);
        
        // Initialize module
        if (method_exists($module, 'init')) {
            $module->init();
        }
        
        $this->modules[$moduleName] = array(
            'instance' => $module,
            'manifest' => $manifest,
            'path' => $modulePath
        );
        
        $this->loadedModules[$moduleName] = true;
        
        return true;
    }
    
    /**
     * Get module instance
     */
    public function getModule($moduleName) {
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName]['instance'];
        }
        return null;
    }
    
    /**
     * Get all loaded modules
     */
    public function getModules() {
        return $this->modules;
    }
    
    /**
     * Check if module is loaded
     */
    public function isLoaded($moduleName) {
        return isset($this->loadedModules[$moduleName]);
    }
    
    /**
     * Get module manifest
     */
    public function getManifest($moduleName) {
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName]['manifest'];
        }
        return null;
    }
}
