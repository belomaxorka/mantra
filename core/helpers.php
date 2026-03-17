<?php
/**
 * Helper functions - Global utility functions
 */

/**
 * Get application instance
 */
function app()
{
    return Application::getInstance();
}

/**
 * Get database instance
 */
function db()
{
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}

/**
 * Get auth instance
 */
function auth()
{
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

/**
 * Get logger instance
 */
function logger($channel = 'app')
{
    static $loggers = array();
    if (!isset($loggers[$channel])) {
        $minLevel = (defined('MANTRA_DEBUG') && MANTRA_DEBUG) ? Logger::DEBUG : Logger::INFO;

        // Prefer early-loaded config (no Application dependency).
        if (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) {
            $level = Config::getNested($GLOBALS['MANTRA_CONFIG'], 'logging.level', null);
            if (!empty($level)) {
                $minLevel = $level;
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
function log_message($level, $message, $context = array())
{
    return logger()->log($level, $message, $context);
}

/**
 * Debug log helper (only in debug mode)
 */
function log_debug($message, $context = array())
{
    if (defined('MANTRA_DEBUG') && MANTRA_DEBUG) {
        return logger()->debug($message, $context);
    }
    return false;
}

/**
 * Get session wrapper instance
 */
function session()
{
    static $session = null;
    if ($session === null) {
        $session = new Http\Session();
    }
    return $session;
}

/**
 * Get cookie wrapper instance
 */
function cookie()
{
    static $cookie = null;
    if ($cookie === null) {
        $cookie = new Http\Cookie();
    }
    return $cookie;
}

/**
 * Get response wrapper instance
 */
function response()
{
    static $response = null;
    if ($response === null) {
        $response = new Http\Response();
    }
    return $response;
}

/**
 * Get request wrapper instance
 */
function request()
{
    static $request = null;
    if ($request === null) {
        $request = new Http\Request();
    }
    return $request;
}

/**
 * Redirect helper
 */
function redirect($url, $code = 302)
{
    response()->redirect($url, $code);
}

/**
 * JSON response helper
 */
function json_response($data, $code = 200)
{
    response()->json($data, $code);
}

/**
 * Determine whether the current request is HTTPS.
 *
 * Uses request headers first (HTTPS / X-Forwarded-Proto), with a fallback to site.url scheme.
 */
function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') {
            return true;
        }
    }

    $siteUrl = config('site.url');
    if ($siteUrl) {
        $scheme = parse_url($siteUrl, PHP_URL_SCHEME);
        return strtolower((string)$scheme) === 'https';
    }

    return false;
}

/**
 * Get client IP address.
 *
 * By default returns REMOTE_ADDR.
 *
 * If config('proxy.trusted_proxies') is set and the current REMOTE_ADDR matches one of them
 * (IP or CIDR), this function will also consider common proxy/CDN headers.
 */
function client_ip()
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!$remoteAddr || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return null;
    }

    $trusted = config('proxy.trusted_proxies', array());
    if (is_string($trusted)) {
        $trusted = array_filter(array_map('trim', explode(',', $trusted)), 'strlen');
    }
    if (!is_array($trusted)) {
        $trusted = array();
    }

    if (empty($trusted) || !ip_matches_any($remoteAddr, $trusted)) {
        return $remoteAddr;
    }

    $candidates = array(
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_FASTLY_CLIENT_IP'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
    );

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $candidates[] = $part;
            }
        }
    }

    foreach ($candidates as $ip) {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        // Prefer public IPs when present.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
    }

    foreach ($candidates as $ip) {
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $remoteAddr;
}

/**
 * Check whether an IP matches any entry in a list of IPs/CIDRs.
 */
