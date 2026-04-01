<?php
/**
 * AdminModule - Admin panel functionality
 *
 * Central hub for the admin area. Manages authentication,
 * layout rendering, and panel discovery/loading.
 */

use Module\Module;

class AdminModule extends Module
{
    /** @var Admin\AdminPanelInterface[] */
    private $panels = array();

    public function init()
    {
        $this->registerPermissionService();
        $this->loadPanels();
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    /**
     * Register PermissionRegistry as a lazy service.
     * Panels and modules register their own permissions via the 'permissions.register' hook.
     */
    private function registerPermissionService()
    {
        $module = $this;
        $this->provide('permissions', function () use ($module) {
            $registry = new PermissionRegistry();
            // Let panels and other modules register their permissions
            $module->fireHook('permissions.register', $registry);
            return $registry;
        });
    }

    // ========== Panel Management ==========

    /**
     * Discover and load panels from modules/admin/panels/
     */
    private function loadPanels()
    {
        $panelsDir = $this->getPath() . '/panels';
        if (!is_dir($panelsDir)) {
            return;
        }

        $dirs = scandir($panelsDir);
        if (!is_array($dirs)) {
            return;
        }

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $panelPath = $panelsDir . '/' . $dir;
            if (!is_dir($panelPath)) {
                continue;
            }

            $metaPath = $panelPath . '/panel.json';
            if (!file_exists($metaPath)) {
                continue;
            }

            try {
                $metadata = JsonCodec::decode(file_get_contents($metaPath));
            } catch (Exception $e) {
                logger()->warning('Failed to read panel.json', array('panel' => $dir, 'error' => $e->getMessage()));
                continue;
            }

            $panelId = isset($metadata['id']) ? (string)$metadata['id'] : $dir;

            // Derive class name: "pages" → "Admin\PagesPanel"
            $parts = explode('-', $dir);
            $baseName = implode('', array_map('ucfirst', $parts)) . 'Panel';
            $className = 'Admin\\' . $baseName;

            $classFile = $panelPath . '/' . $baseName . '.php';
            if (!file_exists($classFile)) {
                logger()->warning('Panel class file not found', array('panel' => $dir, 'file' => $classFile));
                continue;
            }

            require_once $classFile;

            if (!class_exists($className)) {
                logger()->warning('Panel class not found', array('panel' => $dir, 'class' => $className));
                continue;
            }

            $panel = new $className($panelPath, $metadata);
            $panel->init($this);

            // Register translation domain: panel "pages" → domain "admin-pages"
            $langDir = $panelPath . '/lang';
            if (is_dir($langDir)) {
                app()->translator()->registerDomain('admin-' . $panelId, $langDir);
            }

            $this->panels[$panelId] = $panel;

            logger()->debug('Panel loaded', array('panel' => $panelId));
        }
    }

    /**
     * Get all loaded panels.
     * @return Admin\AdminPanelInterface[]
     */
    public function getPanels()
    {
        return $this->panels;
    }

    /**
     * Get a panel by ID.
     * @param string $id
     * @return Admin\AdminPanelInterface|null
     */
    public function getPanel($id)
    {
        return isset($this->panels[$id]) ? $this->panels[$id] : null;
    }

    public function adminRoute($method, $pattern, $callback)
    {
        $router = $this->app->router();
        $pattern = '/admin' . ($pattern === '' ? '' : ('/' . ltrim($pattern, '/')));

        if ($method === 'GET') {
            return $router->get($pattern, $callback)->middleware(array($this, 'requireAuth'));
        }
        if ($method === 'POST') {
            return $router->post($pattern, $callback)->middleware(array($this, 'requireAuth'));
        }
        return $router->any($pattern, $callback)->middleware(array($this, 'requireAuth'));
    }

    /**
     * Register admin routes
     */
    public function registerRoutes($data)
    {
        $router = $data['router'];

        // Auth routes (no middleware — public)
        $router->get('/admin/login', array($this, 'loginForm'));
        $router->post('/admin/login', array($this, 'loginProcess'));
        $router->post('/admin/logout', array($this, 'logout'));
        $router->get('/admin/logout', function() { app()->response()->redirect(base_url('/admin')); });

        // Panel routes (auth middleware applied by adminRoute())
        foreach ($this->panels as $panel) {
            $panel->registerRoutes($this);
        }

        return $data;
    }

    private function normalizeSidebarItem($item)
    {
        if (!is_array($item)) {
            $item = array();
        }

        $id = isset($item['id']) ? (string)$item['id'] : '';
        $item['id'] = $id;

        if (isset($item['title'])) {
            $item['title'] = t($item['title']);
        }
        if (isset($item['group'])) {
            $item['group'] = t($item['group']);
        }

        if (!isset($item['order'])) {
            $item['order'] = 100;
        }

        if (!isset($item['url']) || !is_string($item['url'])) {
            if ($id !== '') {
                $item['url'] = base_url('/admin/' . $id);
            } else {
                $item['url'] = '#';
            }
        }

        $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : array();
        $normalizedChildren = array();
        foreach ($children as $child) {
            $normalizedChildren[] = $this->normalizeSidebarItem($child);
        }
        $item['children'] = $normalizedChildren;

        return $item;
    }

