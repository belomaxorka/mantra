<?php declare(strict_types=1);

/**
 * Config - Immutable defaults and nested-array utilities
 *
 * Persistence and mutable state belong exclusively to ConfigRepository.
 */

class Config
{
    /**
     * Build full configuration array (defaults + JSON overrides).
     * Intended for early bootstrap (index.php) before Application exists.
     */
    public static function bootstrap($configPath = null)
    {
        $path = $configPath ? $configPath : (MANTRA_CONTENT . '/settings/config.json');
        try {
            // Bootstrap must not write before installation state is checked.
            return (new ConfigRepository($path, null, false))->all();
        } catch (Throwable $e) {
            error_log('Failed to bootstrap config.json: ' . $e->getMessage());
            return self::defaults();
        }
    }

    /**
     * Normalize script path for cross-platform URL compatibility.
     * Converts backslashes to forward slashes (Windows compatibility).
     *
     * @param string $path Script path from dirname($_SERVER['SCRIPT_NAME'])
     * @return string Normalized path with forward slashes
     */
    public static function normalizeScriptPath($path)
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Default configuration (nested).
     */
    public static function defaults()
    {
        return [
            'site' => [
                'name' => 'Mantra CMS',
                // Relative URLs are the safe default. An absolute public URL may
                // be configured explicitly, but must never be persisted from an
                // untrusted Host header during installation.
                'url' => '',
            ],
            'locale' => [
                'timezone' => 'UTC',
                'date_format' => 'j F Y',
                'time_format' => 'H:i',
                'default_language' => 'en',
                'fallback_locale' => 'en',
            ],
            'theme' => [
                'active' => 'default',
            ],
            'content' => [
                'format' => 'json',
                'posts_per_page' => 10,
                'compact_json' => false,
                'revision_limit' => 20,
            ],
            'modules' => [
                'enabled' => ['admin'],
            ],
            'security' => [
                // Stored as string identifier; interpreted by Auth when hashing.
                'password_hash_algo' => 'PASSWORD_DEFAULT',
            ],
            'session' => [
                'name' => 'mantra_session',
                'lifetime' => 7200,
                'cookie_secure' => 'auto',
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_path' => '/',
                'cookie_domain' => '',
            ],
            'logging' => [
                'level' => 'debug',
                'retention_days' => 30,
            ],
            'proxy' => [
                'trusted_proxies' => [],
            ],
            'performance' => [
                'gzip_compression' => false,
            ],
            'permissions' => [
                'roles' => [],
            ],
            'debug' => [
                'enabled' => false,
            ],
            'admin' => [
                'accent_color' => 'indigo',
                'sidebar_color' => 'dark',
                'font' => 'inter',
                'theme' => 'light',
            ],
            'advanced' => [
                // Placeholder to force JSON object encoding for empty group.
                '_placeholder' => null,
            ],
        ];
    }

    /**
     * Create install-time config (full defaults with specific overrides).
     */
    public static function buildInstallConfig($siteName, $language)
    {
        $config = self::defaults();
        self::setNested($config, 'site.name', $siteName);
        self::setNested($config, 'locale.default_language', $language);
        self::setNested($config, 'locale.fallback_locale', 'en');
        return $config;
    }

