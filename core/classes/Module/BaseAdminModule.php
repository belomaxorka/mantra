<?php
/**
 * BaseAdminModule - Base class for admin panel modules
 * 
 * Provides common functionality for modules that extend the admin panel:
 * - Easy route registration
 * - Sidebar item registration
 * - Quick action registration
 * - Admin rendering
 * - CSRF protection
 */

namespace Module;

abstract class BaseAdminModule extends Module {
    
    /**
     * Register admin route
     * @param string $method HTTP method (GET, POST, ANY)
     * @param string $pattern Route pattern (without /admin prefix)
     * @param callable $callback Route handler
     */
    protected function registerAdminRoute($method, $pattern, $callback) {
        app()->hooks()->register('routes.register', function ($data) use ($method, $pattern, $callback) {
            $admin = admin();
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute($method, $pattern, $callback);
            }
            return $data;
        });
    }
    
    /**
     * Register sidebar item
     * @param array $item Sidebar item configuration
     */
    protected function registerSidebarItem($item) {
        app()->hooks()->register('admin.sidebar', function ($items) use ($item) {
            if (!is_array($items)) {
                $items = array();
            }
            $items[] = $item;
            return $items;
        });
    }
    
    /**
     * Register quick action
     * @param array $action Quick action configuration
     */
    protected function registerQuickAction($action) {
        app()->hooks()->register('admin.quick_actions', function ($actions) use ($action) {
            if (!is_array($actions)) {
                $actions = array();
            }
            $actions[] = $action;
            return $actions;
        });
    }
    
    /**
     * Render admin page
     * @param string $title Page title
     * @param string $content Page content
     * @param array $extra Extra data
     * @return string
     */
    protected function renderAdmin($title, $content, $extra = array()) {
        $admin = admin();
        if ($admin && method_exists($admin, 'render')) {
            return $admin->render($title, $content, $extra);
        }
        http_response_code(500);
        echo 'Admin module not loaded';
    }
    
    /**
     * Verify CSRF token (shorthand)
     * @return bool
     */
    protected function verifyCsrf() {
        return verify_csrf();
    }
    
    /**
     * Get current user
     * @return array|null
     */
    protected function getUser() {
        return auth()->user();
    }
    
    /**
     * Check if user is authenticated
     * @return bool
     */
    protected function isAuthenticated() {
        return auth()->check();
    }
    
    /**
     * Redirect to admin page
     * @param string $path Path relative to /admin
     */
    protected function redirectAdmin($path = '') {
        redirect(base_url('/admin/' . ltrim($path, '/')));
    }
    
    /**
     * Render admin view (shorthand)
     * @param string $template Template name (module:template)
     * @param array $data Template data
     * @return string
     */
    protected function renderView($template, $data = array()) {
        return view()->fetch($template, $data);
    }
}