    private function sortSidebarTree(&$items)
    {
        if (!is_array($items)) {
            $items = array();
            return;
        }

        usort($items, function ($a, $b) {
            $oa = isset($a['order']) ? (int)$a['order'] : 100;
            $ob = isset($b['order']) ? (int)$b['order'] : 100;
            if ($oa !== $ob) {
                return $oa - $ob;
            }

            $ta = isset($a['title']) ? (string)$a['title'] : '';
            $tb = isset($b['title']) ? (string)$b['title'] : '';
            return strcmp($ta, $tb);
        });

        foreach ($items as &$item) {
            if (isset($item['children']) && is_array($item['children'])) {
                $this->sortSidebarTree($item['children']);
            }
        }
        unset($item);
    }

    private function computeSidebarActive(&$item, $path)
    {
        $selfMatch = false;
        $childMatch = false;

        $id = isset($item['id']) ? (string)$item['id'] : '';
        $url = isset($item['url']) ? (string)$item['url'] : '';

        if ($id !== '') {
            $prefix = '/admin/' . $id;
            if (str_starts_with($path, $prefix)) {
                $selfMatch = true;
            }
        }

        // Also consider explicit URL match (best-effort)
        if (!$selfMatch && $url !== '' && $url !== '#') {
            $parsed = parse_url($url, PHP_URL_PATH);
            if (is_string($parsed) && $parsed !== '') {
                // Special case: /admin should only match the dashboard itself, not every /admin/* route.
                if ($parsed === '/admin') {
                    if ($path === '/admin' || $path === '/admin/') {
                        $selfMatch = true;
                    }
                } elseif (str_starts_with($path, $parsed)) {
                    $selfMatch = true;
                }
            }
        }

        if (!empty($item['children']) && is_array($item['children'])) {
            foreach ($item['children'] as &$child) {
                $childActive = $this->computeSidebarActive($child, $path);
                if ($childActive) {
                    $childMatch = true;
                }
            }
            unset($child);
        }

        // If this item has children, don't mark it as "active".
        // Instead, mark it as "expanded" when any child is active.
        $hasChildren = !empty($item['children']) && is_array($item['children']);

        $item['expanded'] = $hasChildren ? $childMatch : false;
        $item['active'] = $hasChildren ? false : $selfMatch;

        return $item['active'] || $item['expanded'];
    }

    private function buildSidebarItems()
    {
        // Collect from hooks (backward compat with BaseAdminModule / third-party modules)
        $items = $this->fireHook('admin.sidebar', array());
        if (!is_array($items)) {
            $items = array();
        }

        // Collect from panels (declarative)
        foreach ($this->panels as $panel) {
            $sb = $panel->getSidebarItem();
            if (is_array($sb) && !empty($sb)) {
                $items[] = $sb;
            }
        }

        $path = app()->request()->path();

        $normalized = array();
        foreach ($items as $item) {
            $normalized[] = $this->normalizeSidebarItem($item);
        }

        // Sort groups at top-level, then sort each group's subtree.
        usort($normalized, function ($a, $b) {
            $ga = isset($a['group']) ? (string)$a['group'] : '';
            $gb = isset($b['group']) ? (string)$b['group'] : '';
            if ($ga !== $gb) {
                return strcmp($ga, $gb);
            }

            $oa = isset($a['order']) ? (int)$a['order'] : 100;
            $ob = isset($b['order']) ? (int)$b['order'] : 100;
            if ($oa !== $ob) {
                return $oa - $ob;
            }

            $ta = isset($a['title']) ? (string)$a['title'] : '';
            $tb = isset($b['title']) ? (string)$b['title'] : '';
            return strcmp($ta, $tb);
        });

        foreach ($normalized as &$item) {
            if (isset($item['children']) && is_array($item['children'])) {
                $this->sortSidebarTree($item['children']);
            }
            $this->computeSidebarActive($item, $path);
        }
        unset($item);

        return $normalized;
    }

    public function render($title, $content, $extra = array())
    {
        return $this->renderAdminLayout($title, $content, $extra);
    }

    private function renderAdminLayout($title, $content, $extra = array())
    {
        $data = array_merge(array(
            'title' => $title,
            'content' => $content,
            'sidebarItems' => $this->buildSidebarItems(),
            'user' => app()->auth()->user(),
        ), is_array($extra) ? $extra : array());

        return app()->view()->render('admin:layout', $data);
    }

    /**
     * Auth middleware
     */
    public function requireAuth()
    {
        if (!app()->auth()->check()) {
            app()->response()->redirect(base_url('/admin/login'));
            return false;
        }
        return true;
    }

    /**
     * Login form
     */
    public function loginForm()
    {
        if (app()->auth()->check()) {
            app()->response()->redirect(base_url('/admin'));
            return;
        }

        $this->view('admin:login', array(
            'csrf_token' => app()->auth()->generateCsrfToken()
        ));
    }

    /**
     * Process login
     */
    public function loginProcess()
    {
        $token = (string)app()->request()->post('csrf_token', '');
        if (!app()->auth()->verifyCsrfToken($token)) {
            abort(403);
            return;
        }

        $username = (string)app()->request()->post('username', '');
        $password = (string)app()->request()->post('password', '');

        if (app()->auth()->login($username, $password)) {
            app()->response()->redirect(base_url('/admin'));
        } else {
            $this->view('admin:login', array(
                'error' => 'Invalid credentials',
                'csrf_token' => app()->auth()->generateCsrfToken()
            ));
        }
    }

    /**
     * Logout
     */
    public function logout()
    {
        $token = (string)app()->request()->post('csrf_token', '');
        if (!app()->auth()->verifyCsrfToken($token)) {
            abort(403);
            return;
        }

        app()->auth()->logout();
        app()->response()->redirect(base_url('/admin/login'));
    }
}
