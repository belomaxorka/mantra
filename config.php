<?php
/**
 * Mantra CMS Configuration
 */

return array(
    // General settings
    'site_name' => 'Mantra CMS',
    'site_url' => 'http://localhost',
    'timezone' => 'UTC',
    'default_language' => 'en',
    
    // Debug mode
    'debug' => true,
    
    // Cache settings
    'cache_enabled' => true,
    'cache_lifetime' => 3600, // seconds
    
    // Session settings
    'session_name' => 'mantra_session',
    'session_lifetime' => 7200,
    
    // Security
    'password_hash_algo' => PASSWORD_DEFAULT,
    'csrf_token_name' => 'mantra_csrf',
    
    // Content settings
    'content_format' => 'json', // json, yaml, md
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
