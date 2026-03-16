<?php
/**
 * AdminModule - Admin panel functionality
 */

class AdminModule extends Module {

    private $adminSubmodules = array();

    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    private function isAdminRequest() {
        $path = request()->path();
        return strpos($path, '/admin') === 0;
    }

    /**
     * Validate admin-submodule name to prevent path traversal.
     */
    private function assertValidAdminSubmoduleName($name) {
        $name = (string)$name;
        if ($name === '' || !preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new Exception("Invalid admin submodule name: '{$name}'");
        }
    }

    /**
     * Load admin-only submodules from modules/admin-modules/*
     */
    private function loadAdminSubmodules($router) {
        if (!$this->isAdminRequest()) {
            return;
        }

        $baseDir = MANTRA_MODULES . '/admin-modules';
        if (!is_dir($baseDir)) {
            return;
        }

        foreach (glob($baseDir . '/*/module.json') as $manifestPath) {
            $dir = basename(dirname($manifestPath));
            $this->assertValidAdminSubmoduleName($dir);

            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                logger()->warning('Invalid admin submodule manifest', array('path' => $manifestPath));
                continue;
            }

            // Enforce explicit dependency on the admin host module.
            // Admin-submodules must declare: "requires": ["admin"]
            $requires = isset($manifest['requires']) ? $manifest['requires'] : null;
            if (!is_array($requires) || !in_array('admin', $requires, true)) {
                logger()->warning('Admin submodule missing required dependency', array(
                    'path' => $manifestPath,
                    'required' => 'admin',
                ));
                continue;
            }

            $id = isset($manifest['id']) ? (string)$manifest['id'] : $dir;
            $this->assertValidAdminSubmoduleName($id);

            if (!empty($this->adminSubmodules[$id])) {
                continue;
            }

            $mainFile = isset($manifest['main']) && is_string($manifest['main'])
                ? $manifest['main']
                : (ucfirst($id) . 'AdminModule.php');

            $mainPath = dirname($manifestPath) . '/' . $mainFile;
            if (!file_exists($mainPath)) {
                logger()->warning('Admin submodule main file not found', array('id' => $id, 'path' => $mainPath));
                continue;
            }

            require_once MANTRA_MODULES . '/admin/AdminSubmodule.php';
            require_once $mainPath;

            $className = isset($manifest['class']) && is_string($manifest['class'])
                ? $manifest['class']
                : (ucfirst($id) . 'AdminModule');

            if (!class_exists($className)) {
                logger()->warning('Admin submodule class not found', array('id' => $id, 'class' => $className));
                continue;
            }

            $instance = null;
            try {
                $ref = new ReflectionClass($className);
                $ctor = $ref->getConstructor();
                if ($ctor === null) {
                    $instance = $ref->newInstance();
                } else {
                    $argc = $ctor->getNumberOfParameters();
                    if ($argc >= 2) {
                        $instance = $ref->newInstanceArgs(array($manifest, $this));
                    } elseif ($argc === 1) {
                        $instance = $ref->newInstanceArgs(array($manifest));
                    } else {
                        $instance = $ref->newInstance();
                    }
                }
            } catch (Exception $e) {
                logger()->warning('Failed to instantiate admin submodule', array('id' => $id, 'class' => $className, 'error' => $e->getMessage()));
                continue;
            }

            if (!($instance instanceof AdminSubmodule)) {
                logger()->warning('Admin submodule does not implement AdminSubmodule', array('id' => $id, 'class' => $className));
                continue;
            }

            $instance->init($this);

            $this->adminSubmodules[$id] = array(
                'instance' => $instance,
                'manifest' => $manifest,
                'path' => dirname($manifestPath),
            );
        }
    }

    public function getAdminSubmodule($id) {
        return isset($this->adminSubmodules[$id]) ? $this->adminSubmodules[$id]['instance'] : null;
    }

    public function adminRoute($method, $pattern, $callback) {
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
    public function registerRoutes($data) {
        $router = $data['router'];

        // Admin-only submodules (admin -> its modules)
        $this->loadAdminSubmodules($router);

        // Auth
        $router->get('/admin/login', array($this, 'loginForm'));
        $router->post('/admin/login', array($this, 'loginProcess'));
        $router->get('/admin/logout', array($this, 'logout'));


        // (admin-modules register their own /admin/* routes via $admin->adminRoute(...))

        return $data;
    }

    private function resolveAdminString($spec) {
        if (is_string($spec)) {
            return t($spec);
        }
        if (is_array($spec) && isset($spec['key'])) {
            $key = (string)$spec['key'];
            $translated = t($key);
            if ($translated === $key && isset($spec['fallback']) && is_string($spec['fallback'])) {
                return $spec['fallback'];
            }
            return $translated;
        }
        return '';
    }

    private function normalizeSidebarItem($item) {
        if (!is_array($item)) {
            $item = array();
        }

        $id = isset($item['id']) ? (string)$item['id'] : '';
        $item['id'] = $id;

        if (isset($item['title'])) {
            $item['title'] = $this->resolveAdminString($item['title']);
        }
        if (isset($item['group'])) {
            $item['group'] = $this->resolveAdminString($item['group']);
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

    private function sortSidebarTree(&$items) {
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

    private function computeSidebarActive(&$item, $path) {
        $selfMatch = false;
        $childMatch = false;

        $id = isset($item['id']) ? (string)$item['id'] : '';
        $url = isset($item['url']) ? (string)$item['url'] : '';

        if ($id !== '') {
            $prefix = '/admin/' . $id;
            if (strpos($path, $prefix) === 0) {
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
                } elseif (strpos($path, $parsed) === 0) {
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

    private function buildSidebarItems() {
        $items = $this->fireHook('admin.sidebar', array());
        if (!is_array($items)) {
            $items = array();
        }

        $path = request()->path();

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

    public function render($title, $content, $extra = array()) {
        return $this->renderAdminLayout($title, $content, $extra);
    }

    public function getSidebarItems() {
        return $this->buildSidebarItems();
    }

    private function renderAdminLayout($title, $content, $extra = array()) {
        $view = new View();
        $data = array_merge(array(
            'title' => $title,
            'content' => $content,
            'sidebarItems' => $this->buildSidebarItems(),
            'user' => auth()->user(),
        ), is_array($extra) ? $extra : array());

        return $view->render('admin:layout', $data);
    }

    private function admin404($message) {
        http_response_code(404);
        $html = '<div class="alert alert-danger">' . e($message) . '</div>';
        return $this->renderAdminLayout('Not found', $html);
    }

    // Settings UI has been moved to the Settings admin-submodule (modules/admin-modules/settings).
    // Keeping AdminModule as the host/platform (layout, auth, sidebar, adminRoute).

    // (settings implementation removed)



    /**
     * Auth middleware
     */
    public function requireAuth() {
        if (!auth()->check()) {
            redirect(base_url('/admin/login'));
            return false;
        }
        return true;
    }

    // Dashboard route is registered by the dashboard admin-submodule.

    // (requireAuth is defined above with admin layout rendering)

    /**
     * Login form
     */
    public function loginForm() {
        if (auth()->check()) {
            redirect(base_url('/admin'));
            return;
        }
        
        $this->view('admin:login', array());
    }
    
    /**
     * Process login
     */
    public function loginProcess() {
        $username = (string)request()->post('username', '');
        $password = (string)request()->post('password', '');
        
        if (auth()->login($username, $password)) {
            redirect(base_url('/admin'));
        } else {
            $this->view('admin:login', array(
                'error' => 'Invalid credentials'
            ));
        }
    }
    
    /**
     * Logout
     */
    public function logout() {
        auth()->logout();
        redirect(base_url('/admin/login'));
    }
    
}
