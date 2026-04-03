<?php declare(strict_types=1);
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
    static $loggers = [];
    if (!isset($loggers[$channel])) {
        $loggers[$channel] = new Logger($channel, [
            'minLevel' => Logger::resolveLevel(),
        ]);
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
function t($key, $params = [])
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
function partial($name, $params = [])
{
    return app()->view()->partial($name, $params);
}

/**
 * Abort with HTTP error page.
 */
function abort($code = 404, $message = ''): void
{
    http_response_code($code);

    $titles = [
        403 => 'Forbidden',
        404 => 'Page Not Found',
        500 => 'Internal Server Error',
    ];
    $title = $titles[$code] ?? 'Error';

    try {
        app()->view()->render((string)$code, [
            'title' => $code . ' - ' . $title,
            'code' => $code,
            'message' => $message,
        ]);
    } catch (Exception $e) {
        echo '<h1>' . $code . ' - ' . htmlspecialchars($title) . '</h1>';
        if ($message !== '') {
            echo '<p>' . htmlspecialchars($message) . '</p>';
        }
    }
}

// ── Utilities ───────────────────────────────────────────────────────────────

/**
 * Generate slug from string.
 */
function slugify($text)
{
    $cyrillic = [
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
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
    ];

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
