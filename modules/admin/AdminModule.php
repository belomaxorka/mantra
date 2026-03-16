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
        $base = MANTRA_MODULES;
        if (!is_dir($base)) {
            return $options;
        }

        foreach (glob($base . '/*/module.json') as $path) {
            $dir = basename(dirname($path));
            $meta = json_decode((string)file_get_contents($path), true);
            if (!is_array($meta)) {
                $meta = array();
            }

            // Allow modules to opt-out of being disable-able via config UI.
            // module.json: { "admin": { "disableable": false } }
            if (isset($meta['admin']) && is_array($meta['admin'])) {
                if (array_key_exists('disableable', $meta['admin']) && $meta['admin']['disableable'] === false) {
                    continue;
                }
            }

            $title = $dir;
            if (!empty($meta['name']) && is_string($meta['name'])) {
                $title = (string)$meta['name'];
            }
            $options[$dir] = $title;
        }

        ksort($options);
        return $options;
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

    private function generalSchema() {
        // Minimal schema to drive admin UI widgets for core config.
        return array(
            'locale.default_language' => array(
                'type' => 'select',
                'options' => $this->availableLocaleOptions(),
            ),
            'locale.fallback_locale' => array(
                'type' => 'select',
                'options' => $this->availableLocaleOptions(),
            ),
            'theme.active' => array(
                'type' => 'select',
                'options' => $this->availableThemeOptions(),
            ),
            'content.format' => array(
                'type' => 'select',
                'options' => array(
                    'json' => 'JSON',
                    'markdown' => 'Markdown',
                ),
            ),
            'security.password_hash_algo' => array(
                'type' => 'select',
                'options' => array(
                    'PASSWORD_DEFAULT' => 'PASSWORD_DEFAULT',
                    'PASSWORD_BCRYPT' => 'PASSWORD_BCRYPT',
                    'PASSWORD_ARGON2I' => 'PASSWORD_ARGON2I',
                    'PASSWORD_ARGON2ID' => 'PASSWORD_ARGON2ID',
                ),
            ),
            'logging.level' => array(
                'type' => 'select',
                'options' => array(
                    Logger::DEBUG => 'debug',
                    Logger::INFO => 'info',
                    Logger::NOTICE => 'notice',
                    Logger::WARNING => 'warning',
                    Logger::ERROR => 'error',
                    Logger::CRITICAL => 'critical',
                    Logger::ALERT => 'alert',
                    Logger::EMERGENCY => 'emergency',
                ),
            ),
            'modules.enabled' => array(
                'type' => 'checklist',
                'options' => $this->availableModuleOptions(),
            ),
        );
    }

    private function buildGeneralFields() {
        $defaultsNested = Config::defaults();
        $defaults = Config::flattenPaths($defaultsNested);
        $values = config()->all();
        $schema = $this->generalSchema();

        $groups = array();

        foreach ($defaults as $path => $defaultVal) {
            $groupId = $this->configGroupForKey($path);
            if (!isset($groups[$groupId])) {
                $groups[$groupId] = array(
                    'id' => $groupId,
                    'title' => $this->translateOrFallback('admin.settings.group.' . $groupId, $this->humanizeKey($groupId)),
                    'fields' => array(),
                );
            }

            $currentVal = Config::getNested($values, $path, $defaultVal);

            $type = 'text';
            $options = null;
            if (isset($schema[$path]) && is_array($schema[$path]) && !empty($schema[$path]['type'])) {
                $type = (string)$schema[$path]['type'];
                if (!empty($schema[$path]['options']) && is_array($schema[$path]['options'])) {
                    $options = $schema[$path]['options'];
                }
            } else {
                if (is_bool($defaultVal)) {
                    $type = 'toggle';
                } elseif (is_int($defaultVal) || is_float($defaultVal)) {
                    $type = 'number';
                } elseif (is_array($defaultVal)) {
                    $type = 'list';
                }
            }

            $labelKey = 'admin.settings.' . $path;
            $helpKey = $labelKey . '.help';

            $field = array(
                'key' => $path,
                'name' => $path,
                'type' => $type,
                'title' => $this->translateOrFallback($labelKey, $this->humanizeKey($path)),
                'help' => $this->translateOrFallback($helpKey, ''),
                'value' => $currentVal,
                'default' => $defaultVal,
            );

            if (is_array($options)) {
                $field['options'] = $options;
            }

            // If help key not translated, keep empty.
            if ($field['help'] === $helpKey) {
                $field['help'] = '';
            }

            $groups[$groupId]['fields'][] = $field;
        }

        $order = array('site', 'locale', 'theme', 'content', 'modules', 'security', 'session', 'cache', 'logging', 'proxy', 'debug', 'advanced');
        $sorted = array();
        foreach ($order as $gid) {
            if (isset($groups[$gid])) {
                $sorted[] = $groups[$gid];
                unset($groups[$gid]);
            }
        }
        foreach ($groups as $g) {
            $sorted[] = $g;
        }

        return $sorted;
    }

    private function handleGeneralPost(&$notice, &$error) {
        $token = (string)request()->post('csrf_token', '');
        if (!auth()->verifyCsrfToken($token)) {
            $error = 'Invalid CSRF token';
            return;
        }

        $defaultsNested = Config::defaults();
        $defaults = Config::flattenPaths($defaultsNested);
        $updates = array();

        foreach ($defaults as $path => $defaultVal) {
            $posted = request()->post($path, null);

            if (is_bool($defaultVal)) {
                $updates[$path] = $posted ? true : false;
                continue;
            }

            if ($posted === null) {
                continue;
            }

            if (is_int($defaultVal)) {
                $updates[$path] = (int)$posted;
                continue;
            }

            if (is_float($defaultVal)) {
                $updates[$path] = (float)$posted;
                continue;
            }

            if (is_array($defaultVal)) {
                // Support checklist widgets that post arrays (e.g. modules.enabled[])
                if (is_array($posted)) {
                    $items = array();
                    foreach ($posted as $item) {
                        $item = trim((string)$item);
                        if ($item !== '') {
                            $items[] = $item;
                        }
                    }
                    $updates[$path] = array_values(array_unique($items));
                    continue;
                }

                // Default list widget posts textarea lines.
                $raw = (string)$posted;
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
                continue;
            }

            $updates[$path] = (string)$posted;
        }

        // Auto-prune removed modules from modules.enabled to avoid keeping stale IDs
        // when a module directory was deleted from disk.
        if (isset($updates['modules.enabled']) && is_array($updates['modules.enabled'])) {
            $existing = array();
            $base = MANTRA_MODULES;
            if (is_dir($base)) {
                foreach (glob($base . '/*/module.json') as $path) {
                    $dir = basename(dirname($path));
                    if ($dir !== '') {
                        $existing[$dir] = true;
                    }
                }
            }

            $pruned = array();
            foreach ($updates['modules.enabled'] as $id) {
                $id = (string)$id;
                if (isset($existing[$id])) {
                    $pruned[] = $id;
                }
            }
            $updates['modules.enabled'] = $pruned;
        }

        config()->setMultiple($updates);
        $notice = 'Settings saved';
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

        // Admin shell
        $router->get('/admin', array($this, 'dashboard'))
               ->middleware(array($this, 'requireAuth'));

        // Unified settings
        $router->get('/admin/settings', array($this, 'settings'))
               ->middleware(array($this, 'requireAuth'));
        $router->post('/admin/settings', array($this, 'settings'))
               ->middleware(array($this, 'requireAuth'));

        // Legacy routes (keep during transition)
        // IMPORTANT: keep these before the generic /admin/{module} dispatcher
        // so that explicit routes like /admin/pages do not get captured and redirected in a loop.
        $router->get('/admin/pages', array($this, 'listPages'))
               ->middleware(array($this, 'requireAuth'));
        $router->get('/admin/pages/create', array($this, 'createPage'))
               ->middleware(array($this, 'requireAuth'));
        $router->post('/admin/pages/save', array($this, 'savePage'))
               ->middleware(array($this, 'requireAuth'));

        // New dispatcher routes
        $router->get('/admin/{module}', array($this, 'dispatchModuleIndex'))
               ->middleware(array($this, 'requireAuth'));
        $router->any('/admin/{module}/settings', array($this, 'dispatchModuleSettings'))
               ->middleware(array($this, 'requireAuth'));

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

    private function buildModuleSettingsContent($module, $actionUrl, &$notice, &$error) {
        $module = (string)$module;

        $store = module_settings($module);
        $schema = $store->schema();
        if (!is_array($schema)) {
            $error = 'This module has no settings';
            return null;
        }

        $store->load(); // materialize defaults

        if (request()->method() === 'POST') {
            $token = (string)request()->post('csrf_token', '');
            if (!auth()->verifyCsrfToken($token)) {
                $error = 'Invalid CSRF token';
            } else {
                $updates = array();

                foreach (($schema['tabs'] ?? array()) as $tab) {
                    foreach (($tab['fields'] ?? array()) as $field) {
                        if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                            continue;
                        }

                        $path = (string)$field['path'];
                        $name = str_replace('.', '__', $path);
                        $type = (string)$field['type'];

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
                        } else {
                            // text / textarea
                            $updates[$path] = (string)$raw;
                        }
                    }
                }

                $store->setMultiple($updates);
                $store->save();
                $notice = 'Settings saved';
            }
        }

        $tabs = array();
        foreach (($schema['tabs'] ?? array()) as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
            $tabTitle = $this->resolveAdminString($tab['title'] ?? $tab['label'] ?? $tabId);

            $fields = array();
            foreach (($tab['fields'] ?? array()) as $field) {
                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                    continue;
                }

                $path = (string)$field['path'];
                $name = str_replace('.', '__', $path);

                $f = array(
                    'path' => $path,
                    'name' => $name,
                    'type' => (string)$field['type'],
                    'title' => $this->resolveAdminString($field['title'] ?? $field['label'] ?? $path),
                    'help' => isset($field['help']) ? $this->resolveAdminString($field['help']) : '',
                    'value' => $store->get($path, array_key_exists('default', $field) ? $field['default'] : null),
                    'options' => isset($field['options']) && is_array($field['options']) ? $field['options'] : array(),
                );

                // Resolve option labels if they are i18n specs.
                if ($f['type'] === 'select' && is_array($f['options'])) {
                    foreach ($f['options'] as $k => $v) {
                        $f['options'][$k] = $this->resolveAdminString($v);
                    }
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
            'action' => $actionUrl,
            'csrf_token' => auth()->generateCsrfToken(),
            'notice' => $notice,
            'error' => $error,
        ));
    }

    private function buildGeneralSettingsContent(&$notice, &$error) {
        if (request()->method() === 'POST') {
            $this->handleGeneralPost($notice, $error);
        }

        $groups = $this->buildGeneralFields();

        $view = new View();
        return $view->fetch('admin:settings-general', array(
            'groups' => $groups,
            'action' => base_url('/admin/settings?tab=general'),
            'csrf_token' => auth()->generateCsrfToken(),
        ));
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

        foreach ($enabled as $module) {
            $module = (string)$module;
            if ($module === '' || $module === 'admin') {
                continue;
            }

            $schema = module_settings($module)->schema();
            if (!is_array($schema)) {
                continue;
            }

            $tabs[] = array(
                'id' => $module,
                'title' => ucfirst($module),
                'url' => base_url('/admin/settings?tab=' . $module),
                'active' => ($activeTab === $module),
            );
        }

        $notice = null;
        $error = null;

        $contentHtml = '';
        if ($activeTab === 'general') {
            $contentHtml = $this->buildGeneralSettingsContent($notice, $error);
        } else {
            $contentHtml = $this->buildModuleSettingsContent($activeTab, base_url('/admin/settings?tab=' . $activeTab), $notice, $error);
            if ($contentHtml === null) {
                return $this->admin404('This module has no settings');
            }
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

    public function dispatchModuleIndex($params) {
        $module = isset($params['module']) ? (string)$params['module'] : '';
        if ($module === '' || $module === 'admin') {
            redirect(base_url('/admin'));
            return;
        }

        $instance = app()->modules()->getModule($module);
        if (!$instance) {
            return $this->admin404('Module not found');
        }

        if (method_exists($instance, 'adminIndex')) {
            return $instance->adminIndex();
        }

        $schema = module_settings($module)->schema();
        if (is_array($schema)) {
            redirect(base_url('/admin/settings?tab=' . $module));
            return;
        }

        return $this->admin404('No admin screen available for this module');
    }

    public function dispatchModuleSettings($params) {
        $module = isset($params['module']) ? (string)$params['module'] : '';
        if ($module === '' || $module === 'admin') {
            return $this->admin404('Invalid module');
        }

        // Unified settings page (legacy redirect)
        redirect(base_url('/admin/settings?tab=' . $module));
        return;
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

    /**
     * Dashboard
     */
    public function dashboard() {
        $view = new View();
        $content = $view->fetch('admin:dashboard', array(
            'user' => auth()->user()
        ));

        return $this->renderAdminLayout('Dashboard', $content, array(
            'user' => auth()->user(),
        ));
    }
    
    // (requireAuth and dashboard are defined above with admin layout rendering)
    
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
    
    /**
     * List pages
     */
    public function listPages() {
        $db = new Database();
        $pages = $db->query('pages', array(), array('sort' => 'created_at', 'order' => 'desc'));
        
        $this->view('admin:pages', array(
            'pages' => $pages
        ));
    }
    
    /**
     * Create page form
     */
    public function createPage() {
        $this->view('admin:page-edit', array(
            'page' => null
        ));
    }
    
    /**
     * Save page
     */
    public function savePage() {
        $db = new Database();
        
        $id = (string)request()->post('id', '');
        if ($id === '') {
            $id = $db->generateId();
        }

        $title = (string)request()->post('title', '');

        $data = array(
            'title' => $title,
            'slug' => slugify($title),
            'content_html' => (string)request()->post('content', ''),
            'content_type' => 'html',
            'status' => (string)request()->post('status', 'draft'),
            'lang' => (string)request()->post('lang', 'en')
        );
        
        if ($db->write('pages', $id, $data)) {
            redirect(base_url('/admin/pages'));
        } else {
            echo 'Error saving page';
        }
    }
}
