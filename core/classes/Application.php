<?php
/**
 * Application - Main application singleton
 * Orchestrates the entire CMS lifecycle
 */

use Module\ModuleManager;

class Application {
    private static $instance = null;
    private $config = array();
    private $router = null;
    private $moduleManager = null;
    private $hookManager = null;
    private $services = array();
    
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
        // Prefer config loaded in index.php (avoids re-reading config file)
        if (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) {
            $config = $GLOBALS['MANTRA_CONFIG'];
        } else {
            // Fallback for non-standard entrypoints
            require_once MANTRA_CORE . '/Config.php';
            $config = Config::bootstrap();
        }
        $this->config = $config;
        
        // Define debug constant (may already be defined in index.php)
        if (!defined('MANTRA_DEBUG')) {
            define('MANTRA_DEBUG', !empty(Config::getNested($config, 'debug.enabled', false)));
        }
    }
    
    /**
     * Setup environment
     */
    private function setupEnvironment() {
        // Set timezone
        $tz = Config::getNested($this->config, 'locale.timezone', null);
        if (is_string($tz) && $tz !== '') {
            date_default_timezone_set($tz);
        }

        // Ensure MANTRA_DEBUG matches config as a single source of truth.
        $debug = Config::getNested($this->config, 'debug.enabled', false);
        if (!defined('MANTRA_DEBUG') || MANTRA_DEBUG !== (bool)$debug) {
            // Constants can't be redefined; only define if missing.
            if (!defined('MANTRA_DEBUG')) {
                define('MANTRA_DEBUG', (bool)$debug);
            }
        }
        
        // Error reporting
        // Variant C: collect everything to logs, but show details only in debug.
        error_reporting(E_ALL);
        if (MANTRA_DEBUG) {
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }
        
        // Start session
        session()->start();
    }
    
    /**
     * Run application
     */
    public function run() {
        try {
            logger()->info('Application started');

            // Clean old logs periodically (once per day)
            $this->cleanOldLogsIfNeeded();

            // Start output compression if enabled
            $this->startOutputCompression();

            // Initialize hook manager first (modules will register hooks)
            $this->hookManager = new HookManager();

            // Initialize module manager
            $this->moduleManager = new ModuleManager($this->config);
            $this->moduleManager->loadModules();

            logger()->debug('Modules loaded', array(
                'count' => count($this->moduleManager->getModules())
            ));

            // Fire init hook
            $this->hookManager->fire('system.init');

            // Initialize router
            $this->router = new Router();

            // Let modules register routes (specific routes like /admin/*)
            $this->hookManager->fire('routes.register', array('router' => $this->router));

            // Register core public routes (fallback for content pages)
            $this->registerCoreRoutes();

            // Dispatch request
            $this->router->dispatch();

            // Fire shutdown hook
            $this->hookManager->fire('system.shutdown');

            logger()->debug('Application finished');
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Register core public routes
     */
    private function registerCoreRoutes() {
        $controller = new PageController();

        // Home page
        $this->router->get('/', array($controller, 'home'));

        // Blog listing page
        $this->router->get('/blog', array($controller, 'blog'));

        // Single post (must be before catch-all page route)
        $this->router->get('/post/{slug}', array($controller, 'post'));

        // Single page (catch-all, registered last)
        $this->router->get('/{slug}', array($controller, 'page'));
    }

    /**
     * Clean old logs if needed (once per day)
     */
    private function cleanOldLogsIfNeeded() {
        $markerFile = MANTRA_STORAGE . '/logs/.last_cleanup';
        $now = time();

        // Check if cleanup was done today
        if (file_exists($markerFile)) {
            $lastCleanup = (int)file_get_contents($markerFile);
            if ($now - $lastCleanup < 86400) { // 24 hours
                return;
            }
        }

        // Perform cleanup
        $retentionDays = (int)$this->config('logging.retention_days', 30);
        $deleted = logger()->clearOldLogs($retentionDays);

        if ($deleted > 0) {
            logger()->info('Old logs cleaned', array('deleted' => $deleted, 'days' => $retentionDays));
        }

        // Update marker
        file_put_contents($markerFile, $now);
    }

    /**
     * Start output compression if enabled and supported
     *
     * Uses zlib.output_compression (SAPI-level) instead of ob_start('ob_gzhandler')
     * so it does not interfere with the View output buffering stack and handles
     * error output correctly without manual buffer cleanup.
     */
    private function startOutputCompression() {
        // Check if compression is enabled in config
        if (!(bool)$this->config('performance.gzip_compression', false)) {
            return;
        }

        // Check if already compressed by web server or php.ini
        if (headers_sent() || ini_get('zlib.output_compression')) {
            return;
        }

        // Check if zlib extension is available
        if (!extension_loaded('zlib')) {
            logger()->warning('gzip compression enabled but zlib extension not available');
            return;
        }

        // Enable SAPI-level compression (streamed, no extra ob_ layer)
        ini_set('zlib.output_compression', 'On');

        logger()->debug('Output compression started (zlib)');
    }
    
    /**
     * Handle application errors
     */
    private function handleError($exception) {
        // Log error
        logger()->error('Application error', array(
            'exception' => $exception,
            'url' => (string)request()->server('REQUEST_URI', 'unknown'),
            'method' => (string)request()->server('REQUEST_METHOD', 'unknown')
        ));

        // Discard any partial output left in ob buffers (e.g. from View)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

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
     * @deprecated Use logger() instead
     */
    private function logError($exception) {
        // Kept for backward compatibility, but now uses Logger class
        logger()->error('Application error', array('exception' => $exception));
    }
    
    /**
     * Get configuration value
     */
    public function config($path, $default = null) {
        return Config::getNested($this->config, (string)$path, $default);
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

    /**
     * Register a service that other modules can consume.
     *
     * @param string   $name     Service name (e.g. 'pages', 'search')
     * @param callable|mixed $provider  Callable (lazy) or a ready value
     */
    public function provide($name, $provider) {
        $this->services[$name] = array(
            'provider' => $provider,
            'resolved' => false,
            'value'    => null,
        );
    }

    /**
     * Resolve a previously registered service.
     *
     * Callable providers are invoked once; the result is cached.
     *
     * @param string $name    Service name
     * @param mixed  $default Returned when the service is not registered
     * @return mixed
     */
    public function service($name, $default = null) {
        if (!isset($this->services[$name])) {
            return $default;
        }

        $entry = &$this->services[$name];

        if (!$entry['resolved']) {
            $entry['value'] = is_callable($entry['provider'])
                ? call_user_func($entry['provider'])
                : $entry['provider'];
            $entry['resolved'] = true;
        }

        return $entry['value'];
    }

    /**
     * Check whether a service is registered.
     *
     * @param string $name
     * @return bool
     */
    public function hasService($name) {
        return isset($this->services[$name]);
    }
}
