<?php
/**
 * Mantra CMS - Bootstrap
 *
 * Shared initialization for all entry points (web, install, CLI, etc.).
 */

// Check PHP version requirement
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
    die('Mantra CMS requires PHP 5.5.0 or higher. Your version: ' . PHP_VERSION);
}

// Detect CLI mode
if (!defined('MANTRA_CLI')) {
    define('MANTRA_CLI', (PHP_SAPI === 'cli'));
}

// Define base paths (allow entrypoints to predefine if needed)
if (!defined('MANTRA_ROOT')) {
    define('MANTRA_ROOT', dirname(__DIR__));
}
if (!defined('MANTRA_CORE')) {
    define('MANTRA_CORE', MANTRA_ROOT . '/core');
}
if (!defined('MANTRA_MODULES')) {
    define('MANTRA_MODULES', MANTRA_ROOT . '/modules');
}
if (!defined('MANTRA_CONTENT')) {
    define('MANTRA_CONTENT', MANTRA_ROOT . '/content');
}
if (!defined('MANTRA_STORAGE')) {
    define('MANTRA_STORAGE', MANTRA_ROOT . '/storage');
}
if (!defined('MANTRA_THEMES')) {
    define('MANTRA_THEMES', MANTRA_ROOT . '/themes');
}
if (!defined('MANTRA_UPLOADS')) {
    define('MANTRA_UPLOADS', MANTRA_ROOT . '/uploads');
}

// Load vendored PSR-3 interfaces (no Composer)
require_once MANTRA_CORE . '/Psr/Log/LoggerInterface.php';
require_once MANTRA_CORE . '/Psr/Log/LogLevel.php';

// Load module system core classes
require_once MANTRA_CORE . '/ModuleInterface.php';
require_once MANTRA_CORE . '/ModuleType.php';
require_once MANTRA_CORE . '/ModuleCapability.php';
require_once MANTRA_CORE . '/AdminModule.php';
require_once MANTRA_CORE . '/ContentAdminModule.php';

// Load config and compute debug mode as early as possible
require_once MANTRA_CORE . '/JsonFile.php';
require_once MANTRA_CORE . '/Config.php';
$config = Config::bootstrap();
$GLOBALS['MANTRA_CONFIG'] = $config;

if (!defined('MANTRA_DEBUG')) {
    define('MANTRA_DEBUG', !empty(Config::getNested($config, 'debug.enabled', false)));
}

// Autoloader for core classes (Logger/ErrorHandler/Application/etc.)
spl_autoload_register(function($class) {
    $relative = str_replace("\0", '', $class);
    $relative = str_replace('\\', '/', $relative);
    $coreFile = MANTRA_CORE . '/' . $relative . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
    }
});

// Load helper functions (logger(), config(), base_url(), ...)
require_once MANTRA_CORE . '/helpers.php';

// Register centralized PHP error handling (logs to channel "php")
ErrorHandler::register(new Logger('php', array(
    'minLevel' => MANTRA_DEBUG ? Logger::DEBUG : Logger::NOTICE
)));
