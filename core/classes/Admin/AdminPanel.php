<?php
/**
 * AdminPanel - Abstract base class for admin panels
 *
 * Provides helpers similar to BaseAdminModule but adapted for the panel
 * lifecycle where AdminModule manages auth, layout, and sidebar.
 */

namespace Admin;

use Application;

abstract class AdminPanel implements AdminPanelInterface {

    /** @var \AdminModule */
    protected $admin;

    /** @var string Filesystem path to this panel directory */
    protected $panelPath = '';

    /** @var array Parsed panel.json contents */
    protected $metadata = array();

    public function __construct($panelPath, $metadata) {
        $this->panelPath = $panelPath;
        $this->metadata = is_array($metadata) ? $metadata : array();
    }

    // ========== Lifecycle ==========

    public function init($admin) {
        $this->admin = $admin;
    }

    public function registerRoutes($admin) {
        // Override in subclasses
    }

    // ========== Sidebar / Quick Actions (declarative from panel.json) ==========

    public function getSidebarItem() {
        if (!isset($this->metadata['sidebar']) || !is_array($this->metadata['sidebar'])) {
            return null;
        }

        $sb = $this->metadata['sidebar'];

        // Hide sidebar item if user lacks the required role
        if (isset($sb['require_role'])) {
            $required = $sb['require_role'];
            if (is_string($required)) {
                $required = array($required);
            }
            if (is_array($required)) {
                $user = app()->auth()->user();
                $userRole = is_array($user) && isset($user['role']) ? $user['role'] : '';
                if (!in_array($userRole, $required, true)) {
                    return null;
                }
            }
        }

        return array(
            'id'    => isset($sb['id']) ? $sb['id'] : $this->id(),
            'title' => isset($sb['title']) ? $sb['title'] : 'admin-' . $this->id() . '.title',
            'icon'  => isset($sb['icon']) ? $sb['icon'] : '',
            'group' => isset($sb['group']) ? $sb['group'] : '',
            'order' => isset($sb['order']) ? (int)$sb['order'] : 50,
            'url'   => isset($sb['url']) ? base_url($sb['url']) : '',
        );
    }

    public function getQuickActions() {
        if (!isset($this->metadata['quick_actions']) || !is_array($this->metadata['quick_actions'])) {
            return array();
        }

        // Hide quick actions if user lacks the required role for this panel
        if (isset($this->metadata['sidebar']['require_role'])) {
            $required = $this->metadata['sidebar']['require_role'];
            if (is_string($required)) {
                $required = array($required);
            }
            if (is_array($required)) {
                $user = app()->auth()->user();
                $userRole = is_array($user) && isset($user['role']) ? $user['role'] : '';
                if (!in_array($userRole, $required, true)) {
                    return array();
                }
            }
        }

        $actions = array();
        foreach ($this->metadata['quick_actions'] as $qa) {
            if (!is_array($qa) || empty($qa['title'])) {
                continue;
            }
            $actions[] = array(
                'id'    => isset($qa['id']) ? $qa['id'] : $this->id() . '-qa',
                'title' => $qa['title'],
                'icon'  => isset($qa['icon']) ? $qa['icon'] : '',
                'url'   => isset($qa['url']) ? base_url($qa['url']) : '',
                'order' => isset($qa['order']) ? (int)$qa['order'] : 50,
            );
        }
        return $actions;
    }

    // ========== View Rendering ==========

    /**
     * Render a template from this panel's views/ directory.
     *
     * @param string $template Template name (without .php), e.g. 'list'
     * @param array  $data     Variables to extract into template scope
     * @return string Rendered HTML
     */
    protected function renderView($template, $data = array()) {
        $path = $this->panelPath . '/views/' . $template . '.php';
        return app()->view()->fetchPath($path, $data);
    }

    /**
     * Wrap content in the admin layout.
     */
    protected function renderAdmin($title, $content, $extra = array()) {
        return $this->admin->render($title, $content, $extra);
    }

    // ========== Access Control ==========

    /**
     * Check if current user has a specific permission.
     * Returns false and renders a 403 page if denied.
     *
     * @param string $permission
     * @return bool|string  true for full access, 'own' for ownership-gated, false if denied
     */
    protected function requirePermission($permission) {
        $userManager = new \User();
        $result = $userManager->hasPermission($this->getUser(), $permission);

        if ($result === false) {
            http_response_code(403);
            echo $this->renderAdmin(
                t('admin.common.access_denied'),
                '<div class="alert alert-danger alert-permanent">' . e(t('admin.common.access_denied')) . '</div>'
            );
            return false;
        }

        return $result; // true or 'own'
    }

    /**
     * Check if current user has admin role.
     * Returns false and renders a 403 page if denied.
     */
    protected function requireAdmin() {
        if ($this->auth()->hasRole('admin')) {
            return true;
        }
        http_response_code(403);
        echo $this->renderAdmin(
            t('admin.common.access_denied'),
            '<div class="alert alert-danger alert-permanent">' . e(t('admin.common.access_denied')) . '</div>'
        );
        return false;
    }

    // ========== Convenience Helpers ==========

    protected function db() {
        return app()->db();
    }

    protected function auth() {
        return app()->auth();
    }

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

    protected function getUser() {
        return app()->auth()->user();
    }

    protected function redirectAdmin($path = '') {
        app()->response()->redirect(base_url('/admin/' . ltrim($path, '/')));
    }

    /**
     * Register a hook listener.
     */
    protected function hook($hookName, $callback, $priority = 10) {
        return Application::getInstance()->hooks()->register($hookName, $callback, $priority);
    }

    /**
     * Fire a hook.
     */
    protected function fireHook($hookName, $data = null, $context = null) {
        return Application::getInstance()->hooks()->fire($hookName, $data, $context);
    }

    /**
     * Get panel asset URL.
     */
    public function asset($path) {
        $path = ltrim($path, '/');
        $version = isset($this->metadata['version']) ? $this->metadata['version'] : '';
        $url = '/modules/admin/panels/' . $this->id() . '/assets/' . $path;
        return $version !== '' ? $url . '?v=' . urlencode($version) : $url;
    }

    /**
     * Get the panel filesystem path.
     */
    public function getPath() {
        return $this->panelPath;
    }

    /**
     * Get the panel metadata.
     */
    public function getMetadata() {
        return $this->metadata;
    }
}
