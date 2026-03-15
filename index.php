<?php
/**
 * Mantra CMS - Flat-File Content Management System
 * Entry Point
 */

require_once __DIR__ . '/core/bootstrap.php';

// Define MANTRA_ROOT for legacy code expecting it from index.php
// (bootstrap defines it as well, but keep it explicit for entrypoint clarity)
// Note: constants cannot be redefined, so this is safe.
if (!defined('MANTRA_ROOT')) {
    define('MANTRA_ROOT', __DIR__);
}

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

// bootstrap.php already loaded config/logger/helpers/autoload/error handler
// so entrypoint stays minimal here.



// Initialize application
$app = Application::getInstance();
$app->run();
