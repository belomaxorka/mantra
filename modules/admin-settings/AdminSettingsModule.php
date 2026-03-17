<?php

class AdminSettingsModule extends Module
{
    public function init()
    {
        // Sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'settings',
                'title' => 'admin-settings.title',
                'icon' => 'bi-sliders',
                'group' => 'admin.sidebar.group.system',
                'order' => 50,
                'url' => base_url('/admin/settings'),
            );

            return $items;
        });

        // Quick action
        app()->hooks()->register('admin.quick_actions', function ($actions) {
            if (!is_array($actions)) {
                $actions = array();
            }

            $actions[] = array(
                'id' => 'settings',
                'title' => 'admin-settings.title',
                'icon' => 'bi-gear',
                'url' => base_url('/admin/settings'),
                'order' => 10,
            );

            return $actions;
        });

        // Register routes
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute('GET', 'settings', array($this, 'settings'));
                $admin->adminRoute('POST', 'settings', array($this, 'settings'));
            }
        });
    }

    public function settings()
    {
        $admin = app()->modules()->getModule('admin');
        if (!$admin || !method_exists($admin, 'render')) {
            http_response_code(500);
            echo 'Admin module not loaded';
            return;
        }

        // Get active tab from query string
        $activeTab = (string)request()->query('tab', 'general');

        // Build tabs list
        $tabs = array();
        $tabs[] = array(
            'id' => 'general',
            'title' => $this->translateOrFallback('admin-settings.general', 'General'),
            'url' => base_url('/admin/settings?tab=general'),
            'active' => ($activeTab === 'general'),
        );

        // Add tabs for modules with settings
        $modulesWithSettings = $this->getModulesWithSettings();
        foreach ($modulesWithSettings as $modId => $modTitle) {
            $tabs[] = array(
                'id' => $modId,
                'title' => $modTitle,
                'url' => base_url('/admin/settings?tab=' . $modId),
                'active' => ($activeTab === $modId),
            );
        }

        $notice = null;
        $error = null;

        // Render content based on active tab
        if ($activeTab === 'general') {
            $contentHtml = $this->buildConfigSettingsContent(base_url('/admin/settings?tab=general'), $notice, $error);
        } else {
            $contentHtml = $this->buildModuleSettingsContent($activeTab, base_url('/admin/settings?tab=' . $activeTab), $notice, $error);
        }

        if ($contentHtml === null) {
            http_response_code(404);
            return $admin->render('Settings', '<div class="alert alert-danger alert-dismissible fade show alert-permanent" role="alert">Settings not found<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
        }

        $page = view()->fetch('admin-settings:settings', array(
            'pageTitle' => $this->translateOrFallback('admin-settings.title', 'Settings'),
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
        $translated = t($key);
        // If translation not found (returns key), use provided fallback
        if ($translated === $key) {
            return (string)$fallback;
        }
        return $translated;
    }

    /**
     * Get list of modules with settings (id => title)
     */
    private function getModulesWithSettings()
    {
        $modules = array();
        $moduleManager = app()->modules();

        foreach ($moduleManager->getModules() as $moduleId => $data) {
            $module = $data['instance'];

            if (!$module->hasSettings()) {
                continue;
            }

            // Try to get translated name using module_id.name key
            $nameKey = $moduleId . '.name';
            $translatedName = t($nameKey);

            // If translation not found, fallback to manifest name
            if ($translatedName === $nameKey) {
                $translatedName = $module->getName();
            }

            $modules[$moduleId] = $translatedName;
        }

        ksort($modules);
        return $modules;
    }

    /**
     * Build module settings content
     */
    private function buildModuleSettingsContent($moduleId, $actionUrl, &$notice, &$error)
    {
        if (!ModuleValidator::isValidModuleId($moduleId)) {
            $error = 'Invalid module name';
            return null;
        }

        $store = module_settings($moduleId);
        $schema = $store->schema();

        if (!is_array($schema)) {
            $error = 'This module has no settings';
            return null;
        }

        return $this->buildSchemaSettingsContent($store, $schema, $actionUrl, $notice, $error);
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
            try {
                $meta = JsonFile::read($path);
            } catch (JsonFileException $e) {
                continue;
            }
            $name = $dir;
            if (is_array($meta) && !empty($meta['name']) && is_string($meta['name'])) {
                $name = (string)$meta['name'];
            }
            $options[$dir] = $name;
        }

        ksort($options);
        return $options;
    }

    private function getAllThemesMetadata()
    {
        $themes = array();
        $base = MANTRA_THEMES;
        if (!is_dir($base)) {
            return $themes;
        }

        foreach (glob($base . '/*/theme.json') as $path) {
            $dir = basename(dirname($path));
            try {
                $meta = JsonFile::read($path);
            } catch (JsonFileException $e) {
                continue;
            }

            $themes[$dir] = array(
                'id' => $dir,
                'name' => isset($meta['name']) && is_string($meta['name']) ? (string)$meta['name'] : $dir,
                'version' => isset($meta['version']) && is_string($meta['version']) ? (string)$meta['version'] : '',
                'author' => isset($meta['author']) && is_string($meta['author']) ? (string)$meta['author'] : '',
                'description' => isset($meta['description']) ? $this->resolveLocalizedValue($meta['description']) : '',
            );
        }

        return $themes;
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
        $moduleManager = app()->modules();

        // Get all available modules (including disabled)
        $allModules = $moduleManager->discoverModules();

        foreach ($allModules as $moduleId => $moduleData) {
            $isEnabled = $moduleData['enabled'];
            $manifest = $moduleData['manifest'];

            // Get module instance if loaded
            $module = $isEnabled ? $moduleManager->getModule($moduleId) : null;

            // Use module API if available, otherwise parse manifest
            if ($module) {
                $title = $module->getName();
                $version = $module->getVersion();
                $author = $module->getAuthor();
                $homepage = $module->getHomepage();
                $description = $module->getDescription();
                $type = $module->getType();
                $hasSettings = $module->hasSettings();
                $canDisable = $module->isDisableable();
                $canDelete = $module->isDeletable();
            } else {
                // Parse from manifest for disabled modules
                $title = resolve_localized($manifest['name'] ?? $moduleId);
                $version = $manifest['version'] ?? '';
                $author = $manifest['author'] ?? '';
                $homepage = $manifest['homepage'] ?? '';
                $description = resolve_localized($manifest['description'] ?? '');
                $type = $manifest['type'] ?? 'custom';
                $hasSettings = file_exists($moduleData['path'] . '/settings.schema.php');

                // Check type for disableable/deletable
                $adminConfig = $manifest['admin'] ?? array();

                if ($type === ModuleType::CORE) {
                    $canDisable = false;
                    $canDelete = false;
                } else {
                    $canDisable = $adminConfig['disableable'] ?? true;
                    $canDelete = $adminConfig['deletable'] ?? true;
                }
            }

            // Check if any enabled module depends on this one
            $requiredByEnabled = false;
            if ($isEnabled) {
                $enabled = config_settings()->get('modules.enabled', array('admin'));
                $graph = $this->collectModuleDependencyGraph();

                foreach ($enabled as $enabledMod) {
                    if ($enabledMod !== $moduleId && $this->dependsOnTransitive($enabledMod, $moduleId, $graph)) {
                        $requiredByEnabled = true;
                        break;
                    }
                }
            }

            // Override permissions if required by other modules
            if ($requiredByEnabled) {
                $canDisable = false;
                $canDelete = false;
            }

            // Can't delete if enabled
            if ($isEnabled) {
                $canDelete = false;
            }

            $cards[] = array(
                'id' => $moduleId,
                'title' => $title,
                'version' => $version,
                'author' => $author,
                'homepage' => $homepage,
                'description' => $description,
                'type' => $type,
                'enabled' => $isEnabled,
                'has_settings' => $hasSettings,
                'disableable' => $canDisable,
                'deletable' => $canDelete,
            );
        }

        usort($cards, function ($a, $b) {
            return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
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
            if (!ModuleValidator::isValidModuleId($dir)) {
                continue;
            }

            try {
                $meta = JsonFile::read($path);
            } catch (JsonFileException $e) {
                $meta = array();
            }

            $deps = array();
            if (isset($meta['dependencies']) && is_array($meta['dependencies'])) {
                foreach ($meta['dependencies'] as $d) {
                    if (!is_string($d)) {
                        continue;
                    }
                    $d = trim((string)$d);
                    if ($d !== '' && ModuleValidator::isValidModuleId($d)) {
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
                    $field['theme_metadata'] = $this->getAllThemesMetadata();
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

        if (!ModuleValidator::isValidModuleId($deleteId)) {
            $error = 'Invalid module name';
            return true;
        }

        $moduleManager = app()->modules();

        // Check if module is loaded
        $module = $moduleManager->getModule($deleteId);

        if ($module) {
            // Use module API for loaded modules
            if (!$module->isDeletable()) {
                $error = 'This module cannot be deleted';
                return true;
            }

            if ($moduleManager->isLoaded($deleteId)) {
                $error = 'Disable the module before deleting it';
                return true;
            }
        } else {
            // Check manifest for unloaded modules
            $manifestPath = MANTRA_MODULES . '/' . $deleteId . '/module.json';
            if (file_exists($manifestPath)) {
                try {
                    $manifest = JsonFile::read($manifestPath);
                    $type = $manifest['type'] ?? 'custom';
                    $adminConfig = $manifest['admin'] ?? array();

                    // CORE modules cannot be deleted
                    if ($type === ModuleType::CORE) {
                        $error = 'Core modules cannot be deleted';
                        return true;
                    }

                    // Check admin.deletable flag
                    if (isset($adminConfig['deletable']) && $adminConfig['deletable'] === false) {
                        $error = 'This module cannot be deleted';
                        return true;
                    }
                } catch (JsonFileException $e) {
                    // Continue with deletion if manifest is unreadable
                }
            }
        }

        // Check if any enabled module depends on this one
        $enabled = config_settings()->get('modules.enabled', array('admin'));
        if (!is_array($enabled)) {
            $enabled = array('admin');
        }

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

        // Delete module settings
        $settingsPath = MANTRA_CONTENT . '/settings/' . $deleteId . '.json';
        if (file_exists($settingsPath)) {
            @unlink($settingsPath);
        }

        // Delete module folder
        $moduleDir = MANTRA_MODULES . '/' . $deleteId;
        $realModules = realpath(MANTRA_MODULES);
        $realModuleDir = realpath($moduleDir);
        if ($realModules && $realModuleDir && strpos($realModuleDir, $realModules) === 0) {
            $this->rrmdirSafe($realModuleDir);
        }

        // Remove from enabled list
        $newEnabled = array_values(array_diff($enabled, array($deleteId)));
        config_settings()->set('modules.enabled', $newEnabled);
        config_settings()->save();

        $notice = "Module '{$deleteId}' deleted";
        return true;
    }

    private function validateModulesEnabledUpdate($newEnabled)
    {
        if (!is_array($newEnabled)) {
            return 'Invalid modules list';
        }

        $current = config_settings()->get('modules.enabled', array('admin'));
        if (!is_array($current)) {
            $current = array('admin');
        }

        $moduleManager = app()->modules();
        $graph = $this->collectModuleDependencyGraph();

        // Check modules being disabled
        $beingDisabled = array_diff($current, $newEnabled);
        foreach ($beingDisabled as $modId) {
            // Use module API if loaded
            $module = $moduleManager->getModule($modId);

            if ($module) {
                if (!$module->isDisableable()) {
                    $type = $module->getType();
                    if ($type === ModuleType::CORE) {
                        return "Cannot disable core module '{$modId}'";
                    }
                    return "Cannot disable module '{$modId}': protected by policy";
                }
            } else {
                // Check manifest for unloaded modules
                $manifestPath = MANTRA_MODULES . '/' . $modId . '/module.json';
                if (file_exists($manifestPath)) {
                    try {
                        $manifest = JsonFile::read($manifestPath);
                        $type = $manifest['type'] ?? 'custom';

                        // CORE modules cannot be disabled
                        if ($type === ModuleType::CORE) {
                            return "Cannot disable core module '{$modId}'";
                        }

                        $adminConfig = $manifest['admin'] ?? array();
                        if (isset($adminConfig['disableable']) && $adminConfig['disableable'] === false) {
                            return "Cannot disable module '{$modId}': protected by policy";
                        }
                    } catch (JsonFileException $e) {
                        // Skip policy check if manifest is unreadable
                    }
                }
            }

            // Check if any module in the new enabled list depends on this one
            foreach ($newEnabled as $enabledMod) {
                if ($enabledMod !== $modId && $this->dependsOnTransitive($enabledMod, $modId, $graph)) {
                    return "Cannot disable module '{$modId}': required by '{$enabledMod}'";
                }
            }
        }

        // Check modules being enabled
        $beingEnabled = array_diff($newEnabled, $current);
        foreach ($beingEnabled as $modId) {
            if (!ModuleValidator::isValidModuleId($modId)) {
                return "Invalid module name: '{$modId}'";
            }

            $manifestPath = MANTRA_MODULES . '/' . $modId . '/module.json';
            if (!file_exists($manifestPath)) {
                return "Module not found: '{$modId}'";
            }

            try {
                $manifest = JsonFile::read($manifestPath);
            } catch (JsonFileException $e) {
                return "Cannot read module manifest: '{$modId}'";
            }

            if (isset($manifest['dependencies']) && is_array($manifest['dependencies'])) {
                foreach ($manifest['dependencies'] as $dep) {
                    if (!is_string($dep)) {
                        continue;
                    }
                    $dep = trim($dep);
                    if ($dep !== '' && !in_array($dep, $newEnabled, true)) {
                        return "Cannot enable module '{$modId}': missing dependency '{$dep}'";
                    }
                }
            }
        }

        return null;
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

                        // Validate modules.enabled changes (security: enforce policies and dependencies)
                        if (array_key_exists('modules.enabled', $updates)) {
                            $validationError = $this->validateModulesEnabledUpdate($updates['modules.enabled']);
                            if ($validationError !== null) {
                                $error = $validationError;
                                unset($updates['modules.enabled']);
                            }
                        }

                        if (!empty($updates)) {
                            $store->setMultiple($updates);
                            $store->save();
                            $notice = 'Settings saved';
                        }
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

                // Pass through theme_metadata if present
                if (isset($field['theme_metadata'])) {
                    $f['theme_metadata'] = $field['theme_metadata'];
                }

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
        return $view->fetch('admin-settings:module-settings', array(
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
