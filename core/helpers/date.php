<?php
/**
 * Date and time helpers
 */

/**
 * Standard datetime format used throughout the application
 */
if (!defined('DATETIME_FORMAT')) {
    define('DATETIME_FORMAT', 'Y-m-d H:i:s');
}

/**
 * Get current datetime in standard format
 * @return string Current datetime
 */
function now()
{
    return date(DATETIME_FORMAT);
}

/**
 * Format timestamp to standard datetime format
 * @param int|null $timestamp Unix timestamp (null for current time)
 * @return string Formatted datetime
 */
function datetime($timestamp = null)
{
    if ($timestamp === null) {
        $timestamp = time();
    }
    return date(DATETIME_FORMAT, $timestamp);
}
