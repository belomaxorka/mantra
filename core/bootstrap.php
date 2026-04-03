<?php declare(strict_types=1);
/**
 * Mantra CMS - Bootstrap
 *
 * Shared initialization for all entry points (web, install, CLI, etc.).
 */

// Check PHP version requirement
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('Mantra CMS requires PHP 8.1.0 or higher. Your version: ' . PHP_VERSION);
}

// Project information
define('MANTRA_PROJECT_INFO', [
    'name' => 'Mantra CMS',
    'version' => '1.1.0',
    'github' => 'https://github.com/belomaxorka/mantra',
    'release_date' => '2026-04-03',
]);

// Detect CLI mode
define('MANTRA_CLI', (PHP_SAPI === 'cli'));

// Define base paths
define('MANTRA_ROOT', dirname(__DIR__));
define('MANTRA_CORE', MANTRA_ROOT . '/core');
define('MANTRA_MODULES', MANTRA_ROOT . '/modules');
define('MANTRA_CONTENT', MANTRA_ROOT . '/content');
define('MANTRA_STORAGE', MANTRA_ROOT . '/storage');
define('MANTRA_THEMES', MANTRA_ROOT . '/themes');
define('MANTRA_UPLOADS', MANTRA_ROOT . '/uploads');

// Load logger classes BEFORE autoloader
require_once MANTRA_CORE . '/classes/Psr/Log/LoggerInterface.php';
require_once MANTRA_CORE . '/classes/Psr/Log/LogLevel.php';

// Load config classes BEFORE autoloader
require_once MANTRA_CORE . '/classes/Storage/FileIO.php';
require_once MANTRA_CORE . '/classes/JsonCodec.php';
require_once MANTRA_CORE . '/classes/Config.php';
$config = Config::bootstrap();
$GLOBALS['MANTRA_CONFIG'] = $config;

define('MANTRA_DEBUG', !empty(Config::getNested($config, 'debug.enabled', false)));

// PSR-4 autoloader for core classes (namespace maps to directory)
spl_autoload_register(function ($class): void {
    $relative = str_replace("\0", '', $class);
    $relative = str_replace('\\', '/', $relative);

    if (str_contains($relative, '..')) {
        return;
    }

    $path = MANTRA_CORE . '/classes/' . $relative . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// Load global helper functions
require_once MANTRA_CORE . '/helpers.php';

// Register centralized PHP error handling (logs to channel "php")
ErrorHandler::register(new Logger('php', [
    'minLevel' => Logger::resolveLevel(),
]));
