<?php
/**
 * Config - Configuration management
 *
 * Single source of truth: content/settings/config.json
 *
 * - Provides defaults
 * - Auto-detects base URL (used as default site.url)
 * - Merges JSON over defaults
 */

use Storage\FileIO;

class Config {
    private $configPath = '';
    private $config = array();

    /**
     * Build full configuration array (defaults + JSON overrides).
     * Intended for early bootstrap (index.php) before Application exists.
     */
    public static function bootstrap($configPath = null) {
        $path = $configPath ? $configPath : (MANTRA_CONTENT . '/settings/config.json');

        $defaults = self::defaults();
        $json = array();

        if ($path && file_exists($path)) {
            try {
                $raw = FileIO::readLocked($path);
                $decoded = JsonCodec::decode($raw);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            } catch (Exception $e) {
                // Don't fail bootstrap on config JSON issues; ErrorHandler/logger may not be ready yet.
                error_log('Failed to read config.json: ' . $e->getMessage());
            }
        }

        $merged = self::deepMerge($defaults, $json);
        return self::pruneToDefaults($merged, $defaults);
    }

    private static function pruneToDefaults($data, $defaults) {
        if (!is_array($defaults)) {
            return $data;
        }
        if (!is_array($data)) {
            $data = array();
        }

        $out = array();
        foreach ($defaults as $k => $defVal) {
            if (is_array($defVal) && self::isAssoc($defVal)) {
                $out[$k] = self::pruneToDefaults(isset($data[$k]) ? $data[$k] : array(), $defVal);
            } else {
                if (array_key_exists($k, $data)) {
                    $out[$k] = $data[$k];
                } else {
                    $out[$k] = $defVal;
                }
            }
        }

        // Preserve schema metadata that is not part of defaults
        if (isset($data['schema_version'])) {
            $out['schema_version'] = (int)$data['schema_version'];
        }

        return $out;
    }

    /**
     * Normalize script path for cross-platform URL compatibility.
     * Converts backslashes to forward slashes (Windows compatibility).
     *
     * @param string $path Script path from dirname($_SERVER['SCRIPT_NAME'])
     * @return string Normalized path with forward slashes
     */
    public static function normalizeScriptPath($path) {
        return str_replace('\\', '/', $path);
    }

    /**
     * Default configuration (nested).
     */
    public static function defaults() {
        $baseUrl = self::detectBaseUrl();

        return array(
            'site' => array(
                'name' => 'Mantra CMS',
                'url' => $baseUrl,
            ),
            'locale' => array(
                'timezone' => 'UTC',
                'default_language' => 'en',
                'fallback_locale' => 'en',
            ),
            'theme' => array(
                'active' => 'default',
            ),
            'content' => array(
                'format' => 'json',
                'posts_per_page' => 10,
            ),
            'modules' => array(
                'enabled' => array('admin'),
            ),
            'security' => array(
                // Stored as string identifier; interpreted by Auth when hashing.
                'password_hash_algo' => 'PASSWORD_DEFAULT',
                'csrf_token_name' => 'mantra_csrf',
            ),
            'session' => array(
                'name' => 'mantra_session',
                'lifetime' => 7200,
                'cookie_secure' => 'auto',
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_path' => '/',
                'cookie_domain' => '',
            ),
            'logging' => array(
                'level' => 'debug',
                'retention_days' => 30,
            ),
            'proxy' => array(
                'trusted_proxies' => array(),
            ),
            'performance' => array(
                'gzip_compression' => false,
            ),
            'permissions' => array(
                'roles' => array(),
            ),
            'debug' => array(
                'enabled' => true,
            ),
            'advanced' => array(
                // Placeholder to force JSON object encoding for empty group.
                '_placeholder' => null,
            ),
        );
    }

    /**
     * Create install-time config (full defaults with specific overrides).
     */
    public static function buildInstallConfig($siteName, $language, $siteUrl) {
        $config = self::defaults();
        self::setNested($config, 'site.name', $siteName);
        self::setNested($config, 'locale.default_language', $language);
        self::setNested($config, 'locale.fallback_locale', 'en');
        self::setNested($config, 'site.url', $siteUrl);
        return $config;
    }

