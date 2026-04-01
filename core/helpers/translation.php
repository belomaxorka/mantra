<?php
/**
 * Translation helpers
 */

/**
 * Get the TranslationManager singleton.
 *
 * Use this to register custom domains or access advanced API.
 * For simple translations, use t() instead.
 *
 * @return TranslationManager
 */
function translator()
{
    static $instance = null;
    if ($instance === null) {
        $instance = new TranslationManager();
    }
    return $instance;
}

/**
 * Translation helper
 * @param string $key Translation key
 * @param array $params Parameters for interpolation
 * @return string
 */
function t($key, $params = array())
{
    return translator()->translate($key, $params);
}
