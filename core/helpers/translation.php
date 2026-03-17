<?php
/**
 * Translation helpers
 */

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
