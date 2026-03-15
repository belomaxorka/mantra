<?php
/**
 * Module - Base class for all modules
 * Provides common functionality and API access
 */

abstract class Module {
    protected $manifest = array();
    protected $app = null;
    
    public function __construct($manifest) {
        $this->manifest = $manifest;
        $this->app = Application::getInstance();
    }
    
    /**
     * Initialize module - override in child classes
     */
    public function init() {
        // Override in child classes
    }
    
    /**
     * Get module name
     */
    public function getName() {
        return isset($this->manifest['name']) ? $this->manifest['name'] : '';
    }
    
    /**
     * Get module version
     */
    public function getVersion() {
        return isset($this->manifest['version']) ? $this->manifest['version'] : '1.0.0';
    }
    
    /**
     * Register a hook
     */
    protected function hook($hookName, $callback, $priority = 10) {
        $this->app->hooks()->register($hookName, $callback, $priority);
    }
    
    /**
     * Fire a hook
     */
    protected function fireHook($hookName, $data = null) {
        return $this->app->hooks()->fire($hookName, $data);
    }
    
    /**
     * Register a route
     */
    protected function route($method, $pattern, $callback) {
        $router = $this->app->router();
        if ($method === 'GET') {
            return $router->get($pattern, $callback);
        } elseif ($method === 'POST') {
            return $router->post($pattern, $callback);
        } else {
            return $router->any($pattern, $callback);
        }
    }
    
    /**
     * Get config value
     */
    protected function config($key, $default = null) {
        return $this->app->config($key, $default);
    }
    
    /**
     * Load view template
     */
    protected function view($template, $data = array()) {
        $view = new View();
        return $view->render($template, $data);
    }
    
    /**
     * Get module path
     */
    protected function getPath() {
        return MANTRA_MODULES . '/' . $this->getName();
    }
    
    /**
     * Load module file
     */
    protected function loadFile($filename) {
        $path = $this->getPath() . '/' . $filename;
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
        return false;
    }
}
