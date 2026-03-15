<?php
/**
 * Mantra CMS Configuration Bootstrap
 * This file loads configuration from content/settings/config.json
 */

// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . '://' . $host . ($scriptPath !== '/' ? $scriptPath : '');

// Default configuration
$defaultConfig = array(
    // General settings
    'site_name' => 'Mantra CMS',
    'site_url' => $baseUrl,
    'timezone' => 'UTC',
    'default_language' => 'en',
    
    // Debug mode
    'debug' => true,
    
    // Logging settings
    'log_level' => 'debug', // emergency, alert, critical, error, warning, notice, info, debug
    'log_retention_days' => 30, // Auto-delete logs older than X days
    
    // Cache settings
    'cache_enabled' => true,
    'cache_lifetime' => 3600,
    
    // Session settings
    'session_name' => 'mantra_session',
    'session_lifetime' => 7200,
    
    // Security
    'password_hash_algo' => PASSWORD_DEFAULT,
    'csrf_token_name' => 'mantra_csrf',
    
    // Content settings
    'content_format' => 'json',
    'posts_per_page' => 10,
    
    // Theme
    'active_theme' => 'default',
    
    // Modules - enabled modules list
    'enabled_modules' => array(
        'admin',
        'pages',
        'media',
        'users',
        'editor'
    )
);

// Load configuration from JSON file if exists
$configFile = __DIR__ . '/content/settings/config.json';
if (file_exists($configFile)) {
    $jsonConfig = json_decode(file_get_contents($configFile), true);
    if ($jsonConfig) {
        $defaultConfig = array_merge($defaultConfig, $jsonConfig);
    }
}

return $defaultConfig;