    /**
     * Resolve localized value (string or array with locale keys).
     */
    public static function resolveLocalized($value, $locale = null) {
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

    public static function detectBaseUrl() {
        // Note: Config::bootstrap() is called from core/bootstrap.php before helpers.php is loaded,
        // so this method must not depend on global helpers like is_https() or request().
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
        $scriptPath = self::normalizeScriptPath($scriptPath);
        $baseUrl = $protocol . '://' . $host . (($scriptPath && $scriptPath !== '/') ? $scriptPath : '');
        return $baseUrl;
    }

    public function __construct() {
        $this->configPath = MANTRA_CONTENT . '/settings/config.json';
        $this->load();
    }

    /**
     * Load configuration (merged: defaults + JSON overrides).
     */
    private function load() {
        $this->config = self::defaults();

        if (file_exists($this->configPath)) {
            try {
                $raw = FileIO::readLocked($this->configPath);
                $decoded = JsonCodec::decode($raw);
                if (is_array($decoded)) {
                    $this->config = self::deepMerge($this->config, $decoded);
                }
            } catch (Exception $e) {
                logger('app')->warning('Failed to read config.json, using defaults', array(
                    'path' => $this->configPath,
                    'error' => $e->getMessage()
                ));
            }
        }
    }

    /**
     * Get configuration value by dot-path.
     */
    public function get($path, $default = null) {
        return self::getNested($this->config, (string)$path, $default);
    }

    /**
     * Set configuration value by dot-path.
     */
    public function set($path, $value) {
        self::setNested($this->config, (string)$path, $value);
        return $this->save();
    }

    /**
     * Set multiple configuration values by dot-path.
     */
    public function setMultiple($values) {
        if (!is_array($values)) {
            return false;
        }
        foreach ($values as $path => $value) {
            self::setNested($this->config, (string)$path, $value);
        }
        return $this->save();
    }

    /**
     * Get all configuration.
     */
    public function all() {
        return $this->config;
    }

    /**
     * Save configuration to file.
     *
     * config.json is persisted as overrides-only (diff from Config::defaults()).
     * This matches the admin Settings store (ConfigSettings) and prevents writing
     * a huge merged config back to disk.
     */
    public function save() {
        $dir = dirname($this->configPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $defaults = self::defaults();
            $overrides = self::diffOverrides($defaults, $this->config);
            if (!is_array($overrides)) {
                $overrides = array();
            }

            // Preserve schema_version if present in the in-memory config.
            if (isset($this->config['schema_version'])) {
                $overrides['schema_version'] = (int)$this->config['schema_version'];
            }

            return FileIO::writeAtomic($this->configPath, JsonCodec::encode($overrides));
        } catch (Exception $e) {
            logger('app')->error('Failed to write config.json', array(
                'path' => $this->configPath,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Delete configuration key by dot-path.
     */
    public function delete($path) {
        $path = trim((string)$path);
        if ($path === '') {
            return false;
        }

        $parts = explode('.', $path);
        $cur =& $this->config;

        $last = array_pop($parts);
        foreach ($parts as $part) {
            if ($part === '' || !is_array($cur) || !array_key_exists($part, $cur)) {
                return false;
            }
            $cur =& $cur[$part];
        }

        if ($last === '' || !is_array($cur) || !array_key_exists($last, $cur)) {
            return false;
        }

        unset($cur[$last]);
        return $this->save();
    }

    /**
     * Check if dot-path exists.
     */
    public function has($path) {
        return self::hasNested($this->config, (string)$path);
    }

    /**
     * Deep merge two nested config arrays.
     */
    public static function deepMerge($base, $override) {
        if (!is_array($base)) {
            $base = array();
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
    public static function flattenPaths($nested, $prefix = '') {
        $out = array();
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

    public static function isAssoc($arr) {
        if (!is_array($arr)) {
            return false;
        }
        $keys = array_keys($arr);
        return array_keys($keys) !== $keys;
    }

    public static function getNested($arr, $path, $default = null) {
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

    public static function hasNested($arr, $path) {
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

    public static function setNested(&$arr, $path, $value) {
        if (!is_array($arr)) {
            $arr = array();
        }

        $path = trim((string)$path);
        if ($path === '') {
            return;
        }

        $parts = explode('.', $path);
        $cur =& $arr;

        $last = array_pop($parts);
        foreach ($parts as $part) {
            if ($part === '') {
                return;
            }
            if (!isset($cur[$part]) || !is_array($cur[$part])) {
                $cur[$part] = array();
            }
            $cur =& $cur[$part];
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
    public static function diffOverrides($defaults, $current) {
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

        $out = array();
        $hasAny = false;

        foreach ($defaults as $k => $defVal) {
            if (!array_key_exists($k, $current)) {
                continue;
            }

            $curVal = $current[$k];
            $child = self::diffOverrides($defVal, $curVal);
            if ($child !== null) {
                $out[$k] = $child;
                $hasAny = true;
            }
        }

        return $hasAny ? $out : null;
    }
}
