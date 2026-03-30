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

// Project information
if (!defined('MANTRA_PROJECT_INFO')) {
    define('MANTRA_PROJECT_INFO', array(
        'name' => 'Mantra CMS',
        'version' => '1.1.0',
        'github' => 'https://github.com/belomaxorka/mantra',
        'release_date' => '2026-03-17'
    ));
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
    define('MANTRA_UPLOADS', MANTRA_STORAGE . '/uploads');
}

// Load logger classes BEFORE autoloader
require_once MANTRA_CORE . '/classes/Psr/Log/LoggerInterface.php';
require_once MANTRA_CORE . '/classes/Psr/Log/LogLevel.php';

// Load config classes BEFORE autoloader
require_once MANTRA_CORE . '/classes/Storage/FileIO.php';
require_once MANTRA_CORE . '/classes/JsonCodec.php';
require_once MANTRA_CORE . '/classes/Config.php';
$config = Config::bootstrap();
$GLOBALS['MANTRA_CONFIG'] = $config;

if (!defined('MANTRA_DEBUG')) {
    define('MANTRA_DEBUG', !empty(Config::getNested($config, 'debug.enabled', false)));
}

// PSR-4 autoloader for core classes (namespace maps to directory)
spl_autoload_register(function ($class) {
    $relative = str_replace("\0", '', $class);
    $relative = str_replace('\\', '/', $relative);

    if (strpos($relative, '..') !== false) {
        return;
    }

    $path = MANTRA_CORE . '/classes/' . $relative . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// Load helper functions from core/helpers/ directory
$helpersDir = MANTRA_CORE . '/helpers';
if (is_dir($helpersDir)) {
    $helperFiles = glob($helpersDir . '/*.php');
    if ($helperFiles !== false) {
        sort($helperFiles);
        foreach ($helperFiles as $helperFile) {
            require_once $helperFile;
        }
    }
}

// Register centralized PHP error handling (logs to channel "php")
ErrorHandler::register(new Logger('php', array(
    'minLevel' => resolve_log_level()
)));
