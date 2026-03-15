<?php
/**
 * Config - Configuration management
 *
 * Single source of truth: content/settings/config.json
 *
 * - Provides defaults
 * - Auto-detects base URL (used as default site_url)
 * - Merges JSON over defaults
 */
class Config {
    private $configPath = '';
    private $config = array();

    /**
     * Build full configuration array (defaults + JSON overrides).
     * Intended for early bootstrap (index.php) before Application exists.
     */
    public static function bootstrap($configPath = null) {
        $path = $configPath ? $configPath : (defined('MANTRA_CONTENT') ? (MANTRA_CONTENT . '/settings/config.json') : null);

        $defaults = self::defaults();
        $json = array();

        if ($path && file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return array_merge($defaults, $json);
    }

    /**
     * Default configuration.
     * Note: site_url defaults to an auto-detected base URL.
     */
    public static function defaults() {
        $baseUrl = self::detectBaseUrl();

        return array(
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

            // Proxy/CDN settings
            // Only trust proxy headers (X-Forwarded-For, etc.) when REMOTE_ADDR matches one of these entries.
            // Entries can be IPs or CIDRs (IPv4/IPv6). Example: array('127.0.0.1', '10.0.0.0/8')
            'trusted_proxies' => array(),

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
    }

    /**
     * Create install-time config (full defaults with specific overrides).
     */
    public static function buildInstallConfig($siteName, $language, $siteUrl) {
        $config = self::defaults();
        $config['site_name'] = $siteName;
        $config['default_language'] = $language;
        $config['site_url'] = $siteUrl;
        return $config;
    }

    private static function detectBaseUrl() {
        // Note: Config::bootstrap() is called from core/bootstrap.php before helpers.php is loaded,
        // so this method must not depend on global helpers like is_https() or request().
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
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
            $content = file_get_contents($this->configPath);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $this->config = array_merge($this->config, $decoded);
            }
        }
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Set configuration value
     */
    public function set($key, $value) {
        $this->config[$key] = $value;
        return $this->save();
    }

    /**
     * Set multiple configuration values
     */
    public function setMultiple($data) {
        $this->config = array_merge($this->config, $data);
        return $this->save();
    }

    /**
     * Get all configuration
     */
    public function all() {
        return $this->config;
    }

    /**
     * Save configuration to file
     */
    public function save() {
        $dir = dirname($this->configPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->configPath, $json) !== false;
    }

    /**
     * Delete configuration key
     */
    public function delete($key) {
        if (isset($this->config[$key])) {
            unset($this->config[$key]);
            return $this->save();
        }
        return false;
    }

    /**
     * Check if key exists
     */
    public function has($key) {
        return isset($this->config[$key]);
    }
}
