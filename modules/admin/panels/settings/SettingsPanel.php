<?php
/**
 * SettingsPanel - System settings and module management
 *
 * Ported from AdminSettingsModule. Handles config editing,
 * module enable/disable, and per-module settings.
 */

namespace Admin;

use Module\ModuleValidator;
use Module\ModuleType;

class SettingsPanel extends AdminPanel {

    public function id() {
        return 'settings';
    }

    public function registerRoutes($admin) {
        $admin->adminRoute('GET',  'settings', array($this, 'settings'));
        $admin->adminRoute('POST', 'settings', array($this, 'settings'));
    }

    // ========== Main Action ==========

    public function settings() {
        if (!$this->requireAdmin()) return;

        $activeTab = (string)request()->query('tab', 'general');

        // Build tabs
        $tabs = array();
        $tabs[] = array(
            'id' => 'general',
            'title' => t('admin-settings.general'),
            'url' => base_url('/admin/settings?tab=general'),
            'active' => ($activeTab === 'general'),
        );

        foreach ($this->getModulesWithSettings() as $modId => $modTitle) {
            $tabs[] = array(
                'id' => $modId,
                'title' => $modTitle,
                'url' => base_url('/admin/settings?tab=' . $modId),
                'active' => ($activeTab === $modId),
            );
        }

        $notice = null;
        $error = null;

        if ($activeTab === 'general') {
            $contentHtml = $this->buildConfigSettingsContent(
                base_url('/admin/settings?tab=general'), $notice, $error
            );
        } else {
            $contentHtml = $this->buildModuleSettingsContent(
                $activeTab, base_url('/admin/settings?tab=' . $activeTab), $notice, $error
            );
        }

        if ($contentHtml === null) {
            http_response_code(404);
            return $this->renderAdmin(
                t('admin-settings.title'),
                '<div class="alert alert-danger alert-permanent">Settings not found</div>'
            );
        }

        $settingsPrefix = t('admin-settings.title');
        $pageTitle = $settingsPrefix;
        foreach ($tabs as $tab) {
            if (!empty($tab['active'])) {
                $pageTitle = $settingsPrefix . ' - ' . $tab['title'];
                break;
            }
        }

        $page = $this->renderView('settings', array(
            'pageTitle' => $pageTitle,
            'tabs' => $tabs,
            'contentHtml' => $contentHtml,
            'notice' => $notice,
            'error' => $error,
        ));

        return $this->renderAdmin($pageTitle, $page, array(
            'user' => $this->getUser(),
            'breadcrumbs' => array(
                array('title' => t('admin-dashboard.title'), 'url' => base_url('/admin')),
                array('title' => t('admin-settings.title')),
            ),
        ));
    }

    // ========== Module discovery ==========

    private function getModulesWithSettings() {
        $modules = array();
        $moduleManager = app()->modules();

        foreach ($moduleManager->getModules() as $moduleId => $data) {
            $module = $data['instance'];
            if (!$module->hasSettings()) {
                continue;
            }

            $nameKey = $moduleId . '.name';
            $translatedName = t($nameKey);
            if ($translatedName === $nameKey) {
                $translatedName = $module->getName();
            }

            $modules[$moduleId] = $translatedName;
        }

        ksort($modules);
        return $modules;
    }

    // ========== Config settings ==========

    private function buildConfigSettingsContent($actionUrl, &$notice, &$error) {
        $store = config_settings();
        $schema = $this->applyConfigSchemaRuntimeOptions($store->schema());
        return $this->buildSchemaSettingsContent(
            $store, $schema, $actionUrl, $notice, $error,
            array('on_post' => array($this, 'handleConfigDeleteModuleAction'))
        );
    }

    // ========== Module settings ==========

    private function buildModuleSettingsContent($moduleId, $actionUrl, &$notice, &$error) {
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

    // ========== Generic schema-driven form builder ==========

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
                    $args = array(&$notice, &$error);
                    $handledAction = (bool)call_user_func_array($context['on_post'], $args);
                }

