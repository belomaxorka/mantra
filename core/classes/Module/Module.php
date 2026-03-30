<?php
/**
 * Module - Base class for all modules
 * Provides common functionality and API access
 */

namespace Module;

use Application;

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
        return resolve_localized($this->manifest['name']);
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
        return isset($this->manifest['description']) ? resolve_localized($this->manifest['description']) : '';
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
        return isset($this->manifest['author']) ? $this->manifest['author'] : '';
    }

    public function getHomepage() {
        return isset($this->manifest['homepage']) ? $this->manifest['homepage'] : '';
    }

    public function getLicense() {
        return isset($this->manifest['license']) ? $this->manifest['license'] : '';
    }

    public function getTags() {
        return isset($this->manifest['tags']) ? $this->manifest['tags'] : array();
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
        $m = strtoupper($method);
        if ($m === 'GET') {
            return $router->get($pattern, $callback);
        } elseif ($m === 'POST') {
            return $router->post($pattern, $callback);
        } else {
            return $router->any($pattern, $callback);
        }
    }
    
    protected function config($key, $default = null) {
        return $this->app->config($key, $default);
    }
    
    protected function view($template, $data = array()) {
        return view($template, $data);
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

    /**
     * Get module URL (web-accessible path)
     * @return string Module URL (e.g., "/modules/my-module")
     */
    public function getUrl() {
        return '/' . basename(MANTRA_MODULES) . '/' . $this->getId();
    }

    /**
     * Get full module URL with base URL
     * @return string Full module URL (e.g., "http://example.com/modules/my-module")
     */
    public function getBaseUrl() {
        return base_url($this->getUrl());
    }

    /**
     * Get asset URL for a file in module's assets directory
     * @param string $path Path relative to assets directory (e.g., "css/style.css")
     * @return string Full asset URL
     */
    public function asset($path) {
        $path = ltrim($path, '/');
        return base_url($this->getUrl() . '/assets/' . $path);
    }

    /**
     * Enqueue CSS file in admin panel
     * @param string $path Path relative to assets directory (e.g., "css/admin.css")
     * @param int $priority Hook priority (default: 10)
     */
    protected function enqueueAdminStyle($path, $priority = 10) {
        $url = $this->asset($path);
        $this->hook('admin.head', function($content) use ($url) {
            return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
        }, $priority);
    }

    /**
     * Enqueue JS file in admin panel
     * @param string $path Path relative to assets directory (e.g., "js/admin.js")
     * @param int $priority Hook priority (default: 10)
     */
    protected function enqueueAdminScript($path, $priority = 10) {
        $url = $this->asset($path);
        $this->hook('admin.footer', function($content) use ($url) {
            return $content . "\n    <script src=\"" . e($url) . "\"></script>";
        }, $priority);
    }

    /**
     * Add inline CSS to admin panel
     * @param string $css CSS code
     * @param int $priority Hook priority (default: 10)
     */
    protected function addAdminInlineStyle($css, $priority = 10) {
        $this->hook('admin.head', function($content) use ($css) {
            return $content . "\n    <style>\n" . $css . "\n    </style>";
        }, $priority);
    }

    /**
     * Add inline JS to admin panel
     * @param string $js JavaScript code
     * @param int $priority Hook priority (default: 10)
     */
    protected function addAdminInlineScript($js, $priority = 10) {
        $this->hook('admin.footer', function($content) use ($js) {
            return $content . "\n    <script>\n" . $js . "\n    </script>";
        }, $priority);
    }

    /**
     * Enqueue CSS file in public theme
     * @param string $path Path relative to assets directory (e.g., "css/style.css")
     * @param int $priority Hook priority (default: 10)
     */
    protected function enqueueStyle($path, $priority = 10) {
        $url = $this->asset($path);
        $this->hook('theme.head', function($content) use ($url) {
            return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
        }, $priority);
    }

    /**
     * Enqueue JS file in public theme
     * @param string $path Path relative to assets directory (e.g., "js/script.js")
     * @param int $priority Hook priority (default: 10)
     */
    protected function enqueueScript($path, $priority = 10) {
        $url = $this->asset($path);
        $this->hook('theme.footer', function($content) use ($url) {
            return $content . "\n    <script src=\"" . e($url) . "\"></script>";
        }, $priority);
    }

    /**
     * Add inline CSS to public theme
     * @param string $css CSS code
     * @param int $priority Hook priority (default: 10)
     */
    protected function addInlineStyle($css, $priority = 10) {
        $this->hook('theme.head', function($content) use ($css) {
            return $content . "\n    <style>\n" . $css . "\n    </style>";
        }, $priority);
    }

    /**
     * Add inline JS to public theme
     * @param string $js JavaScript code
     * @param int $priority Hook priority (default: 10)
     */
    protected function addInlineScript($js, $priority = 10) {
        $this->hook('theme.footer', function($content) use ($js) {
            return $content . "\n    <script>\n" . $js . "\n    </script>";
        }, $priority);
    }

}
