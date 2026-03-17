<?php
/**
 * Configuration helpers
 */

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
