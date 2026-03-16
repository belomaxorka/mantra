<?php

class SettingsAdminModule implements AdminSubmodule
{
    public function __construct($manifest = array(), $admin = null)
    {
    }

    public function getId()
    {
        return 'settings';
    }

    public function init($admin)
    {
        // Sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'settings',
                'title' => array('key' => 'admin.settings.title', 'fallback' => 'Settings'),
                'icon' => 'bi-sliders',
                'group' => array('key' => 'admin.sidebar.group.system', 'fallback' => 'System'),
                'order' => 50,
                'url' => base_url('/admin/settings'),
            );

            return $items;
        });

        if (is_object($admin) && method_exists($admin, 'adminRoute')) {
            $admin->adminRoute('GET', 'settings', array($this, 'settings'));
            $admin->adminRoute('POST', 'settings', array($this, 'settings'));
        }
    }

    public function settings()
    {
        $admin = app()->modules()->getModule('admin');
        if (!$admin || !method_exists($admin, 'render')) {
            http_response_code(500);
            echo 'Admin module not loaded';
            return;
        }

        $tabs = array();
        $tabs[] = array(
            'id' => 'general',
            'title' => $this->translateOrFallback('admin.settings.general', 'General'),
            'url' => base_url('/admin/settings?tab=general'),
            'active' => true,
        );

        $notice = null;
        $error = null;

        $contentHtml = $this->buildConfigSettingsContent(base_url('/admin/settings?tab=general'), $notice, $error);
        if ($contentHtml === null) {
            http_response_code(404);
            return $admin->render('Settings', '<div class="alert alert-danger">Settings schema not found</div>');
        }

        $view = new View();
        $page = $view->fetch('admin-modules/settings:settings', array(
            'pageTitle' => $this->translateOrFallback('admin.settings.title', 'Settings'),
            'tabs' => $tabs,
            'contentHtml' => $contentHtml,
            'notice' => $notice,
            'error' => $error,
        ));

        return $admin->render('Settings', $page, array(
            'user' => auth()->user(),
        ));
    }

    private function translateOrFallback($key, $fallback)
    {
        $key = (string)$key;
        $translated = t($key);
        if ($translated === $key) {
            return (string)$fallback;
        }
        return $translated;
    }

    private function resolveAdminString($spec)
    {
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

    private function isValidModuleName($name)
    {
        $name = (string)$name;
        return ($name !== '' && preg_match('/^[a-z0-9_-]+$/', $name));
    }

    private function adminModulePolicy($manifest)
    {
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

    private function availableThemeOptions()
    {
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

    private function availableLocaleOptions()
    {
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

    private function availableModuleCards()
    {
        $cards = array();
        $base = MANTRA_MODULES;
        if (!is_dir($base)) {
            return $cards;
        }

        $enabled = array('admin');

        // Regular modules
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

            $cards[] = array(
                'id' => $dir,
                'title' => $title,
                'version' => $version,
                'author' => $author,
                'homepage' => $homepage,
                'description' => $description,
                'enabled' => in_array($dir, $enabled, true),
                'has_settings' => false,
                'disableable' => !empty($policy['disableable']),
                'deletable' => !empty($policy['deletable']),
            );
        }

        // Admin submodules
        $adminModulesBase = $base . '/admin-modules';
        if (is_dir($adminModulesBase)) {
            foreach (glob($adminModulesBase . '/*/module.json') as $path) {
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

                $cards[] = array(
                    'id' => 'admin-modules/' . $dir,
                    'title' => $title . ' (Admin)',
                    'version' => $version,
                    'author' => $author,
                    'homepage' => $homepage,
                    'description' => $description,
                    'enabled' => true,
                    'has_settings' => false,
                    'disableable' => false,
                    'deletable' => !empty($policy['deletable']),
                );
            }
        }

        usort($cards, function ($a, $b) {
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $cards;
    }

    private function collectModuleDependencyGraph()
    {
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

    private function dependsOnTransitive($start, $target, $graph)
    {
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

    private function rrmdirSafe($dirPath)
    {
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

    private function applyConfigSchemaRuntimeOptions($schema)
    {
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

    private function handleConfigDeleteModuleAction(&$notice, &$error)
    {
        $deleteId = (string)request()->post('module_delete', '');
        if ($deleteId === '') {
            return false;
        }

        // Handle admin-modules/xxx format
        $isAdminModule = (strpos($deleteId, 'admin-modules/') === 0);
        if ($isAdminModule) {
            $moduleName = substr($deleteId, strlen('admin-modules/'));
            if (!$this->isValidModuleName($moduleName)) {
                $error = 'Invalid module name';
                return true;
            }
            $manifestPath = MANTRA_MODULES . '/admin-modules/' . $moduleName . '/module.json';
            $moduleDir = MANTRA_MODULES . '/admin-modules/' . $moduleName;
            $settingsPath = MANTRA_CONTENT . '/settings/admin-modules-' . $moduleName . '.json';
        } else {
            if (!$this->isValidModuleName($deleteId)) {
                $error = 'Invalid module name';
                return true;
            }
            $manifestPath = MANTRA_MODULES . '/' . $deleteId . '/module.json';
            $moduleDir = MANTRA_MODULES . '/' . $deleteId;
            $settingsPath = MANTRA_CONTENT . '/settings/' . $deleteId . '.json';
        }

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
        if (file_exists($settingsPath)) {
            @unlink($settingsPath);
        }

        // Delete module folder (defense-in-depth: ensure it stays under MANTRA_MODULES).
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

    private function buildSchemaSettingsContent($store, $schema, $actionUrl, &$notice, &$error, $context = array())
    {
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
        return $view->fetch('admin-modules/settings:module-settings', array(
            'title' => '',
            'tabs' => $tabs,
            'active_tab' => $activeInnerTab,
            'action' => $actionUrl,
            'csrf_token' => auth()->generateCsrfToken(),
            'notice' => $notice,
            'error' => $error,
        ));
    }

    private function buildConfigSettingsContent($actionUrl, &$notice, &$error)
    {
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
}
