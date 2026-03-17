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
        'version' => '1.0.0',
        'github' => 'https://github.com/yourusername/mantra-cms',
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

// Load vendored PSR-3 interfaces (no Composer) - in subfolder, autoloader won't find them
require_once MANTRA_CORE . '/classes/Psr/Log/LoggerInterface.php';
require_once MANTRA_CORE . '/classes/Psr/Log/LogLevel.php';

// Load config classes BEFORE autoloader (needed to read config and set up debug mode)
require_once MANTRA_CORE . '/classes/JsonFile.php';
require_once MANTRA_CORE . '/classes/Config.php';
$config = Config::bootstrap();
$GLOBALS['MANTRA_CONFIG'] = $config;

if (!defined('MANTRA_DEBUG')) {
    define('MANTRA_DEBUG', !empty(Config::getNested($config, 'debug.enabled', false)));
}

// Autoloader for core classes (Logger/ErrorHandler/Application/Module/etc.)
spl_autoload_register(function ($class) {
    $relative = str_replace("\0", '', $class);
    $relative = str_replace('\\', '/', $relative);

    // Try core/classes/ with full path (handles Http\Request, etc.)
    $classFile = MANTRA_CORE . '/classes/' . $relative . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return;
    }

    // Try core/classes/Module/ for module-related classes
    $moduleFile = MANTRA_CORE . '/classes/Module/' . $relative . '.php';
    if (file_exists($moduleFile)) {
        require_once $moduleFile;
        return;
    }

    // Try core/classes/Storage/ for storage driver classes
    $storageFile = MANTRA_CORE . '/classes/Storage/' . $relative . '.php';
    if (file_exists($storageFile)) {
        require_once $storageFile;
        return;
    }

    // Try direct in core/classes/ (for classes without namespace-like structure)
    $directFile = MANTRA_CORE . '/classes/' . basename($relative) . '.php';
    if (file_exists($directFile)) {
        require_once $directFile;
        return;
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
    'minLevel' => MANTRA_DEBUG ? Logger::DEBUG : Logger::NOTICE
)));
