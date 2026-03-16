<?php
/**
 * AdminModule - Admin panel functionality
 */

class AdminModule extends Module {

    private function humanizeKey($key) {
        $key = (string)$key;
        $key = str_replace(array('-', '_'), ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        return ucwords($key);
    }

    private function translateOrFallback($key, $fallback) {
        $key = (string)$key;
        $translated = t($key);
        if ($translated === $key) {
            return (string)$fallback;
        }
        return $translated;
    }

    private function configGroupForKey($path) {
        $path = trim((string)$path);
        if ($path === '') {
            return 'advanced';
        }

        $parts = explode('.', $path);
        $group = (string)array_shift($parts);
        return $group !== '' ? $group : 'advanced';
    }

    private function availableThemeOptions() {
        $options = array();
        $base = MANTRA_THEMES;
        if (!is_dir($base)) {
            return $options;
        }

        foreach (glob($base . '/*/theme.json') as $path) {
            $dir = basename(dirname($path));
            $meta = json_decode((string)file_get_contents($path), true);
            $name = $dir;
            if (is_array($meta) && !empty($meta['name']) && is_string($meta['name'])) {
                $name = (string)$meta['name'];
            }
            $options[$dir] = $name;
        }

        ksort($options);
        return $options;
    }

    private function availableModuleOptions() {
        $options = array();
        foreach ($this->availableModuleCards() as $m) {
            if (empty($m['id'])) {
                continue;
            }
            $options[(string)$m['id']] = (string)($m['title'] ?? $m['id']);
        }
        ksort($options);
        return $options;
    }

    private function isValidModuleName($name) {
        $name = (string)$name;
        return ($name !== '' && preg_match('/^[a-z0-9_-]+$/', $name));
    }

    private function adminModulePolicy($manifest) {
        if (!is_array($manifest)) {
            $manifest = array();
        }
        $admin = isset($manifest['admin']) && is_array($manifest['admin']) ? $manifest['admin'] : array();

        $disableable = true;
        if (array_key_exists('disableable', $admin) && $admin['disableable'] === false) {
            $disableable = false;
        }

        $deletable = true;
        if (array_key_exists('deletable', $admin) && $admin['deletable'] === false) {
            $deletable = false;
        }

        return array(
            'disableable' => $disableable,
            'deletable' => $deletable,
        );
    }

    private function availableModuleCards() {
        $cards = array();
        $base = MANTRA_MODULES;
        if (!is_dir($base)) {
            return $cards;
        }

        $enabled = array('admin');

        foreach (glob($base . '/*/module.json') as $path) {
            $dir = basename(dirname($path));
            if (!$this->isValidModuleName($dir)) {
                continue;
            }

            $meta = json_decode((string)file_get_contents($path), true);
            if (!is_array($meta)) {
                $meta = array();
            }

            $policy = $this->adminModulePolicy($meta);

            $title = $dir;
            if (!empty($meta['name']) && is_string($meta['name'])) {
                $title = (string)$meta['name'];
            }

            $version = '';
            if (!empty($meta['version']) && is_string($meta['version'])) {
                $version = (string)$meta['version'];
            }

            $author = '';
            if (!empty($meta['author']) && is_string($meta['author'])) {
                $author = (string)$meta['author'];
            }

            $homepage = '';
            if (!empty($meta['homepage']) && is_string($meta['homepage'])) {
                $homepage = (string)$meta['homepage'];
            }

            $description = '';
            if (!empty($meta['description']) && is_string($meta['description'])) {
                $description = (string)$meta['description'];
            }

            $hasSettings = false;
            // Front-end module settings are currently not used; admin-only submodules handle their own UI.
            // Keep the field for potential future use.
            $schema = null;
            if (is_array($schema)) {
                $hasSettings = true;
            }

            $cards[] = array(
                'id' => $dir,
                'title' => $title,
                'version' => $version,
                'author' => $author,
                'homepage' => $homepage,
                'description' => $description,
                'enabled' => in_array($dir, $enabled, true),
                'has_settings' => $hasSettings,
                'disableable' => !empty($policy['disableable']),
                'deletable' => !empty($policy['deletable']),
            );
        }

        usort($cards, function ($a, $b) {
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $cards;
    }

    private function collectModuleDependencyGraph() {
        $graph = array();
        $base = MANTRA_MODULES;
        if (!is_dir($base)) {
            return $graph;
        }

        foreach (glob($base . '/*/module.json') as $path) {
            $dir = basename(dirname($path));
            if (!$this->isValidModuleName($dir)) {
                continue;
            }

            $meta = json_decode((string)file_get_contents($path), true);
            if (!is_array($meta)) {
                $meta = array();
            }

            $deps = array();
            if (isset($meta['dependencies']) && is_array($meta['dependencies'])) {
                foreach ($meta['dependencies'] as $d) {
                    if (!is_string($d)) {
                        continue;
                    }
                    $d = trim((string)$d);
                    if ($d !== '' && $this->isValidModuleName($d)) {
                        $deps[] = $d;
                    }
                }
            }

            $graph[$dir] = array_values(array_unique($deps));
        }

        return $graph;
    }

    private function dependsOnTransitive($start, $target, $graph) {
        $start = (string)$start;
        $target = (string)$target;
        if ($start === '' || $target === '' || $start === $target) {
            return false;
        }

        $visited = array();
        $stack = array($start);

        while (!empty($stack)) {
            $cur = array_pop($stack);
            if (isset($visited[$cur])) {
                continue;
            }
            $visited[$cur] = true;

            $deps = isset($graph[$cur]) && is_array($graph[$cur]) ? $graph[$cur] : array();
            foreach ($deps as $d) {
                if ($d === $target) {
                    return true;
                }
                if (!isset($visited[$d])) {
                    $stack[] = $d;
                }
            }
        }

        return false;
    }

    private function rrmdirSafe($dirPath) {
        $dirPath = (string)$dirPath;
        if ($dirPath === '' || !is_dir($dirPath)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS);
        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dirPath);
    }

    private function availableLocaleOptions() {
        $locales = array();

        // Modules
        $modBase = MANTRA_MODULES;
        if (is_dir($modBase)) {
            foreach (glob($modBase . '/*/lang/*.php') as $path) {
                $locale = pathinfo($path, PATHINFO_FILENAME);
                if ($locale !== '') {
                    $locales[$locale] = strtoupper($locale);
                }
            }
        }

        // Themes
        $themeBase = MANTRA_THEMES;
        if (is_dir($themeBase)) {
            foreach (glob($themeBase . '/*/lang/*.php') as $path) {
                $locale = pathinfo($path, PATHINFO_FILENAME);
                if ($locale !== '') {
                    $locales[$locale] = strtoupper($locale);
                }
            }
        }

        if (empty($locales)) {
            $locales = array('en' => 'EN');
        }

        ksort($locales);
        return $locales;
    }


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


        // Unified settings
        $router->get('/admin/settings', array($this, 'settings'))
               ->middleware(array($this, 'requireAuth'));
        $router->post('/admin/settings', array($this, 'settings'))
               ->middleware(array($this, 'requireAuth'));

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

    private function applyConfigSchemaRuntimeOptions($schema) {
        if (!is_array($schema)) {
            return $schema;
        }

        if (empty($schema['tabs']) || !is_array($schema['tabs'])) {
            return $schema;
        }

        foreach ($schema['tabs'] as &$tab) {
            if (empty($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }

            foreach ($tab['fields'] as &$field) {
                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                    continue;
                }

                $path = (string)$field['path'];

                if ($path === 'locale.default_language' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableLocaleOptions();
                }

                if ($path === 'locale.fallback_locale' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableLocaleOptions();
                }

                if ($path === 'theme.active' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableThemeOptions();
                }

                if ($path === 'logging.level' && (string)$field['type'] === 'select') {
                    $field['options'] = array(
                        Logger::DEBUG => 'debug',
                        Logger::INFO => 'info',
                        Logger::NOTICE => 'notice',
                        Logger::WARNING => 'warning',
                        Logger::ERROR => 'error',
                        Logger::CRITICAL => 'critical',
                        Logger::ALERT => 'alert',
                        Logger::EMERGENCY => 'emergency',
                    );
                }

                if ($path === 'modules.enabled' && (string)$field['type'] === 'module_cards') {
                    $field['options'] = $this->availableModuleCards();
                }
            }
            unset($field);
        }
        unset($tab);

        return $schema;
    }

    private function handleConfigDeleteModuleAction(&$notice, &$error) {
        $deleteId = (string)request()->post('module_delete', '');
        if ($deleteId === '') {
            return false;
        }

        if (!$this->isValidModuleName($deleteId)) {
            $error = 'Invalid module name';
            return true;
        }

        $manifestPath = MANTRA_MODULES . '/' . $deleteId . '/module.json';
        $manifest = array();
        if (file_exists($manifestPath)) {
            $tmp = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($tmp)) {
                $manifest = $tmp;
            }
        }

        $policy = $this->adminModulePolicy($manifest);
        if (empty($policy['deletable'])) {
            $error = 'This module cannot be deleted';
            return true;
        }

        $enabled = array('admin');

        if (in_array($deleteId, $enabled, true)) {
            $error = 'Disable the module before deleting it';
            return true;
        }

        // Block deletion if any enabled module depends on this one (transitively).
        $graph = $this->collectModuleDependencyGraph();
        foreach ($enabled as $m) {
            if ($m === '' || $m === $deleteId) {
                continue;
            }
            if ($this->dependsOnTransitive($m, $deleteId, $graph)) {
                $error = "Cannot delete module '{$deleteId}': required by '{$m}'";
                return true;
            }
        }

        // Delete module settings config if present.
        $settingsPath = MANTRA_CONTENT . '/settings/' . $deleteId . '.json';
        if (file_exists($settingsPath)) {
            @unlink($settingsPath);
        }

        // Delete module folder (defense-in-depth: ensure it stays under MANTRA_MODULES).
        $moduleDir = MANTRA_MODULES . '/' . $deleteId;
        $realModules = realpath(MANTRA_MODULES);
        $realModuleDir = realpath($moduleDir);
        if ($realModules && $realModuleDir && strpos($realModuleDir, $realModules) === 0) {
            $this->rrmdirSafe($realModuleDir);
        }

        // Ensure it's pruned from enabled list if it was present.
        $newEnabled = array_values(array_diff($enabled, array($deleteId)));
        config_settings()->set('modules.enabled', $newEnabled);
        config_settings()->save();

        $notice = "Module '{$deleteId}' deleted";
        return true;
    }

    private function buildSchemaSettingsContent($store, $schema, $actionUrl, &$notice, &$error, $context = array()) {
        if (!is_array($schema)) {
            $error = 'This module has no settings';
            return null;
        }

        if (is_array($context) && !empty($context['schema_mutator']) && is_callable($context['schema_mutator'])) {
            $schema = call_user_func($context['schema_mutator'], $schema);
        }

        if (method_exists($store, 'load')) {
            $store->load();
        }

        if (request()->method() === 'POST') {
            $token = (string)request()->post('csrf_token', '');
            if (!auth()->verifyCsrfToken($token)) {
                $error = 'Invalid CSRF token';
            } else {
                $handledAction = false;
                if (is_array($context) && !empty($context['on_post']) && is_callable($context['on_post'])) {
                    // Need call_user_func_array to preserve &reference parameters.
                    $args = array(&$notice, &$error);
                    $handledAction = (bool)call_user_func_array($context['on_post'], $args);
                }

                if (empty($error) && !$handledAction) {
                    $updates = array();

                    foreach (($schema['tabs'] ?? array()) as $tab) {
                        foreach (($tab['fields'] ?? array()) as $field) {
                            if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                                continue;
                            }

                            $path = (string)$field['path'];
                            $type = (string)$field['type'];

                            // module_cards posts its own name (modules.enabled[])
                            if ($type === 'module_cards') {
                                $posted = request()->post($path, null);
                                if (is_array($posted)) {
                                    $items = array();
                                    foreach ($posted as $item) {
                                        $item = trim((string)$item);
                                        if ($item !== '') {
                                            $items[] = $item;
                                        }
                                    }
                                    $updates[$path] = array_values(array_unique($items));
                                }
                                continue;
                            }

                            $name = str_replace('.', '__', $path);

                            if ($type === 'toggle') {
                                $updates[$path] = request()->post($name) ? true : false;
                                continue;
                            }

                            $raw = request()->post($name, null);
                            if ($raw === null) {
                                continue;
                            }

                            if ($type === 'number') {
                                $updates[$path] = (int)$raw;
                            } elseif ($type === 'select') {
                                $val = (string)$raw;
                                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();
                                if (array_key_exists($val, $options)) {
                                    $updates[$path] = $val;
                                }
                            } elseif ($type === 'textarea') {
                                // Keep as string; view may display arrays as lines, but input posts string.
                                $updates[$path] = (string)$raw;
                            } else {
                                // text
                                $updates[$path] = (string)$raw;
                            }
                        }
                    }

                    if (!empty($updates)) {
                        // Special-case: textarea fields that represent lists in config.
                        // Schema can declare default as array; we accept textarea lines.
                        foreach (($schema['tabs'] ?? array()) as $tab) {
                            foreach (($tab['fields'] ?? array()) as $field) {
                                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                                    continue;
                                }
                                $path = (string)$field['path'];
                                if ((string)$field['type'] === 'textarea' && array_key_exists($path, $updates) && array_key_exists('default', $field) && is_array($field['default'])) {
                                    $raw = (string)$updates[$path];
                                    $lines = preg_split('/\r\n|\r|\n/', $raw);
                                    $lines = is_array($lines) ? $lines : array();
                                    $items = array();
                                    foreach ($lines as $line) {
                                        $line = trim((string)$line);
                                        if ($line !== '') {
                                            $items[] = $line;
                                        }
                                    }
                                    $updates[$path] = $items;
                                }
                            }
                        }

                        $store->setMultiple($updates);
                        $store->save();
                        $notice = 'Settings saved';
                    }
                }
            }
        }