    /**
     * Resolve localized value (string or array with locale keys).
     */
    public static function resolveLocalized($value, $locale = null)
    {
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            return '';
        }
        if ($locale === null) {
            $locale = self::getNested($GLOBALS['MANTRA_CONFIG'], 'locale.default_language', 'en');
        }
        if (isset($value[$locale])) {
            return (string)$value[$locale];
        }
        if (isset($value['en'])) {
            return (string)$value['en'];
        }
        $first = reset($value);
        return is_string($first) ? $first : '';
    }

    /**
     * Detect the application path without trusting the HTTP Host header.
     * Used for same-origin redirects and relative URL generation.
     */
    public static function detectBasePath()
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $path = self::normalizeScriptPath(dirname($scriptName));
        if ($path === '' || $path === '.' || $path === '/') {
            return '';
        }

        return '/' . trim($path, '/');
    }

    /**
     * Deep merge two nested config arrays.
     */
    public static function deepMerge($base, $override)
    {
        if (!is_array($base)) {
            $base = [];
        }
        if (!is_array($override)) {
            return $base;
        }

        foreach ($override as $k => $v) {
            if (is_array($v) && array_key_exists($k, $base) && is_array($base[$k])) {
                $base[$k] = self::deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    /**
     * Flatten nested arrays into dot-path => value map.
     */
    public static function flattenPaths($nested, $prefix = '')
    {
        $out = [];
        if (!is_array($nested)) {
            return $out;
        }

        foreach ($nested as $k => $v) {
            $k = (string)$k;
            $path = $prefix === '' ? $k : ($prefix . '.' . $k);

            if (is_array($v) && self::isAssoc($v)) {
                $out = array_merge($out, self::flattenPaths($v, $path));
            } else {
                if ($path === 'advanced._placeholder') {
                    continue;
                }
                $out[$path] = $v;
            }
        }

        return $out;
    }

    public static function isAssoc($arr)
    {
        if (!is_array($arr)) {
            return false;
        }
        $keys = array_keys($arr);
        return array_keys($keys) !== $keys;
    }

    public static function getNested($arr, $path, $default = null)
    {
        if (!is_array($arr)) {
            return $default;
        }
        $path = trim((string)$path);
        if ($path === '') {
            return $default;
        }

        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $part) {
            if ($part === '') {
                return $default;
            }
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }

    public static function hasNested($arr, $path)
    {
        if (!is_array($arr)) {
            return false;
        }
        $path = trim((string)$path);
        if ($path === '') {
            return false;
        }

        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $part) {
            if ($part === '') {
                return false;
            }
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return false;
            }
            $cur = $cur[$part];
        }
        return true;
    }

    public static function setNested(&$arr, $path, $value): void
    {
        if (!is_array($arr)) {
            $arr = [];
        }

        $path = trim((string)$path);
        if ($path === '') {
            return;
        }

        $parts = explode('.', $path);
        $cur = &$arr;

        $last = array_pop($parts);
        foreach ($parts as $part) {
            if ($part === '') {
                return;
            }
            if (!isset($cur[$part]) || !is_array($cur[$part])) {
                $cur[$part] = [];
            }
            $cur = &$cur[$part];
        }

        if ($last === '') {
            return;
        }
        $cur[$last] = $value;
    }

    /**
     * Diff current config against defaults and return overrides-only structure.
     *
     * - For scalar/array mismatches: keep current value.
     * - For list arrays: treat as atomic.
     * - Only keys present in defaults are considered; unknown keys are ignored.
     */
    public static function diffOverrides($defaults, $current, $preserveUnknown = false)
    {
        if (!is_array($defaults) || !is_array($current)) {
            if ($defaults === $current) {
                return null;
            }
            return $current;
        }

        $isAssocDefaults = self::isAssoc($defaults);
        $isAssocCurrent = self::isAssoc($current);

        if (!$isAssocDefaults || !$isAssocCurrent) {
            if ($defaults === $current) {
                return null;
            }
            return $current;
        }

        $out = [];
        $hasAny = false;

        foreach ($defaults as $k => $defVal) {
            if (!array_key_exists($k, $current)) {
                continue;
            }

            $curVal = $current[$k];
            $child = self::diffOverrides($defVal, $curVal, $preserveUnknown);
            if ($child !== null) {
                $out[$k] = $child;
                $hasAny = true;
            }
        }

        if ($preserveUnknown) {
            foreach ($current as $k => $curVal) {
                if (!array_key_exists($k, $defaults)) {
                    $out[$k] = $curVal;
                    $hasAny = true;
                }
            }
        }

        return $hasAny ? $out : null;
    }
}