function ip_matches_any($ip, $entries)
{
    foreach ($entries as $entry) {
        if (ip_matches($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/**
 * Check whether an IP matches a single entry (IP or CIDR).
 */
function ip_matches($ip, $entry)
{
    $entry = trim((string)$entry);
    if ($entry === '') {
        return false;
    }

    if (strpos($entry, '/') === false) {
        return $ip === $entry;
    }

    list($subnet, $bits) = array_pad(explode('/', $entry, 2), 2, null);
    if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
        return false;
    }

    $bits = (int)$bits;
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0) {
        $bits = 0;
    }
    if ($bits > $maxBits) {
        $bits = $maxBits;
    }

    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }

    if ($remainder === 0) {
        return true;
    }

    $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
    return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
}

/**
 * Get config instance or value
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
 * Resolve localized value (string or array with locale keys)
 *
 * @param mixed $value String or array with locale keys (e.g., ['en' => 'Hello', 'ru' => 'Привет'])
 * @param string|null $locale Locale to use (defaults to current locale)
 * @return string Resolved localized string
 */
function resolve_localized($value, $locale = null)
{
    if (is_string($value)) {
        return $value;
    }

    if (!is_array($value)) {
        return '';
    }

    if ($locale === null) {
        $locale = config()->get('locale.default_language', 'en');
    }

    // Try requested locale
    if (isset($value[$locale])) {
        return (string)$value[$locale];
    }

    // Fallback to English
    if (isset($value['en'])) {
        return (string)$value['en'];
    }

    // Fallback to first available value
    $first = reset($value);
    return is_string($first) ? $first : '';
}

/**
 * Get module settings instance or a specific value.
 */
function module_settings($module, $key = null, $default = null)
{
    static $stores = array();

    $module = (string)$module;
    if (!isset($stores[$module])) {
        $stores[$module] = new ModuleSettings($module);
    }

    if ($key === null) {
        return $stores[$module];
    }

    return $stores[$module]->get($key, $default);
}

/**
 * Get config settings store (schema-driven admin settings for config.json).
 */
function config_settings()
{
    static $store = null;
    if ($store === null) {
        $store = new ConfigSettings();
    }
    return $store;
}

/**
 * Get base URL
 */
function base_url($path = '')
{
    $siteUrl = config('site.url');
    if (!$siteUrl) {
        $app = Application::getInstance();
        $siteUrl = $app->config('site.url');
    }

    if (!$siteUrl) {
        $siteUrl = '';
    }

    // Normalize both forward and back slashes to avoid URLs like "//admin" or "\\admin".
    if ($siteUrl === '') {
        return '/' . ltrim($path, "/\\");
    }
    return rtrim($siteUrl, "/\\") . '/' . ltrim($path, "/\\");
}

/**
 * Sanitize string for output (XSS protection)
 */
function sanitize($value)
{
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output (alias for sanitize)
 */
function e($value)
{
    return sanitize($value);
}

/**
 * Generate slug from string
 */
function slugify($text)
{
    // Transliteration map for Cyrillic characters
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

    // Transliterate Cyrillic
    $text = strtr($text, $cyrillic);

    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // Transliterate remaining characters
    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // Trim
    $text = trim($text, '-');

    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // Lowercase
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}

/**
 * Debug dump
 */
function dd($var)
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    exit;
}

/**
 * Translation helper
 * @param string $key Translation key
 * @param array $params Parameters for interpolation
 * @return string
 */
function t($key, $params = array())
{
    static $translator = null;
    if ($translator === null) {
        $translator = new TranslationManager();
    }
    return $translator->translate($key, $params);
}

/**
 * Render widget/component
 *
 * @param string $name Widget name (e.g., "sidebar", "module:widget")
 * @param array $params Parameters to pass to widget
 * @return string Rendered widget HTML
 */
function widget($name, $params = array())
{
    $view = new View();
    return $view->widget($name, $params);
}

/**
 * Get view instance or render template
 * @param string|null $template Template name
 * @param array $data Template data
 * @return View|string
 */
function view($template = null, $data = array())
{
    $view = new View();

    if ($template === null) {
        return $view;
    }

    return $view->render($template, $data);
}

/**
 * Get admin module instance
 * @return Module|null
 */
function admin()
{
    static $admin = null;
    if ($admin === null) {
        $admin = app()->modules()->getModule('admin');
    }
    return $admin;
}

/**
 * Verify CSRF token from POST request
 * @return bool
 */
function verify_csrf()
{
    if (request()->method() !== 'POST') {
        return true;
    }

    $token = request()->post('csrf_token', '');
    if (!auth()->verifyCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        return false;
    }
    return true;
}

/**
 * Get module instance
 * @param string $moduleId
 * @return Module|null
 */
function module($moduleId)
{
    return app()->modules()->getModule($moduleId);
}

/**
 * Get content type registry instance
 *
 * @return ContentTypeRegistry
 */
function content_types()
{
    return ContentTypeRegistry::getInstance();
}
