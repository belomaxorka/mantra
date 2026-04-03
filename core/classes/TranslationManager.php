<?php declare(strict_types=1);
/**
 * TranslationManager - Enhanced i18n system with module integration
 * 
 * Features:
 * - Automatic module translation discovery
 * - Lazy loading of translations
 * - Namespace-based translation keys
 * - Fallback chain support
 * - Translation caching
 */

class TranslationManager {
    private $locale;
    private $fallbackLocale;
    private $translations = [];
    private $loadedDomains = [];
    private $customDomainPaths = [];

    public function __construct($locale = null, $fallbackLocale = null) {
        $this->locale = $locale ?: config('locale.default_language', 'en');
        $this->fallbackLocale = $fallbackLocale ?: config('locale.fallback_locale', 'en');
    }

    /**
     * Translate a key
     * @param string $key Namespaced key (e.g., 'admin.title', 'pages.create')
     * @param array $params Interpolation parameters
     * @return string
     */
    public function translate($key, $params = []) {
        $domain = $this->extractDomain($key);

        if (!$domain) {
            return $this->interpolate($key, $params);
        }

        // Try current locale
        $value = $this->get($domain, $key, $this->locale);

        // Try fallback locale
        if ($value === null && $this->fallbackLocale !== $this->locale) {
            $value = $this->get($domain, $key, $this->fallbackLocale);
        }

        // Return key if not found
        if ($value === null) {
            return $this->interpolate($key, $params);
        }

        return $this->interpolate($value, $params);
    }

    /**
     * Get translation from domain
     */
    private function get($domain, $key, $locale) {
        $this->loadDomain($domain, $locale);

        $domainKey = "{$domain}:{$locale}";
        return $this->translations[$domainKey][$key] ?? null;
    }

    /**
     * Load translations for a domain
     */
    private function loadDomain($domain, $locale): void {
        $domainKey = "{$domain}:{$locale}";

        if (isset($this->loadedDomains[$domainKey])) {
            return;
        }

        $this->loadedDomains[$domainKey] = true;

        // Check custom registered domains first (panels, etc.)
        if (isset($this->customDomainPaths[$domain])) {
            $file = $this->customDomainPaths[$domain] . '/' . $locale . '.php';
            $this->loadFile($domain, $locale, $file);
            return;
        }

        // Try to load from module
        if ($this->isModuleDomain($domain)) {
            $this->loadModuleTranslations($domain, $locale);
            return;
        }

        // Try to load from theme
        if ($domain === 'theme') {
            $this->loadThemeTranslations($locale);
            return;
        }

        // Try to load from core
        if ($domain === 'core') {
            $this->loadCoreTranslations($locale);
        }
    }

    /**
     * Load module translations
     */
    private function loadModuleTranslations($moduleId, $locale): void {
        $file = MANTRA_MODULES . '/' . $moduleId . '/lang/' . $locale . '.php';
        $this->loadFile($moduleId, $locale, $file);
    }

    /**
     * Load theme translations
     */
    private function loadThemeTranslations($locale): void {
        $theme = config('theme.active', 'default');
        $file = MANTRA_THEMES . '/' . $theme . '/lang/' . $locale . '.php';
        $this->loadFile('theme', $locale, $file);
    }

    /**
     * Load core translations
     */
    private function loadCoreTranslations($locale): void {
        $file = MANTRA_CORE . '/lang/' . $locale . '.php';
        $this->loadFile('core', $locale, $file);
    }

    /**
     * Load translation file
     */
    private function loadFile($domain, $locale, $file): void {
        if (!file_exists($file)) {
            return;
        }

        $data = include $file;

        if (!is_array($data)) {
            return;
        }

        $domainKey = "{$domain}:{$locale}";

        if (!isset($this->translations[$domainKey])) {
            $this->translations[$domainKey] = [];
        }

        $this->translations[$domainKey] = array_merge(
            $this->translations[$domainKey],
            $data,
        );
    }

    /**
     * Check if domain is a module
     */
    private function isModuleDomain($domain) {
        $modules = app()->modules();
        return $modules !== null && $modules->isLoaded($domain);
    }

    /**
     * Extract domain from key
     */
    private function extractDomain($key) {
        $pos = strpos($key, '.');
        return $pos !== false ? substr($key, 0, $pos) : null;
    }

    /**
     * Interpolate parameters
     */
    private function interpolate($text, $params) {
        if (empty($params)) {
            return $text;
        }

        foreach ($params as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)$value, $text);
        }

        return $text;
    }

    /**
     * Register a custom domain with an explicit lang directory path.
     * Used by admin panels and other non-module translation sources.
     *
     * @param string $domain  Translation domain (e.g. 'admin-pages')
     * @param string $langDir Absolute path to the lang/ directory
     */
    public function registerDomain($domain, $langDir): void {
        $this->customDomainPaths[$domain] = $langDir;
    }

    /**
     * Get current locale
     */
    public function getLocale() {
        return $this->locale;
    }

    /**
     * Set locale
     */
    public function setLocale($locale): void {
        $this->locale = $locale;
    }

    /**
     * Get fallback locale
     */
    public function getFallbackLocale() {
        return $this->fallbackLocale;
    }

    /**
     * Check if translation exists
     */
    public function has($key) {
        $domain = $this->extractDomain($key);

        if (!$domain) {
            return false;
        }

        $value = $this->get($domain, $key, $this->locale);

        if ($value === null && $this->fallbackLocale !== $this->locale) {
            $value = $this->get($domain, $key, $this->fallbackLocale);
        }

        return $value !== null;
    }

    /**
     * Get all translations for a domain
     */
    public function getDomainTranslations($domain, $locale = null) {
        $locale = $locale ?: $this->locale;
        $this->loadDomain($domain, $locale);

        $domainKey = "{$domain}:{$locale}";
        return $this->translations[$domainKey] ?? [];
    }

    /**
     * Discover all available translations for modules
     */
    public function discoverModuleTranslations() {
        $result = [];
        $modules = app()->modules()->getModules();

        foreach ($modules as $moduleId => $data) {
            $module = $data['instance'];

            if (!$module->hasTranslations()) {
                continue;
            }

            $langPath = $module->getPath() . '/lang';
            $locales = [];

            if (is_dir($langPath)) {
                $files = scandir($langPath);
                foreach ($files as $file) {
                    if (preg_match('/^([a-z]{2})\.php$/', $file, $matches)) {
                        $locales[] = $matches[1];
                    }
                }
            }

            $result[$moduleId] = [
                'name' => $module->getName(),
                'locales' => $locales,
                'path' => $langPath,
            ];
        }

        return $result;
    }
}
