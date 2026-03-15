<?php
/**
 * Mantra CMS - Flat-File Content Management System
 * Entry Point
 * 
 * PHP 5.6+ compatible
 */

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

// Load configuration
require_once MANTRA_ROOT . '/config.php';

// Load helper functions
require_once MANTRA_CORE . '/helpers.php';

// Autoloader for core classes
spl_autoload_register(function($class) {
    $coreFile = MANTRA_CORE . '/' . $class . '.php';
    if (file_exists($coreFile)) {
        require_once $coreFile;
    }
});

// Initialize application
try {
    $app = Application::getInstance();
    $app->run();
} catch (Exception $e) {
    // Simple error handling
    if (defined('MANTRA_DEBUG') && MANTRA_DEBUG) {
        echo '<h1>Error</h1><pre>' . $e->getMessage() . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1>';
    }
}
