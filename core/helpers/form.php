<?php
/**
 * Form helpers
 */

/**
 * Get trimmed POST value
 * @param string $key POST key
 * @param string $default Default value
 * @return string Trimmed value
 */
function post_trimmed($key, $default = '')
{
    return trim((string)request()->post($key, $default));
}

/**
 * Get trimmed input value (POST or JSON)
 * @param string $key Input key
 * @param string $default Default value
 * @return string Trimmed value
 */
function input_trimmed($key, $default = '')
{
    return trim((string)request()->input($key, $default));
}
