<?php
/**
 * Mantra CMS - Flat-File Content Management System
 * Entry Point
 */

require_once __DIR__ . '/core/bootstrap.php';

// Check if system is installed
$isInstalled = file_exists(MANTRA_CONTENT . '/users') && 
               count(glob(MANTRA_CONTENT . '/users/*.json')) > 0;

// Redirect to installer if not installed
if (!$isInstalled) {
    $currentUri = request()->uri();
    if (strpos($currentUri, 'install.php') === false) {
        redirect('/install.php');
    }
}

// Initialize application
$app = Application::getInstance();
$app->run();