        $activeInnerTab = (string)request()->query('section', '');
        if ($activeInnerTab === '') {
            $activeInnerTab = (string)request()->post('active_tab', '');
        }

        $tabs = array();
        foreach (($schema['tabs'] ?? array()) as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
            $tabTitle = $this->resolveAdminString($tab['title'] ?? $tab['label'] ?? $tabId);

            if ($activeInnerTab === '' && $tabId !== '') {
                $activeInnerTab = $tabId;
            }

            $fields = array();
            foreach (($tab['fields'] ?? array()) as $field) {
                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                    continue;
                }

                $path = (string)$field['path'];
                $type = (string)$field['type'];

                $name = $type === 'module_cards' ? $path : str_replace('.', '__', $path);

                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : array();

                $f = array(
                    'path' => $path,
                    'name' => $name,
                    'type' => $type,
                    'title' => $this->resolveAdminString($field['title'] ?? $field['label'] ?? $path),
                    'help' => isset($field['help']) ? $this->resolveAdminString($field['help']) : '',
                    'value' => $store->get($path, array_key_exists('default', $field) ? $field['default'] : null),
                    'options' => $options,
                );

                // Resolve option labels if they are i18n specs.
                if ($f['type'] === 'select' && is_array($f['options'])) {
                    foreach ($f['options'] as $k => $v) {
                        $f['options'][$k] = $this->resolveAdminString($v);
                    }
                }

                // Textarea convenience: allow arrays (lists) rendered as lines.
                if ($f['type'] === 'textarea' && is_array($f['value'])) {
                    $f['value'] = array_values($f['value']);
                }

                $fields[] = $f;
            }

