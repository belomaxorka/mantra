<?php
/**
 * Mantra CMS - Flat-File Content Management System
 * Entry Point
 * 
 * PHP 5.5+ required
 */

// Check PHP version requirement
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
    die('Mantra CMS requires PHP 5.5.0 or higher. Your version: ' . PHP_VERSION);
}

// Define base paths
define('MANTRA_ROOT', __DIR__);
define('MANTRA_CORE', MANTRA_ROOT . '/core');
define('MANTRA_MODULES', MANTRA_ROOT . '/modules');
define('MANTRA_CONTENT', MANTRA_ROOT . '/content');
define('MANTRA_STORAGE', MANTRA_ROOT . '/storage');
define('MANTRA_THEMES', MANTRA_ROOT . '/themes');
define('MANTRA_UPLOADS', MANTRA_ROOT . '/uploads');

// Check if system is installed
$isInstalled = file_exists(MANTRA_CONTENT . '/users') && 
               count(glob(MANTRA_CONTENT . '/users/*.json')) > 0;

// Redirect to installer if not installed
if (!$isInstalled) {
    $currentUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($currentUri, 'install.php') === false) {
        header('Location: install.php');
        exit;
    }
}

// Load configuration (single source of truth: content/settings/config.json)
require_once MANTRA_CORE . '/Config.php';
$config = Config::bootstrap();
$GLOBALS['MANTRA_CONFIG'] = $config;

// Define debug constant as early as possible (used by logger/error handler)
if (!defined('MANTRA_DEBUG')) {
    define('MANTRA_DEBUG', !empty($config['debug']));
}

// Load vendored PSR-3 interfaces (no Composer)
require_once MANTRA_CORE . '/Psr/Log/LoggerInterface.php';
require_once MANTRA_CORE . '/Psr/Log/LogLevel.php';

// Load helper functions
require_once MANTRA_CORE . '/helpers.php';

// Autoloader for core classes
spl_autoload_register(function($class) {
    $relative = str_replace("\0", '', $class);
    $relative = str_replace('\\', '/', $relative);
    $coreFile = MANTRA_CORE . '/' . $relative . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
    }
});

// Register centralized PHP error handling (logs to channel "php")
ErrorHandler::register(new Logger('php', array(
    'minLevel' => MANTRA_DEBUG ? Logger::DEBUG : Logger::NOTICE
)));

// Initialize application
$app = Application::getInstance();
$app->run();
