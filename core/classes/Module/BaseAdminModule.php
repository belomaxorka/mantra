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
     * Initialize admin module.
     *
     * Reads optional "admin.sidebar" and "admin.quick_actions" from the
     * module manifest (module.json) and registers them automatically.
     * Subclasses should call parent::init() to keep this behaviour.
     */
    public function init() {
        $manifest = $this->getManifest();
        $admin = isset($manifest['admin']) && is_array($manifest['admin']) ? $manifest['admin'] : array();

        // Auto-register sidebar item from manifest
        if (isset($admin['sidebar']) && is_array($admin['sidebar'])) {
            $sb = $admin['sidebar'];
            $item = array(
                'id'    => isset($sb['id']) ? $sb['id'] : $this->getId(),
                'title' => isset($sb['title']) ? $sb['title'] : $this->getId() . '.title',
                'icon'  => isset($sb['icon']) ? $sb['icon'] : '',
                'group' => isset($sb['group']) ? $sb['group'] : '',
                'order' => isset($sb['order']) ? (int)$sb['order'] : 50,
            );
            if (isset($sb['url'])) {
                $item['url'] = base_url($sb['url']);
            }
            $this->registerSidebarItem($item);
        }

        // Auto-register quick actions from manifest
        if (isset($admin['quick_actions']) && is_array($admin['quick_actions'])) {
            foreach ($admin['quick_actions'] as $qa) {
                if (!is_array($qa) || empty($qa['title'])) {
                    continue;
                }
                $action = array(
                    'id'    => isset($qa['id']) ? $qa['id'] : $this->getId() . '-qa',
                    'title' => $qa['title'],
                    'icon'  => isset($qa['icon']) ? $qa['icon'] : '',
                    'order' => isset($qa['order']) ? (int)$qa['order'] : 50,
                );
                if (isset($qa['url'])) {
                    $action['url'] = base_url($qa['url']);
                }
                $this->registerQuickAction($action);
            }
        }
    }

    /**
     * Register admin route
     * @param string $method HTTP method (GET, POST, ANY)
     * @param string $pattern Route pattern (without /admin prefix)
     * @param callable $callback Route handler
     */
    protected function registerAdminRoute($method, $pattern, $callback) {
        app()->hooks()->register('routes.register', function ($data) use ($method, $pattern, $callback) {
            $admin = app()->modules()->getModule('admin');
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
        $admin = app()->modules()->getModule('admin');
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
        if (app()->request()->method() !== 'POST') {
            return true;
        }
        $token = app()->request()->post('csrf_token', '');
        if (!app()->auth()->verifyCsrfToken($token)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return false;
        }
        return true;
    }
    
    /**
     * Get current user
     * @return array|null
     */
    protected function getUser() {
        return app()->auth()->user();
    }
    
    /**
     * Check if user is authenticated
     * @return bool
     */
    protected function isAuthenticated() {
        return app()->auth()->check();
    }
    
    /**
     * Redirect to admin page
     * @param string $path Path relative to /admin
     */
    protected function redirectAdmin($path = '') {
        app()->response()->redirect(base_url('/admin/' . ltrim($path, '/')));
    }
    
    /**
     * Render admin view (shorthand)
     * @param string $template Template name (module:template)
     * @param array $data Template data
     * @return string
     */
    protected function renderView($template, $data = array()) {
        return app()->view()->fetch($template, $data);
    }
}
