<?php
/**
 * Application helpers
 */

/**
 * Get application instance
 */
function app()
{
    return Application::getInstance();
}

/**
 * Get module instance
 * @param string $moduleId
 * @return Module|null
 */
function module($moduleId)
{
    return app()->modules()->getModule($moduleId);
}

/**
 * Get admin module instance
 * @return Module|null
 */
function admin()
{
    static $admin = null;
    if ($admin === null) {
        $admin = app()->modules()->getModule('admin');
    }
    return $admin;
}
