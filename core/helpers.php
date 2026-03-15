<?php
/**
 * Helper functions - Global utility functions
 */

/**
 * Get application instance
 */
function app() {
    return Application::getInstance();
}

/**
 * Get database instance
 */
function db() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}

/**
 * Get cache instance
 */
function cache() {
    static $cache = null;
    if ($cache === null) {
        $cache = new Cache();
    }
    return $cache;
}

/**
 * Get auth instance
 */
function auth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

/**
 * Get logger instance
 */
function logger($channel = 'app') {
    static $loggers = array();
    if (!isset($loggers[$channel])) {
        $minLevel = (defined('MANTRA_DEBUG') && MANTRA_DEBUG) ? Logger::DEBUG : Logger::INFO;

        // Prefer early-loaded config (no Application dependency).
        if (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) {
            if (!empty($GLOBALS['MANTRA_CONFIG']['log_level'])) {
                $minLevel = $GLOBALS['MANTRA_CONFIG']['log_level'];
            }
        }

        $loggers[$channel] = new Logger($channel, array(
            'minLevel' => $minLevel
        ));
    }
    return $loggers[$channel];
}

/**
 * Quick log helper
 */
function log_message($level, $message, $context = array()) {
    return logger()->log($level, $message, $context);
}

/**
 * Debug log helper (only in debug mode)
 */
function log_debug($message, $context = array()) {
    if (defined('MANTRA_DEBUG') && MANTRA_DEBUG) {
        return logger()->debug($message, $context);
    }
    return false;
}

/**
 * Redirect helper
 */
function redirect($url, $code = 302) {
    header('Location: ' . $url, true, $code);
    exit;
}

/**
 * JSON response helper
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get config instance or value
 */
function config($key = null, $default = null) {
    static $config = null;
    if ($config === null) {
        $config = new Config();
    }
    
    if ($key === null) {
        return $config;
    }
    
    return $config->get($key, $default);
}

/**
 * Get base URL
 */
function base_url($path = '') {
    $siteUrl = config('site_url');
    if (!$siteUrl) {
        $app = Application::getInstance();
        $siteUrl = $app->config('site_url');
    }

    // Normalize both forward and back slashes to avoid URLs like "//admin" or "\\admin".
    return rtrim($siteUrl, "/\\") . '/' . ltrim($path, "/\\");
}

/**
 * Sanitize string for output (XSS protection)
 */
function sanitize($value) {
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output (alias for sanitize)
 */
function e($value) {
    return sanitize($value);
}

/**
 * Generate slug from string
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    return empty($text) ? 'n-a' : $text;
}

/**
 * Debug dump
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    exit;
}