                if (empty($error) && !$handledAction) {
                    $updates = array();
                    $schemaTabs = isset($schema['tabs']) ? $schema['tabs'] : array();

                    foreach ($schemaTabs as $tab) {
                        $tabFields = isset($tab['fields']) ? $tab['fields'] : array();
                        foreach ($tabFields as $field) {
                            if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                                continue;
                            }

                            $path = (string)$field['path'];
                            $type = (string)$field['type'];

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
                                $updates[$path] = (string)$raw;
                            } else {
                                $updates[$path] = (string)$raw;
                            }
                        }
                    }

                    if (!empty($updates)) {
                        // Textarea fields that represent lists
                        foreach ($schemaTabs as $tab) {
                            $tabFields2 = isset($tab['fields']) ? $tab['fields'] : array();
                            foreach ($tabFields2 as $field) {
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

                        // Validate modules.enabled changes
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

        // Build tab data for rendering
        $activeInnerTab = (string)request()->query('section', '');
        if ($activeInnerTab === '') {
            $activeInnerTab = (string)request()->post('active_tab', '');
        }

        $tabs = array();
        $schemaTabs3 = isset($schema['tabs']) ? $schema['tabs'] : array();
        foreach ($schemaTabs3 as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
            $tabTitle = t(isset($tab['title']) ? $tab['title'] : (isset($tab['label']) ? $tab['label'] : $tabId));

            if ($activeInnerTab === '' && $tabId !== '') {
                $activeInnerTab = $tabId;
            }

            $fields = array();
            $tabFields3 = isset($tab['fields']) ? $tab['fields'] : array();
            foreach ($tabFields3 as $field) {
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
                    'title' => t(isset($field['title']) ? $field['title'] : (isset($field['label']) ? $field['label'] : $path)),
                    'help' => isset($field['help']) ? t($field['help']) : '',
                    'value' => $store->get($path, array_key_exists('default', $field) ? $field['default'] : null),
                    'options' => $options,
                );

                if (isset($field['theme_metadata'])) {
                    $f['theme_metadata'] = $field['theme_metadata'];
                }

                if ($f['type'] === 'select' && is_array($f['options'])) {
                    foreach ($f['options'] as $k => $v) {
                        $f['options'][$k] = t($v);
                    }
                }

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

        return $this->renderView('module-settings', array(
            'title' => '',
            'tabs' => $tabs,
            'active_tab' => $activeInnerTab,
            'action' => $actionUrl,
            'csrf_token' => auth()->generateCsrfToken(),
            'notice' => $notice,
            'error' => $error,
        ));
    }

    // ========== Runtime option injectors ==========

    private function applyConfigSchemaRuntimeOptions($schema) {
        if (!is_array($schema) || empty($schema['tabs']) || !is_array($schema['tabs'])) {
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
                if ($path === 'security.password_hash_algo' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->getAvailablePasswordAlgorithms();
                }
                if ($path === 'session.cookie_samesite' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->getAvailableSameSiteOptions();
                }
                if ($path === 'logging.level' && (string)$field['type'] === 'select') {
                    $field['options'] = array(
                        \Logger::DEBUG => 'debug',
                        \Logger::INFO => 'info',
                        \Logger::NOTICE => 'notice',
                        \Logger::WARNING => 'warning',
                        \Logger::ERROR => 'error',
                        \Logger::CRITICAL => 'critical',
                        \Logger::ALERT => 'alert',
                        \Logger::EMERGENCY => 'emergency',
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

    // ========== Option providers ==========

    private function availableThemeOptions() {
        $options = array();
        $base = MANTRA_THEMES;
        if (!is_dir($base)) {
            return $options;
        }
        foreach (glob($base . '/*/theme.json') as $path) {
            $dir = basename(dirname($path));
            try {
                $meta = \JsonCodec::decode(file_get_contents($path));
            } catch (\Exception $e) {
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

    private function getAllThemesMetadata() {
        $themes = array();
        $base = MANTRA_THEMES;
        if (!is_dir($base)) {
            return $themes;
        }
        foreach (glob($base . '/*/theme.json') as $path) {
            $dir = basename(dirname($path));
            try {
                $meta = \JsonCodec::decode(file_get_contents($path));
            } catch (\Exception $e) {
                continue;
            }
            $themes[$dir] = array(
                'id' => $dir,
                'name' => isset($meta['name']) && is_string($meta['name']) ? (string)$meta['name'] : $dir,
                'version' => isset($meta['version']) && is_string($meta['version']) ? (string)$meta['version'] : '',
                'author' => isset($meta['author']) && is_string($meta['author']) ? (string)$meta['author'] : '',
                'description' => isset($meta['description']) ? resolve_localized($meta['description']) : '',
                'homepage' => isset($meta['homepage']) && is_string($meta['homepage']) ? (string)$meta['homepage'] : '',
            );
        }
        return $themes;
    }

    private function availableLocaleOptions() {
        $locales = array();

        if (is_dir(MANTRA_MODULES)) {
            foreach (glob(MANTRA_MODULES . '/*/lang/*.php') as $path) {
                $locale = pathinfo($path, PATHINFO_FILENAME);
                if ($locale !== '') {
                    $locales[$locale] = strtoupper($locale);
                }
            }
        }

        // Also scan panel lang dirs
        $panelsDir = dirname($this->panelPath);
        if (is_dir($panelsDir)) {
            foreach (glob($panelsDir . '/*/lang/*.php') as $path) {
                $locale = pathinfo($path, PATHINFO_FILENAME);
                if ($locale !== '') {
                    $locales[$locale] = strtoupper($locale);
                }
            }
        }

        if (is_dir(MANTRA_THEMES)) {
            foreach (glob(MANTRA_THEMES . '/*/lang/*.php') as $path) {
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

    private function getAvailablePasswordAlgorithms() {
        $algorithms = array(
            'PASSWORD_DEFAULT' => 'PASSWORD_DEFAULT',
            'PASSWORD_BCRYPT' => 'PASSWORD_BCRYPT',
        );
        if (defined('PASSWORD_ARGON2I')) {
            $algorithms['PASSWORD_ARGON2I'] = 'PASSWORD_ARGON2I';
        }
        if (defined('PASSWORD_ARGON2ID')) {
            $algorithms['PASSWORD_ARGON2ID'] = 'PASSWORD_ARGON2ID';
        }
        return $algorithms;
    }

    private function getAvailableSameSiteOptions() {
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            return array(
                'Lax' => 'Lax (recommended)',
                'Strict' => 'Strict',
                'None' => 'None',
            );
        }
        return array(
            'not_supported' => 'Not supported (PHP 7.3+ required)',
        );
    }

    private function availableModuleCards() {
        $cards = array();
        $moduleManager = app()->modules();
        $allModules = $moduleManager->discoverModules();

        foreach ($allModules as $moduleId => $moduleData) {
            $isEnabled = $moduleData['enabled'];
            $manifest = $moduleData['manifest'];
            $module = $isEnabled ? $moduleManager->getModule($moduleId) : null;

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
                $title = resolve_localized(isset($manifest['name']) ? $manifest['name'] : $moduleId);
                $version = isset($manifest['version']) ? $manifest['version'] : '';
                $author = isset($manifest['author']) ? $manifest['author'] : '';
                $homepage = isset($manifest['homepage']) ? $manifest['homepage'] : '';
                $description = resolve_localized(isset($manifest['description']) ? $manifest['description'] : '');
                $type = isset($manifest['type']) ? $manifest['type'] : 'custom';
                $hasSettings = file_exists($moduleData['path'] . '/settings.schema.php');

                $adminConfig = isset($manifest['admin']) ? $manifest['admin'] : array();
                if ($type === ModuleType::CORE) {
                    $canDisable = false;
                    $canDelete = false;
                } else {
                    $canDisable = isset($adminConfig['disableable']) ? $adminConfig['disableable'] : true;
                    $canDelete = isset($adminConfig['deletable']) ? $adminConfig['deletable'] : true;
                }
            }

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

            if ($requiredByEnabled) {
                $canDisable = false;
                $canDelete = false;
            }

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
            return strcasecmp(
                isset($a['title']) ? $a['title'] : '',
                isset($b['title']) ? $b['title'] : ''
            );
        });

        return $cards;
    }

    // ========== Dependency management ==========

    private function collectModuleDependencyGraph() {
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
                $meta = \JsonCodec::decode(file_get_contents($path));
            } catch (\Exception $e) {
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

    private function validateModulesEnabledUpdate($newEnabled) {
        if (!is_array($newEnabled)) {
            return 'Invalid modules list';
        }

        $current = config_settings()->get('modules.enabled', array('admin'));
        if (!is_array($current)) {
            $current = array('admin');
        }

        $moduleManager = app()->modules();
        $graph = $this->collectModuleDependencyGraph();

        $beingDisabled = array_diff($current, $newEnabled);
        foreach ($beingDisabled as $modId) {
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
                $manifestPath = MANTRA_MODULES . '/' . $modId . '/module.json';
                if (file_exists($manifestPath)) {
                    try {
                        $manifest = \JsonCodec::decode(file_get_contents($manifestPath));
                        $type = isset($manifest['type']) ? $manifest['type'] : 'custom';

                        if ($type === ModuleType::CORE) {
                            return "Cannot disable core module '{$modId}'";
                        }

                        $adminConfig = isset($manifest['admin']) ? $manifest['admin'] : array();
                        if (isset($adminConfig['disableable']) && $adminConfig['disableable'] === false) {
                            return "Cannot disable module '{$modId}': protected by policy";
                        }
                    } catch (\Exception $e) {
                        // Skip
                    }
                }
            }

            foreach ($newEnabled as $enabledMod) {
                if ($enabledMod !== $modId && $this->dependsOnTransitive($enabledMod, $modId, $graph)) {
                    return "Cannot disable module '{$modId}': required by '{$enabledMod}'";
                }
            }
        }

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
                $manifest = \JsonCodec::decode(file_get_contents($manifestPath));
            } catch (\Exception $e) {
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

    private function handleConfigDeleteModuleAction(&$notice, &$error) {
        $deleteId = (string)request()->post('module_delete', '');
        if ($deleteId === '') {
            return false;
        }

        if (!ModuleValidator::isValidModuleId($deleteId)) {
            $error = 'Invalid module name';
            return true;
        }

        $moduleManager = app()->modules();
        $module = $moduleManager->getModule($deleteId);

        if ($module) {
            if (!$module->isDeletable()) {
                $error = 'This module cannot be deleted';
                return true;
            }
            if ($moduleManager->isLoaded($deleteId)) {
                $error = 'Disable the module before deleting it';
                return true;
            }
        } else {
            $manifestPath = MANTRA_MODULES . '/' . $deleteId . '/module.json';
            if (file_exists($manifestPath)) {
                try {
                    $manifest = \JsonCodec::decode(file_get_contents($manifestPath));
                    $type = isset($manifest['type']) ? $manifest['type'] : 'custom';
                    $adminConfig = isset($manifest['admin']) ? $manifest['admin'] : array();

                    if ($type === ModuleType::CORE) {
                        $error = 'Core modules cannot be deleted';
                        return true;
                    }
                    if (isset($adminConfig['deletable']) && $adminConfig['deletable'] === false) {
                        $error = 'This module cannot be deleted';
                        return true;
                    }
                } catch (\Exception $e) {
                    // Continue
                }
            }
        }

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

        $settingsPath = MANTRA_CONTENT . '/settings/' . $deleteId . '.json';
        if (file_exists($settingsPath)) {
            @unlink($settingsPath);
        }

        $moduleDir = MANTRA_MODULES . '/' . $deleteId;
        $realModules = realpath(MANTRA_MODULES);
        $realModuleDir = realpath($moduleDir);
        if ($realModules && $realModuleDir && str_starts_with($realModuleDir, $realModules)) {
            $this->rrmdirSafe($realModuleDir);
        }

        $newEnabled = array_values(array_diff($enabled, array($deleteId)));
        config_settings()->set('modules.enabled', $newEnabled);
        config_settings()->save();

        $notice = "Module '{$deleteId}' deleted";
        return true;
    }

    private function rrmdirSafe($dirPath) {
        $dirPath = (string)$dirPath;
        if ($dirPath === '' || !is_dir($dirPath)) {
            return;
        }

        $it = new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dirPath);
    }
}