            $tabs[] = array(
                'id' => $tabId,
                'title' => $tabTitle,
                'fields' => $fields,
            );
        }

        $view = new View();
        return $view->fetch('admin:module-settings', array(
            'title' => '',
            'tabs' => $tabs,
            'active_tab' => $activeInnerTab,
            'action' => $actionUrl,
            'csrf_token' => auth()->generateCsrfToken(),
            'notice' => $notice,
            'error' => $error,
        ));
    }

    private function buildConfigSettingsContent($actionUrl, &$notice, &$error) {
        $store = config_settings();
        $schema = $this->applyConfigSchemaRuntimeOptions($store->schema());
        return $this->buildSchemaSettingsContent(
            $store,
            $schema,
            $actionUrl,
            $notice,
            $error,
            array('on_post' => array($this, 'handleConfigDeleteModuleAction'))
        );
    }

    public function settings() {
        $activeTab = (string)request()->query('tab', 'general');
        if ($activeTab === '') {
            $activeTab = 'general';
        }

        $enabled = config('modules.enabled', array());
        if (!is_array($enabled)) {
            $enabled = array();
        }

        $tabs = array();
        $tabs[] = array(
            'id' => 'general',
            'title' => $this->translateOrFallback('admin.settings.general', 'General'),
            'url' => base_url('/admin/settings?tab=general'),
            'active' => ($activeTab === 'general'),
        );


        $notice = null;
        $error = null;

        $contentHtml = $this->buildConfigSettingsContent(base_url('/admin/settings?tab=general'), $notice, $error);
        if ($contentHtml === null) {
            return $this->admin404('Settings schema not found');
        }

        $view = new View();
        $page = $view->fetch('admin:settings', array(
            'pageTitle' => $this->translateOrFallback('admin.settings.title', 'Settings'),
            'tabs' => $tabs,
            'contentHtml' => $contentHtml,
            'notice' => $notice,
            'error' => $error,
        ));

        return $this->renderAdminLayout('Settings', $page);
    }


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
