<?php declare(strict_types=1);

/**
 * Language - i18n translator (module/theme scoped)
 *
 * - Single global locale from config('locale.default_language')
 * - Fallback locale from config('locale.fallback_locale', 'en')
 * - Loads PHP translation arrays from:
 *   - themes/<theme.active>/lang/<locale>.php
 *   - modules/<module>/lang/<locale>.php
 *
 * Keys are expected to be namespaced:
 *   - "pages.title" => module "pages"
 *   - "admin-dashboard.title" => module "admin-dashboard"
 *   - "theme.header.welcome" => theme domain
 *
 * Hierarchical loading for parent namespaces:
 *   - "admin.dashboard.title" => checks "admin" module, then "admin-dashboard" module
 *   - Parent namespace translations take precedence over child modules
 *   - Supports both "admin.dashboard.*" (legacy) and "admin-dashboard.*" (new) formats
 */
class Language
{
    private $locale;
    private $fallbackLocale;
    private $theme;

    private $themeTranslations = []; // [locale => array]
    private $moduleTranslations = []; // [module => [locale => array]]
    private $childModulesCache = []; // [parent => [child1, child2, ...]]

    public function __construct()
    {
        $this->locale = (string)config('locale.default_language', 'en');
        $this->fallbackLocale = (string)config('locale.fallback_locale', 'en');
        $this->theme = (string)config('theme.active', 'default');

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

    public function getLocale()
    {
        return $this->locale;
    }

    public function getFallbackLocale()
    {
        return $this->fallbackLocale;
    }

    /**
     * Translate a key with optional {param} interpolation.
     */
    public function translate($key, $params = [])
    {
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

    private function lookup($key, $locale)
    {
        $domain = $this->domainFromKey($key);
        if ($domain === 'theme') {
            $dict = $this->loadTheme($locale);
            return $dict[$key] ?? null;
        }

        if ($domain) {
            $dict = $this->loadModule($domain, $locale);
            return $dict[$key] ?? null;
        }

        return null;
    }

    private function domainFromKey($key)
    {
        $pos = strpos($key, '.');
        if ($pos === false) {
            return null;
        }
        return substr($key, 0, $pos);
    }

    private function loadTheme($locale)
    {
        if (isset($this->themeTranslations[$locale])) {
            return $this->themeTranslations[$locale];
        }

        $file = MANTRA_THEMES . '/' . $this->theme . '/lang/' . $locale . '.php';
        $this->themeTranslations[$locale] = $this->loadTranslationFile($file);
        return $this->themeTranslations[$locale];
    }

    private function loadModule($module, $locale)
    {
        if (!isset($this->moduleTranslations[$module])) {
            $this->moduleTranslations[$module] = [];
        }
        if (isset($this->moduleTranslations[$module][$locale])) {
            return $this->moduleTranslations[$module][$locale];
        }

        // Primary: Load from exact module match
        $file = MANTRA_MODULES . '/' . $module . '/lang/' . $locale . '.php';
        $translations = $this->loadTranslationFile($file);

        // Secondary: For parent namespaces (without hyphen), also check child modules
        // Example: "admin" key can load from "admin-dashboard", "admin-pages", etc.
        if (!str_contains($module, '-')  ) {
            // This is a parent namespace (e.g., "admin")
            // Check all child modules (e.g., "admin-dashboard", "admin-pages")
            $childModules = $this->findChildModules($module);
            foreach ($childModules as $childModule) {
                $childFile = MANTRA_MODULES . '/' . $childModule . '/lang/' . $locale . '.php';
                $childTranslations = $this->loadTranslationFile($childFile);
                // Merge child translations (parent takes precedence)
                $translations = array_merge($childTranslations, $translations);
            }
        }

        $this->moduleTranslations[$module][$locale] = $translations;
        return $this->moduleTranslations[$module][$locale];
    }

    /**
     * Find child modules for a parent namespace
     * Example: "admin" -> ["admin-dashboard", "admin-pages", "admin-posts", "admin-settings"]
     *
     * @param string $parentNamespace Parent namespace (e.g., "admin")
     * @return array Array of child module names
     */
    private function findChildModules($parentNamespace)
    {
        // Check cache first
        if (isset($this->childModulesCache[$parentNamespace])) {
            return $this->childModulesCache[$parentNamespace];
        }

        $children = [];
        $modulesDir = MANTRA_MODULES;

        if (!is_dir($modulesDir)) {
            $this->childModulesCache[$parentNamespace] = $children;
            return $children;
        }

        // Find all directories matching pattern: {parent}-*
        $pattern = $modulesDir . '/' . $parentNamespace . '-*';
        $dirs = glob($pattern, GLOB_ONLYDIR);

        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $children[] = basename($dir);
            }
        }

        // Cache the result
        $this->childModulesCache[$parentNamespace] = $children;
        return $children;
    }

    private function loadTranslationFile($filePath)
    {
        if (!is_string($filePath) || $filePath === '' || !file_exists($filePath)) {
            return [];
        }

        $data = include $filePath;

        if (is_array($data)) {
            // Support `return array('translations' => [...])` shape if someone prefers it.
            if (isset($data['translations']) && is_array($data['translations'])) {
                return $data['translations'];
            }
            return $data;
        }

        return [];
    }
}

// NOTE: Global helpers t()/__() live in core/helpers.php
