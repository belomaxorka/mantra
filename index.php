<?php declare(strict_types=1);
/**
 * Mantra CMS - Flat-File Content Management System
 * Entry Point
 */

require_once __DIR__ . '/core/bootstrap.php';

\Http\SecurityHeadersMiddleware::apply((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Redirect to installer if not installed
if (!InstallationState::isInstalled()) {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    if (!str_contains($currentUri, 'install.php')) {
        header('Location: ' . base_url('/install.php'), true, 302);
        exit;
    }
}

// Initialize application
$app = Application::getInstance();
$app->run();
