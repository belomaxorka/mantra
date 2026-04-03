<?php declare(strict_types=1);
/**
 * SettingsPanel - System settings and module management
 *
 * Ported from AdminSettingsModule. Handles config editing,
 * module enable/disable, and per-module settings.
 */

namespace Admin;

use Module\ModuleValidator;
use Module\ModuleType;

class SettingsPanel extends AdminPanel
{

    public function id()
    {
        return 'settings';
    }

    public function registerRoutes($admin): void
    {
        $admin->adminRoute('GET', 'settings', [$this, 'settings']);
        $admin->adminRoute('POST', 'settings', [$this, 'settings']);
    }

    // ========== Main Action ==========

    public function settings()
    {
        if (!$this->requireAdmin()) return;

        $activeTab = (string)app()->request()->query('tab', 'general');

        // Build tabs
        $tabs = [];
        $tabs[] = [
            'id' => 'general',
            'title' => t('admin-settings.general'),
            'url' => base_url('/admin/settings?tab=general'),
            'active' => ($activeTab === 'general'),
        ];

        foreach ($this->getModulesWithSettings() as $modId => $modTitle) {
            $tabs[] = [
                'id' => $modId,
                'title' => $modTitle,
                'url' => base_url('/admin/settings?tab=' . $modId),
                'active' => ($activeTab === $modId),
            ];
        }

        $notice = null;
        $error = null;

        if ($activeTab === 'general') {
            $contentHtml = $this->buildConfigSettingsContent(
                base_url('/admin/settings?tab=general'), $notice, $error,
            );
        } else {
            $contentHtml = $this->buildModuleSettingsContent(
                $activeTab, base_url('/admin/settings?tab=' . $activeTab), $notice, $error,
            );
        }

        if ($contentHtml === null) {
            http_response_code(404);
            return $this->renderAdmin(
                t('admin-settings.title'),
                '<div class="alert alert-danger alert-permanent">Settings not found</div>',
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

        $page = $this->renderView('settings', [
            'pageTitle' => $pageTitle,
            'tabs' => $tabs,
            'contentHtml' => $contentHtml,
            'notice' => $notice,
            'error' => $error,
        ]);

        return $this->renderAdmin($pageTitle, $page, [
            'user' => $this->getUser(),
            'breadcrumbs' => [
                ['title' => t('admin-dashboard.title'), 'url' => base_url('/admin')],
                ['title' => t('admin-settings.title')],
            ],
        ]);
    }

    // ========== Module discovery ==========

    private function getModulesWithSettings()
    {
        $modules = [];
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

    private function buildConfigSettingsContent($actionUrl, &$notice, &$error)
    {
        $store = \ConfigSettings::instance();
        $schema = $this->applyConfigSchemaRuntimeOptions($store->schema());
        return $this->buildSchemaSettingsContent(
            $store, $schema, $actionUrl, $notice, $error,
            ['on_post' => [$this, 'handleConfigDeleteModuleAction']],
        );
    }

    // ========== Module settings ==========

    private function buildModuleSettingsContent($moduleId, $actionUrl, &$notice, &$error)
    {
        if (!ModuleValidator::isValidModuleId($moduleId)) {
            $error = 'Invalid module name';
            return null;
        }

        $store = \Module\ModuleSettings::instance($moduleId);
        $schema = $store->schema();
        if (!is_array($schema)) {
            $error = 'This module has no settings';
            return null;
        }

        return $this->buildSchemaSettingsContent($store, $schema, $actionUrl, $notice, $error);
    }

    // ========== Generic schema-driven form builder ==========

    private function buildSchemaSettingsContent($store, $schema, $actionUrl, &$notice, &$error, $context = [])
    {
        if (!is_array($schema)) {
            $error = 'This module has no settings';
            return null;
        }

        if (is_array($context) && !empty($context['schema_mutator']) && is_callable($context['schema_mutator'])) {
            $schema = ($context['schema_mutator'])($schema);
        }

        if (method_exists($store, 'load')) {
            $store->load();
        }

        if (app()->request()->method() === 'POST') {
            $token = (string)app()->request()->post('csrf_token', '');
            if (!$this->auth()->verifyCsrfToken($token)) {
                $error = 'Invalid CSRF token';
            } else {
                $handledAction = false;
                if (is_array($context) && !empty($context['on_post']) && is_callable($context['on_post'])) {
                    $args = [&$notice, &$error];
                    $handledAction = (bool)($context['on_post'])(...$args);
                }

                if (empty($error) && !$handledAction) {
                    $updates = [];
                    $schemaTabs = $schema['tabs'] ?? [];

                    foreach ($schemaTabs as $tab) {
                        $tabFields = $tab['fields'] ?? [];
                        foreach ($tabFields as $field) {
                            if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                                continue;
                            }

                            $path = (string)$field['path'];
                            $type = (string)$field['type'];

                            if ($type === 'module_cards') {
                                $posted = app()->request()->post($path, null);
                                if (is_array($posted)) {
                                    $items = [];
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
                                $updates[$path] = app()->request()->post($name) ? true : false;
                                continue;
                            }

                            $raw = app()->request()->post($name, null);
                            if ($raw === null) {
                                continue;
                            }

                            if ($type === 'number') {
                                $updates[$path] = (int)$raw;
                            } elseif ($type === 'select') {
                                $val = (string)$raw;
                                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
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
                            $tabFields2 = $tab['fields'] ?? [];
                            foreach ($tabFields2 as $field) {
                                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                                    continue;
                                }
                                $path = (string)$field['path'];
                                if ((string)$field['type'] === 'textarea' && array_key_exists($path, $updates) && array_key_exists('default', $field) && is_array($field['default'])) {
                                    $raw = (string)$updates[$path];
                                    $lines = preg_split('/\r\n|\r|\n/', $raw);
                                    $lines = is_array($lines) ? $lines : [];
                                    $items = [];
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
        $activeInnerTab = (string)app()->request()->query('section', '');
        if ($activeInnerTab === '') {
            $activeInnerTab = (string)app()->request()->post('active_tab', '');
        }

        $tabs = [];
        $schemaTabs3 = $schema['tabs'] ?? [];
        foreach ($schemaTabs3 as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $tabId = isset($tab['id']) ? (string)$tab['id'] : 'tab';
            $tabTitle = t($tab['title'] ?? ($tab['label'] ?? $tabId));

            if ($activeInnerTab === '' && $tabId !== '') {
                $activeInnerTab = $tabId;
            }

            $fields = [];
            $tabFields3 = $tab['fields'] ?? [];
            foreach ($tabFields3 as $field) {
                if (!is_array($field) || empty($field['path']) || empty($field['type'])) {
                    continue;
                }

                $path = (string)$field['path'];
                $type = (string)$field['type'];
                $name = $type === 'module_cards' ? $path : str_replace('.', '__', $path);
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];

                $f = [
                    'path' => $path,
                    'name' => $name,
                    'type' => $type,
                    'title' => t($field['title'] ?? ($field['label'] ?? $path)),
                    'help' => isset($field['help']) ? t($field['help']) : '',
                    'value' => $store->get($path, array_key_exists('default', $field) ? $field['default'] : null),
                    'options' => $options,
                ];

                if (isset($field['theme_metadata'])) {
                    $f['theme_metadata'] = $field['theme_metadata'];
                }

                if ($f['type'] === 'select' && is_array($f['options'])) {
                    $skipTranslation = in_array($path, [
                        'locale.date_format', 'locale.time_format',
                    ], true);
                    if (!$skipTranslation) {
                        foreach ($f['options'] as $k => $v) {
                            $f['options'][$k] = t($v);
                        }
                    }
                }

                if ($f['type'] === 'textarea' && is_array($f['value'])) {
                    $f['value'] = array_values($f['value']);
                }

                $fields[] = $f;
            }

            $tabs[] = [
                'id' => $tabId,
                'title' => $tabTitle,
                'fields' => $fields,
            ];
        }

        return $this->renderView('module-settings', [
            'title' => '',
            'tabs' => $tabs,
            'active_tab' => $activeInnerTab,
            'action' => $actionUrl,
            'csrf_token' => $this->auth()->generateCsrfToken(),
            'notice' => $notice,
            'error' => $error,
        ]);
    }

    // ========== Runtime option injectors ==========

    private function applyConfigSchemaRuntimeOptions($schema)
    {
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

                if ($path === 'locale.timezone' && (string)$field['type'] === 'timezone_select') {
                    $field['options'] = $this->availableTimezoneOptions();
                }
                if ($path === 'locale.date_format' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->dateFormatExamples();
                }
                if ($path === 'locale.time_format' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->timeFormatExamples();
                }
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
                    $field['options'] = [
                        \Logger::DEBUG => 'debug',
                        \Logger::INFO => 'info',
                        \Logger::NOTICE => 'notice',
                        \Logger::WARNING => 'warning',
                        \Logger::ERROR => 'error',
                        \Logger::CRITICAL => 'critical',
                        \Logger::ALERT => 'alert',
                        \Logger::EMERGENCY => 'emergency',
                    ];
                }
                if ($path === 'admin.accent_color' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableAccentColorOptions();
                }
                if ($path === 'admin.sidebar_color' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableSidebarColorOptions();
                }
                if ($path === 'admin.font' && (string)$field['type'] === 'select') {
                    $field['options'] = $this->availableFontOptions();
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

    private function availableAccentColorOptions()
    {
        $presetsFile = dirname($this->panelPath, 2) . '/appearance-presets.php';
        if (!file_exists($presetsFile)) {
            return [];
        }
        $presets = require $presetsFile;
        if (!is_array($presets)) {
            return [];
        }
        $options = [];
        foreach ($presets as $key => $vars) {
            $options[$key] = 'admin-settings.appearance.preset.' . $key;
        }
        return $options;
    }

    private function availableFontOptions()
    {
        $presetsFile = dirname($this->panelPath, 2) . '/font-presets.php';
        if (!file_exists($presetsFile)) {
            return [];
        }
        $presets = require $presetsFile;
        if (!is_array($presets)) {
            return [];
        }
        $options = [];
        foreach ($presets as $key => $meta) {
            $options[$key] = 'admin-settings.appearance.font.' . $key;
        }
        return $options;
    }

    private function availableSidebarColorOptions()
    {
        $presetsFile = dirname($this->panelPath, 2) . '/sidebar-presets.php';
        if (!file_exists($presetsFile)) {
            return [];
        }
        $presets = require $presetsFile;
        if (!is_array($presets)) {
            return [];
        }
        $options = [];
        foreach ($presets as $key => $vars) {
            $options[$key] = 'admin-settings.appearance.sidebar.' . $key;
        }
        return $options;
    }

    private function availableThemeOptions()
    {
        $options = [];
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

    private function getAllThemesMetadata()
    {
        $themes = [];
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
            $themes[$dir] = [
                'id' => $dir,
                'name' => isset($meta['name']) && is_string($meta['name']) ? (string)$meta['name'] : $dir,
                'version' => isset($meta['version']) && is_string($meta['version']) ? (string)$meta['version'] : '',
                'author' => isset($meta['author']) && is_string($meta['author']) ? (string)$meta['author'] : '',
                'description' => isset($meta['description']) ? \Config::resolveLocalized($meta['description']) : '',
                'homepage' => isset($meta['homepage']) && is_string($meta['homepage']) ? (string)$meta['homepage'] : '',
            ];
        }
        return $themes;
    }

    private function availableLocaleOptions()
    {
        $locales = [];

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
            $locales = ['en' => 'EN'];
        }
        ksort($locales);
        return $locales;
    }

    private function getAvailablePasswordAlgorithms()
    {
        $algorithms = [
            'PASSWORD_DEFAULT' => 'PASSWORD_DEFAULT',
            'PASSWORD_BCRYPT' => 'PASSWORD_BCRYPT',
        ];
        if (defined('PASSWORD_ARGON2I')) {
            $algorithms['PASSWORD_ARGON2I'] = 'PASSWORD_ARGON2I';
        }
        if (defined('PASSWORD_ARGON2ID')) {
            $algorithms['PASSWORD_ARGON2ID'] = 'PASSWORD_ARGON2ID';
        }
        return $algorithms;
    }

    private function getAvailableSameSiteOptions()
    {
        return [
            'Lax' => 'Lax (recommended)',
            'Strict' => 'Strict',
            'None' => 'None',
        ];
    }

    /**
     * Build grouped timezone options for the timezone_select field.
     *
     * Returns a nested array: region => array(timezone_id => label).
     */
    private function availableTimezoneOptions()
    {
        $grouped = [];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $dtz = new \DateTimeZone($tz);
            $offset = $dtz->getOffset($now);

            $hours = (int)($offset / 3600);
            $minutes = abs($offset % 3600) / 60;
            $sign = $offset >= 0 ? '+' : '-';
            $utcLabel = sprintf('UTC%s%02d:%02d', $sign, abs($hours), $minutes);

            $parts = explode('/', $tz, 2);
            if (count($parts) === 2) {
                $region = $parts[0];
                $city = str_replace('_', ' ', $parts[1]);
            } else {
                $region = 'Other';
                $city = $tz;
            }

            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            $grouped[$region][$tz] = $city . ' (' . $utcLabel . ')';
        }

        ksort($grouped);
        foreach ($grouped as &$options) {
            asort($options);
        }
        unset($options);

        return $grouped;
    }

    /**
     * Build date format examples for the date_format select.
     */
    private function dateFormatExamples()
    {
        $now = time();
        return [
            'j F Y' => date('j F Y', $now),
            'd.m.Y' => date('d.m.Y', $now),
            'm/d/Y' => date('m/d/Y', $now),
            'Y-m-d' => date('Y-m-d', $now),
            'd M Y' => date('d M Y', $now),
            'F j, Y' => date('F j, Y', $now),
        ];
    }

    /**
     * Build time format examples for the time_format select.
     */
    private function timeFormatExamples()
    {
        $now = time();
        return [
            'H:i' => date('H:i', $now),
            'g:i A' => date('g:i A', $now),
        ];
    }

    private function availableModuleCards()
    {
        $cards = [];
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
                $title = \Config::resolveLocalized($manifest['name'] ?? $moduleId);
                $version = $manifest['version'] ?? '';
                $author = $manifest['author'] ?? '';
                $homepage = $manifest['homepage'] ?? '';
                $description = \Config::resolveLocalized($manifest['description'] ?? '');
                $type = $manifest['type'] ?? 'custom';
                $hasSettings = file_exists($moduleData['path'] . '/settings.schema.php');

                $adminConfig = $manifest['admin'] ?? [];
                if ($type === ModuleType::CORE) {
                    $canDisable = false;
                    $canDelete = false;
                } else {
                    $canDisable = $adminConfig['disableable'] ?? true;
                    $canDelete = $adminConfig['deletable'] ?? true;
                }
            }

            $requiredByEnabled = false;
            if ($isEnabled) {
                $enabled = \ConfigSettings::instance()->get('modules.enabled', ['admin']);
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

            $cards[] = [
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
            ];
        }

        usort($cards, fn($a, $b) => strcasecmp(
            $a['title'] ?? '',
            $b['title'] ?? '',
            ));

        return $cards;
    }

    // ========== Dependency management ==========

    private function collectModuleDependencyGraph()
    {
        $graph = [];
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
                $meta = [];
            }

            $deps = [];
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

        $visited = [];
        $stack = [$start];

        while (!empty($stack)) {
            $cur = array_pop($stack);
            if (isset($visited[$cur])) {
                continue;
            }
            $visited[$cur] = true;

            $deps = isset($graph[$cur]) && is_array($graph[$cur]) ? $graph[$cur] : [];
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

    private function validateModulesEnabledUpdate($newEnabled)
    {
        if (!is_array($newEnabled)) {
            return 'Invalid modules list';
        }

        $current = \ConfigSettings::instance()->get('modules.enabled', ['admin']);
        if (!is_array($current)) {
            $current = ['admin'];
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
                        $type = $manifest['type'] ?? 'custom';

                        if ($type === ModuleType::CORE) {
                            return "Cannot disable core module '{$modId}'";
                        }

                        $adminConfig = $manifest['admin'] ?? [];
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

    private function handleConfigDeleteModuleAction(&$notice, &$error)
    {
        $deleteId = (string)app()->request()->post('module_delete', '');
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
                    $type = $manifest['type'] ?? 'custom';
                    $adminConfig = $manifest['admin'] ?? [];

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

        $enabled = \ConfigSettings::instance()->get('modules.enabled', ['admin']);
        if (!is_array($enabled)) {
            $enabled = ['admin'];
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

        $newEnabled = array_values(array_diff($enabled, [$deleteId]));
        \ConfigSettings::instance()->set('modules.enabled', $newEnabled);
        \ConfigSettings::instance()->save();

        $notice = "Module '{$deleteId}' deleted";
        return true;
    }

    private function rrmdirSafe($dirPath): void
    {
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
