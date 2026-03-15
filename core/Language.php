<?php
/**
 * Language - i18n translator (module/theme scoped)
 *
 * - Single global locale from config('default_language')
 * - Fallback locale from config('fallback_locale', 'en')
 * - Loads PHP translation arrays from:
 *   - themes/<active_theme>/lang/<locale>.php
 *   - modules/<module>/lang/<locale>.php
 *
 * Keys are expected to be namespaced:
 *   - "pages.title" => module "pages"
 *   - "theme.header.welcome" => theme domain
 */
class Language {
    private $locale;
    private $fallbackLocale;
    private $theme;

    private $themeTranslations = array(); // [locale => array]
    private $moduleTranslations = array(); // [module => [locale => array]]

    public function __construct() {
        // Prefer config() helper when available (no Application dependency),
        // but fall back to early-loaded $GLOBALS['MANTRA_CONFIG'] during bootstrap.
        if (function_exists('config')) {
            $this->locale = (string)config('default_language', 'en');
            $this->fallbackLocale = (string)config('fallback_locale', 'en');
            $this->theme = (string)config('active_theme', 'default');
        } else {
            $cfg = (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) ? $GLOBALS['MANTRA_CONFIG'] : array();
            $this->locale = isset($cfg['default_language']) ? (string)$cfg['default_language'] : 'en';
            $this->fallbackLocale = isset($cfg['fallback_locale']) ? (string)$cfg['fallback_locale'] : 'en';
            $this->theme = isset($cfg['active_theme']) ? (string)$cfg['active_theme'] : 'default';
        }

        if ($this->locale === '') {
            $this->locale = 'en';
        }
        if ($this->fallbackLocale === '') {
            $this->fallbackLocale = 'en';
        }
        if ($this->theme === '') {
            $this->theme = 'default';
        }
    }

    public function getLocale() {
        return $this->locale;
    }

    public function getFallbackLocale() {
        return $this->fallbackLocale;
    }

    /**
     * Translate a key with optional {param} interpolation.
     */
    public function translate($key, $params = array()) {
        $key = (string)$key;

        $value = $this->lookup($key, $this->locale);
        if ($value === null && $this->fallbackLocale !== $this->locale) {
            $value = $this->lookup($key, $this->fallbackLocale);
        }

        if (!is_string($value) || $value === '') {
            $value = $key;
        }

        foreach ($params as $k => $v) {
            $value = str_replace('{' . $k . '}', (string)$v, $value);
        }

        return $value;
    }

    private function lookup($key, $locale) {
        $domain = $this->domainFromKey($key);
        if ($domain === 'theme') {
            $dict = $this->loadTheme($locale);
            return isset($dict[$key]) ? $dict[$key] : null;
        }

        if ($domain) {
            $dict = $this->loadModule($domain, $locale);
            return isset($dict[$key]) ? $dict[$key] : null;
        }

        return null;
    }

    private function domainFromKey($key) {
        $pos = strpos($key, '.');
        if ($pos === false) {
            return null;
        }
        return substr($key, 0, $pos);
    }

    private function loadTheme($locale) {
        if (isset($this->themeTranslations[$locale])) {
            return $this->themeTranslations[$locale];
        }

        $file = MANTRA_THEMES . '/' . $this->theme . '/lang/' . $locale . '.php';
        $this->themeTranslations[$locale] = $this->loadTranslationFile($file);
        return $this->themeTranslations[$locale];
    }

    private function loadModule($module, $locale) {
        if (!isset($this->moduleTranslations[$module])) {
            $this->moduleTranslations[$module] = array();
        }
        if (isset($this->moduleTranslations[$module][$locale])) {
            return $this->moduleTranslations[$module][$locale];
        }

        $file = MANTRA_MODULES . '/' . $module . '/lang/' . $locale . '.php';
        $this->moduleTranslations[$module][$locale] = $this->loadTranslationFile($file);
        return $this->moduleTranslations[$module][$locale];
    }

    private function loadTranslationFile($filePath) {
        if (!is_string($filePath) || $filePath === '' || !file_exists($filePath)) {
            return array();
        }

        $data = include $filePath;

        if (is_array($data)) {
            // Support `return array('translations' => [...])` shape if someone prefers it.
            if (isset($data['translations']) && is_array($data['translations'])) {
                return $data['translations'];
            }
            return $data;
        }

        return array();
    }
}

// NOTE: Global helpers t()/__() live in core/helpers.php
