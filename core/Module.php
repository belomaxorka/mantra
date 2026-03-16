<?php
/**
 * Module - Base class for all modules
 * Provides common functionality and API access
 */

abstract class Module implements ModuleInterface {
    protected $manifest = array();
    protected $app = null;
    protected $moduleId = '';
    protected $modulePath = '';
    
    public function __construct($manifest, $moduleId = '', $modulePath = '') {
        $this->manifest = is_array($manifest) ? $manifest : array();
        $this->moduleId = $moduleId;
        $this->modulePath = $modulePath;
        $this->app = Application::getInstance();
    }
    
    /**
     * Initialize module
     */
    public function init() {
    }
    
    // ========== ModuleInterface Implementation ==========
    
    /**
     * Get module manifest
     */
    public function getManifest() {
        return $this->manifest;
    }
    
    /**
     * Get module ID (kebab-case identifier)
     */
    public function getId() {
        return $this->moduleId;
    }
    
    /**
     * Get module name (human-readable)
     */
    public function getName() {
        return $this->manifest['name'];
    }
    
    /**
     * Get module version
     */
    public function getVersion() {
        return $this->manifest['version'];
    }
    
    /**
     * Get module description
     */
    public function getDescription() {
        return isset($this->manifest['description']) ? $this->manifest['description'] : '';
    }
    
    /**
     * Check if module can be disabled
     */
    public function isDisableable() {
        if (isset($this->manifest['admin']['disableable'])) {
            return (bool)$this->manifest['admin']['disableable'];
        }
        return $this->getType() !== ModuleType::CORE;
    }
    
    /**
     * Check if module can be deleted
     */
    public function isDeletable() {
        if (isset($this->manifest['admin']['deletable'])) {
            return (bool)$this->manifest['admin']['deletable'];
        }
        return $this->getType() !== ModuleType::CORE;
    }
    
    /**
     * Get module dependencies
     */
    public function getDependencies() {
        return isset($this->manifest['dependencies']) && is_array($this->manifest['dependencies']) 
            ? $this->manifest['dependencies'] 
            : array();
    }
    
    /**
     * Called when module is being enabled
     */
    public function onEnable() {
        return true;
    }
    
    /**
     * Called when module is being disabled
     */
    public function onDisable() {
        return true;
    }
    
    /**
     * Called when module is being uninstalled
     */
    public function onUninstall() {
        return true;
    }
    
    // ========== Extended Module API ==========
    
    /**
     * Get module type
     * @return string One of ModuleType constants
     */
    public function getType() {
        if (isset($this->manifest['type']) && ModuleType::isValid($this->manifest['type'])) {
            return $this->manifest['type'];
        }
        return ModuleType::CUSTOM;
    }
    
    /**
     * Get module capabilities
     * @return array Array of ModuleCapability constants
     */
    public function getCapabilities() {
        if (isset($this->manifest['capabilities']) && is_array($this->manifest['capabilities'])) {
            return array_filter($this->manifest['capabilities'], function($cap) {
                return ModuleCapability::isValid($cap);
            });
        }
        return array();
    }
    
    /**
     * Check if module has specific capability
     * @param string $capability
     * @return bool
     */
    public function hasCapability($capability) {
        return in_array($capability, $this->getCapabilities(), true);
    }
    
    public function getAuthor() {
        return $this->manifest['author'] ?? '';
    }
    
    public function getHomepage() {
        return $this->manifest['homepage'] ?? '';
    }
    
    public function getLicense() {
        return $this->manifest['license'] ?? '';
    }
    
    public function getTags() {
        return $this->manifest['tags'] ?? array();
    }
    
    // ========== Helper Methods ==========
    
    protected function hook($hookName, $callback, $priority = 10) {
        $this->app->hooks()->register($hookName, $callback, $priority);
    }
    
    protected function fireHook($hookName, $data = null) {
        return $this->app->hooks()->fire($hookName, $data);
    }
    
    protected function route($method, $pattern, $callback) {
        $router = $this->app->router();
        return match(strtoupper($method)) {
            'GET' => $router->get($pattern, $callback),
            'POST' => $router->post($pattern, $callback),
            default => $router->any($pattern, $callback),
        };
    }
    
    protected function config($key, $default = null) {
        return $this->app->config($key, $default);
    }
    
    protected function view($template, $data = array()) {
        return (new View())->render($template, $data);
    }
    
    public function getPath() {
        return $this->modulePath;
    }
    
    protected function loadFile($filename) {
        $path = $this->getPath() . '/' . $filename;
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }
    
    protected function settings() {
        return module_settings($this->getId());
    }
    
    protected function log($level, $message, $context = array()) {
        $context['module'] = $this->getId();
        return logger()->log($level, $message, $context);
    }
    
    public function hasSettings() {
        return file_exists($this->getPath() . '/settings.schema.php');
    }
    
    public function hasTranslations() {
        return is_dir($this->getPath() . '/lang');
    }
    
    public function getViewsPath() {
        $viewsPath = $this->getPath() . '/views';
        return is_dir($viewsPath) ? $viewsPath : null;
    }
}
