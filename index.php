<?php declare(strict_types=1);
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
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    if (!str_contains($currentUri, 'install.php')) {
        header('Location: ' . Config::detectBaseUrl() . '/install.php', true, 302);
        exit;
    }
}

// Initialize application
$app = Application::getInstance();
$app->run();
