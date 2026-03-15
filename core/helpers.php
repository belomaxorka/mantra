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
 * Get base URL
 */
function base_url($path = '') {
    $app = Application::getInstance();
    return rtrim($app->config('site_url'), '/') . '/' . ltrim($path, '/');
}

/**
 * Sanitize string
 */
function sanitize($value) {
    return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
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
