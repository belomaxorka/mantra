<?php
/**
 * Application - Main application singleton
 * Orchestrates the entire CMS lifecycle
 */

class Application {
    private static $instance = null;
    private $config = array();
    private $router = null;
    private $moduleManager = null;
    private $hookManager = null;
    
    private function __construct() {
        $this->loadConfig();
        $this->setupEnvironment();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $config = require MANTRA_ROOT . '/config.php';
        $this->config = $config;
        
        // Define debug constant
        define('MANTRA_DEBUG', isset($config['debug']) ? $config['debug'] : false);
    }
    
    /**
     * Setup environment
     */
    private function setupEnvironment() {
        // Set timezone
        if (isset($this->config['timezone'])) {
            date_default_timezone_set($this->config['timezone']);
        }
        
        // Error reporting
        if (MANTRA_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        
        // Start session
        if (session_status() == PHP_SESSION_NONE) {
            session_name($this->config['session_name']);
            session_start();
        }
    }
    
    /**
     * Run application
     */
    public function run() {
        try {
            // Initialize hook manager first (modules will register hooks)
            $this->hookManager = new HookManager();
            
            // Initialize module manager
            $this->moduleManager = new ModuleManager($this->config);
            $this->moduleManager->loadModules();
            
            // Fire init hook
            $this->hookManager->fire('system.init');
            
            // Initialize router
            $this->router = new Router();
            
            // Let modules register routes
            $this->hookManager->fire('routes.register', array('router' => $this->router));
            
            // Dispatch request
            $this->router->dispatch();
            
            // Fire shutdown hook
            $this->hookManager->fire('system.shutdown');
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Handle application errors
     */
    private function handleError($exception) {
        // Log error
        $this->logError($exception);
        
        // Show error page
        http_response_code(500);
        
        if (MANTRA_DEBUG) {
            echo '<h1>Error</h1>';
            echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            echo '<h1>Something went wrong</h1>';
            echo '<p>Please try again later.</p>';
        }
    }
    
    /**
     * Log error to file
     */
    private function logError($exception) {
        $logDir = MANTRA_STORAGE . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
        $message = sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        file_put_contents($logFile, $message, FILE_APPEND);
    }
    
    /**
     * Get configuration value
     */
    public function config($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    
    /**
     * Get router instance
     */
    public function router() {
        return $this->router;
    }
    
    /**
     * Get module manager instance
     */
    public function modules() {
        return $this->moduleManager;
    }
    
    /**
     * Get hook manager instance
     */
    public function hooks() {
        return $this->hookManager;
    }
}
