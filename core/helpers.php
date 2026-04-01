<?php
/**
 * Mantra CMS - Global Helpers
 *
 * Minimal set of global functions for service access, templates, and utilities.
 * Everything else lives in Application services or class methods.
 */

// ── Service access ──────────────────────────────────────────────────────────

/**
 * Get Application singleton.
 */
function app()
{
    return Application::getInstance();
}

/**
 * Get config instance or value.
 * Works before Application exists (creates its own Config instance).
 */
function config($key = null, $default = null)
{
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
 * Get logger instance by channel (cached per channel).
 */
function logger($channel = 'app')
{
    static $loggers = array();
    if (!isset($loggers[$channel])) {
        $loggers[$channel] = new Logger($channel, array(
            'minLevel' => Logger::resolveLevel()
        ));
    }
    return $loggers[$channel];
}

/**
 * Get Clock service instance.
 *
 * @return Clock
 */
function clock()
{
    return app()->service('clock');
}

// ── Template helpers ────────────────────────────────────────────────────────

/**
 * Escape output (XSS protection).
 */
function e($value)
{
    return sanitize($value);
}

/**
 * Sanitize string for output.
 */
function sanitize($value)
{
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Translation shorthand.
 */
function t($key, $params = array())
{
    return app()->translator()->translate($key, $params);
}

/**
 * Build URL relative to site base.
 */
function base_url($path = '')
{
    $siteUrl = config('site.url', '');

    if ($siteUrl === '') {
        return '/' . ltrim($path, "/\\");
    }
    return rtrim($siteUrl, "/\\") . '/' . ltrim($path, "/\\");
}

/**
 * Render a template partial (without layout wrapping).
 */
function partial($name, $params = array())
{
    return app()->view()->partial($name, $params);
}

/**
 * Abort with HTTP error page.
 */
function abort($code = 404, $message = '')
{
    http_response_code($code);

    $titles = array(
        403 => 'Forbidden',
        404 => 'Page Not Found',
        500 => 'Internal Server Error',
    );
    $title = isset($titles[$code]) ? $titles[$code] : 'Error';

    try {
        app()->view()->render((string)$code, array(
            'title' => $code . ' - ' . $title,
            'code' => $code,
            'message' => $message,
        ));
    } catch (Exception $e) {
        echo '<h1>' . $code . ' - ' . htmlspecialchars($title) . '</h1>';
        if ($message !== '') {
            echo '<p>' . htmlspecialchars($message) . '</p>';
        }
    }
}

// ── Utilities ───────────────────────────────────────────────────────────────

/**
 * Standard datetime format constant.
 */
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

/**
 * Get current datetime in standard format.
 */
function now()
{
    return date(DATETIME_FORMAT);
}

/**
 * Format datetime for display using the configured date format.
 *
 * @param string|\DateTimeInterface|int $datetime
 * @return string
 */
function format_date($datetime)
{
    return clock()->formatDate($datetime);
}

/**
 * Format datetime for display using the configured time format.
 *
 * @param string|\DateTimeInterface|int $datetime
 * @return string
 */
function format_time($datetime)
{
    return clock()->formatTime($datetime);
}

/**
 * Format datetime for display using date + time formats.
 *
 * @param string|\DateTimeInterface|int $datetime
 * @return string
 */
function format_datetime($datetime)
{
    return clock()->formatDatetime($datetime);
}

/**
 * Human-readable relative time ("5 min. ago", "2 hr. ago").
 *
 * @param string|\DateTimeInterface|int $datetime
 * @return string
 */
function time_ago($datetime)
{
    return clock()->ago($datetime);
}

/**
 * Generate slug from string.
 */
function slugify($text)
{
    $cyrillic = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
        'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    );

    $text = strtr($text, $cyrillic);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}

// ── Polyfills ───────────────────────────────────────────────────────────────

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }
}
