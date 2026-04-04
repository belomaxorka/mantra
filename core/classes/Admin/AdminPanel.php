<?php declare(strict_types=1);
/**
 * AdminPanel - Abstract base class for admin panels
 *
 * Provides helpers similar to BaseAdminModule but adapted for the panel
 * lifecycle where AdminModule manages auth, layout, and sidebar.
 */

namespace Admin;

use Application;

abstract class AdminPanel implements AdminPanelInterface
{

    /** @var \AdminModule */
    protected $admin;

    /** @var string Filesystem path to this panel directory */
    protected $panelPath = '';

    /** @var array Parsed panel.json contents */
    protected $metadata = [];

    public function __construct($panelPath, $metadata)
    {
        $this->panelPath = $panelPath;
        $this->metadata = is_array($metadata) ? $metadata : [];
    }

    // ========== Lifecycle ==========

    public function init($admin): void
    {
        $this->admin = $admin;
    }

    public function registerRoutes($admin): void
    {
        // Override in subclasses
    }

    // ========== Sidebar / Quick Actions (declarative from panel.json) ==========

    public function getSidebarItem()
    {
        if (!isset($this->metadata['sidebar']) || !is_array($this->metadata['sidebar'])) {
            return null;
        }

        $sb = $this->metadata['sidebar'];

        // Hide sidebar item if user lacks the required role
        if (isset($sb['require_role'])) {
            $required = $sb['require_role'];
            if (is_string($required)) {
                $required = [$required];
            }
            if (is_array($required)) {
                $user = app()->auth()->user();
                $userRole = is_array($user) && isset($user['role']) ? $user['role'] : '';
                if (!in_array($userRole, $required, true)) {
                    return null;
                }
            }
        }

        return [
            'id' => $sb['id'] ?? $this->id(),
            'title' => $sb['title'] ?? 'admin-' . $this->id() . '.title',
            'icon' => $sb['icon'] ?? '',
            'group' => $sb['group'] ?? '',
            'order' => isset($sb['order']) ? (int)$sb['order'] : 50,
            'url' => isset($sb['url']) ? base_url($sb['url']) : '',
        ];
    }

    public function getQuickActions()
    {
        if (!isset($this->metadata['quick_actions']) || !is_array($this->metadata['quick_actions'])) {
            return [];
        }

        // Hide quick actions if user lacks the required role for this panel
        if (isset($this->metadata['sidebar']['require_role'])) {
            $required = $this->metadata['sidebar']['require_role'];
            if (is_string($required)) {
                $required = [$required];
            }
            if (is_array($required)) {
                $user = app()->auth()->user();
                $userRole = is_array($user) && isset($user['role']) ? $user['role'] : '';
                if (!in_array($userRole, $required, true)) {
                    return [];
                }
            }
        }

        $actions = [];
        foreach ($this->metadata['quick_actions'] as $qa) {
            if (!is_array($qa) || empty($qa['title'])) {
                continue;
            }
            $actions[] = [
                'id' => $qa['id'] ?? $this->id() . '-qa',
                'title' => $qa['title'],
                'icon' => $qa['icon'] ?? '',
                'url' => isset($qa['url']) ? base_url($qa['url']) : '',
                'order' => isset($qa['order']) ? (int)$qa['order'] : 50,
            ];
        }
        return $actions;
    }

    // ========== View Rendering ==========

    /**
     * Render a template from this panel's views/ directory.
     *
     * @param string $template Template name (without .php), e.g. 'list'
     * @param array $data Variables to extract into template scope
     * @return string Rendered HTML
     */
    protected function renderView($template, $data = [])
    {
        $path = $this->panelPath . '/views/' . $template . '.php';
        return app()->view()->fetchPath($path, $data);
    }

    /**
     * Wrap content in the admin layout.
     */
    protected function renderAdmin($title, $content, $extra = [])
    {
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
    protected function requirePermission($permission)
    {
        $userManager = new \User();
        $result = $userManager->hasPermission($this->getUser(), $permission);

        if ($result === false) {
            $this->renderErrorPage(t('admin.common.access_denied'));
            return false;
        }

        return $result; // true or 'own'
    }

    /**
     * Check if current user has admin role.
     * Returns false and renders a 403 page if denied.
     */
    protected function requireAdmin()
    {
        if ($this->auth()->hasRole('admin')) {
            return true;
        }
        $this->renderErrorPage(t('admin.common.access_denied'));
        return false;
    }

    /**
     * Render a full-page error (403/404) with inline alert.
     */
    protected function renderErrorPage($message, $code = 403): void
    {
        http_response_code($code);
        $title = ($code === 404) ? t('admin.common.not_found') : t('admin.common.access_denied');
        echo $this->renderAdmin(
            $title,
            '<div class="alert alert-danger alert-permanent">' . e($message) . '</div>',
        );
    }

    // ========== Convenience Helpers ==========

    protected function db()
    {
        return app()->db();
    }

    protected function auth()
    {
        return app()->auth();
    }

    protected function verifyCsrf()
    {
        if (app()->request()->method() !== 'POST') {
            return true;
        }

        $token = app()->request()->post('csrf_token', '')
              ?: app()->request()->header('X-CSRF-Token', '');

        if (!app()->auth()->verifyCsrfToken($token)) {
            if (app()->request()->acceptsJson()) {
                app()->response()->json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
            }
            http_response_code(403);
            echo 'Invalid CSRF token';
            return false;
        }
        return true;
    }

    protected function getUser()
    {
        return app()->auth()->user();
    }

    protected function redirectAdmin($path = ''): void
    {
        app()->response()->redirect(base_url('/admin/' . ltrim($path, '/')));
    }

    /**
     * Register an AJAX action on the dispatcher.
     *
     * @param string   $name    Action name (e.g. 'uploads.upload')
     * @param callable $handler function(\Http\Request $request, bool|string $access): mixed
     * @param array    $options See \Ajax\AjaxDispatcher::register()
     */
    protected function ajaxAction(string $name, callable $handler, array $options = []): void
    {
        app()->ajax()->register($name, $handler, $options);
    }

    /**
     * Register a hook listener.
     */
    protected function hook($hookName, $callback, $priority = 10)
    {
        return Application::getInstance()->hooks()->register($hookName, $callback, $priority);
    }

    /**
     * Fire a hook.
     */
    protected function fireHook($hookName, $data = null, $context = null)
    {
        return Application::getInstance()->hooks()->fire($hookName, $data, $context);
    }

    /**
     * Get panel asset URL.
     */
    public function asset($path)
    {
        $path = ltrim($path, '/');
        $version = $this->metadata['version'] ?? '';
        $url = '/modules/admin/panels/' . $this->id() . '/assets/' . $path;
        return $version !== '' ? $url . '?v=' . urlencode($version) : $url;
    }

    /**
     * Get the panel filesystem path.
     */
    public function getPath()
    {
        return $this->panelPath;
    }

    /**
     * Get the panel metadata.
     */
    public function getMetadata()
    {
        return $this->metadata;
    }
}
