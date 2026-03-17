<?php
/**
 * Database helpers
 */

/**
 * Get database instance
 */
function db()
{
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}
