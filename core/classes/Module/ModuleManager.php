<?php
/**
 * ModuleManager - Manages module lifecycle
 * Loads, initializes, and provides access to modules
 */

class ModuleManager
{
    private $modules = array();
    private $config = array();
    private $loadedModules = array();

    // Tracks modules currently being loaded (for cycle detection).
    private $loading = array();
    private $loadingStack = array();

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Validate module name to prevent path traversal / unexpected casing.
     * Only allows: lowercase latin letters, digits, underscore and dash.
     */
    private function assertValidModuleName($name, $context = null)
    {
        if (!ModuleValidator::isValidModuleId($name)) {
            logger()->error('Invalid module name', array(
                'module' => $name,
                'context' => $context
            ));

            $message = "Invalid module name";
            if (is_string($context) && $context !== '') {
                $message .= " ({$context})";
            }
            $message .= ": '{$name}'";

            throw new Exception($message);
        }
    }

    /**
     * Load all enabled modules
     */
    public function loadModules()
    {
        $enabledModules = Config::getNested($this->config, 'modules.enabled', array());
        if (!is_array($enabledModules)) {
            $enabledModules = array();
        }

        foreach ($enabledModules as $moduleName) {
            $this->loadModule($moduleName);
        }
    }

    /**
     * Load a single module
     */
    public function loadModule($moduleName)
    {
        $this->assertValidModuleName($moduleName, 'loadModule');

        if (isset($this->loadedModules[$moduleName])) {
            return true;
        }

        if (isset($this->loading[$moduleName])) {
            $startIndex = array_search($moduleName, $this->loadingStack, true);
            $cyclePath = $startIndex === false
                ? array_merge($this->loadingStack, array($moduleName))
                : array_merge(array_slice($this->loadingStack, $startIndex), array($moduleName));

            $cycleString = implode(' -> ', $cyclePath);
            logger()->error('Cyclic module dependency detected', array(
                'module' => $moduleName,
                'cycle' => $cycleString
            ));
            throw new Exception("Cyclic module dependency: {$cycleString}");
        }

        $this->loading[$moduleName] = true;
        $this->loadingStack[] = $moduleName;

        try {
            $modulePath = MANTRA_MODULES . '/' . $moduleName;
            $manifestPath = $modulePath . '/module.json';

            // Convert kebab-case to PascalCase
            $parts = explode('-', $moduleName);
            $pascalCase = implode('', array_map('ucfirst', $parts));
            $mainFile = $modulePath . '/' . $pascalCase . 'Module.php';

            if (!file_exists($manifestPath) || !file_exists($mainFile)) {
                logger()->warning('Module not found', array('module' => $moduleName));
                return false;
            }

            $manifest = JsonCodec::decode(file_get_contents($manifestPath));

            // Validate required fields
            if (!isset($manifest['id']) || !isset($manifest['version'])) {
                throw new Exception("Module manifest missing required fields: id, version");
            }

            // Load dependencies
            if (isset($manifest['dependencies']) && is_array($manifest['dependencies'])) {
                foreach ($manifest['dependencies'] as $dependency) {
                    $this->assertValidModuleName($dependency, "dependency of {$moduleName}");
                    if (!$this->loadModule($dependency)) {
                        throw new Exception("Module '{$moduleName}' requires '{$dependency}'");
                    }
                }
            }

            require_once $mainFile;

            $className = $pascalCase . 'Module';
            if (!class_exists($className)) {
                throw new Exception("Module class '{$className}' not found");
            }

            $module = new $className($manifest, $moduleName, $modulePath);
            $module->init();

            $this->modules[$moduleName] = array(
                'instance' => $module,
                'manifest' => $manifest,
                'path' => $modulePath
            );

            $this->loadedModules[$moduleName] = true;

            logger()->debug('Module loaded', array(
                'module' => $moduleName,
                'version' => $manifest['version']
            ));

            return true;
        } finally {
            unset($this->loading[$moduleName]);
            array_pop($this->loadingStack);
        }
    }

    /**
     * Get module instance
     */
    public function getModule($moduleName)
    {
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName]['instance'];
        }
        return null;
    }

    /**
     * Get all loaded modules
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Check if module is loaded
     */
    public function isLoaded($moduleName)
    {
        return isset($this->loadedModules[$moduleName]);
    }

    /**
     * Get module manifest
     */
    public function getManifest($moduleName)
    {
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName]['manifest'];
        }
        return null;
    }

    /**
     * Get modules by type
     * @param string $type ModuleType constant
     * @return array
     */
    public function getModulesByType($type)
    {
        $result = array();
        foreach ($this->modules as $name => $data) {
            $module = $data['instance'];
            if ($module->getType() === $type) {
                $result[$name] = $data;
            }
        }
        return $result;
    }

    /**
     * Get modules by capability
     * @param string $capability ModuleCapability constant
     * @return array
     */
    public function getModulesByCapability($capability)
    {
        $result = array();
        foreach ($this->modules as $name => $data) {
            $module = $data['instance'];
            if ($module->hasCapability($capability)) {
                $result[$name] = $data;
            }
        }
        return $result;
    }

    /**
     * Enable a module
     * @param string $moduleName
     * @return bool
     */
    public function enableModule($moduleName)
    {
        $this->assertValidModuleName($moduleName, 'enableModule');

        // Check if already enabled
        $enabledModules = Config::getNested($this->config, 'modules.enabled', array());
        if (in_array($moduleName, $enabledModules, true)) {
            return true;
        }

        // Load module if not loaded
        if (!$this->isLoaded($moduleName)) {
            if (!$this->loadModule($moduleName)) {
                return false;
            }
        }

        $module = $this->getModule($moduleName);
        if (!$module) {
            return false;
        }

        // Check if module can be enabled
        if (!$module->isDisableable()) {
            logger()->warning('Cannot enable/disable core module', array('module' => $moduleName));
            return false;
        }

        // Call onEnable hook
        if (!$module->onEnable()) {
            logger()->error('Module onEnable failed', array('module' => $moduleName));
            return false;
        }

        // Add to enabled modules in config
        $enabledModules[] = $moduleName;

        // Update config file
        $configPath = MANTRA_CONTENT . '/settings/config.json';
        try {
            $configData = JsonCodec::decode(FileIO::readLocked($configPath));
            if (!isset($configData['modules'])) {
                $configData['modules'] = array();
            }
            $configData['modules']['enabled'] = $enabledModules;
            FileIO::writeAtomic($configPath, JsonCodec::encode($configData));

            logger()->info('Module enabled', array('module' => $moduleName));
            return true;
        } catch (Exception $e) {
            logger()->error('Failed to update config', array(
                'module' => $moduleName,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Disable a module
     * @param string $moduleName
     * @return bool
     */
    public function disableModule($moduleName)
    {
        $this->assertValidModuleName($moduleName, 'disableModule');

        $module = $this->getModule($moduleName);
        if (!$module) {
            return false;
        }

        // Check if module can be disabled
        if (!$module->isDisableable()) {
            logger()->warning('Cannot disable core module', array('module' => $moduleName));
            return false;
        }

        // Call onDisable hook
        if (!$module->onDisable()) {
            logger()->error('Module onDisable failed', array('module' => $moduleName));
            return false;
        }

        // Remove from enabled modules in config
        $enabledModules = Config::getNested($this->config, 'modules.enabled', array());
        $enabledModules = array_values(array_filter($enabledModules, function ($name) use ($moduleName) {
            return $name !== $moduleName;
        }));

        // Update config file
        $configPath = MANTRA_CONTENT . '/settings/config.json';
        try {
            $configData = JsonCodec::decode(FileIO::readLocked($configPath));
            if (!isset($configData['modules'])) {
                $configData['modules'] = array();
            }
            $configData['modules']['enabled'] = $enabledModules;
            FileIO::writeAtomic($configPath, JsonCodec::encode($configData));

            logger()->info('Module disabled', array('module' => $moduleName));
            return true;
        } catch (Exception $e) {
            logger()->error('Failed to update config', array(
                'module' => $moduleName,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Uninstall a module (delete files and data)
     * @param string $moduleName
     * @return bool
     */
    public function uninstallModule($moduleName)
    {
        $this->assertValidModuleName($moduleName, 'uninstallModule');

        $module = $this->getModule($moduleName);
        if (!$module) {
            return false;
        }

        // Check if module can be deleted
        if (!$module->isDeletable()) {
            logger()->warning('Cannot delete core module', array('module' => $moduleName));
            return false;
        }

        // Disable first
        if ($this->isLoaded($moduleName)) {
            $this->disableModule($moduleName);
        }

        // Call onUninstall hook
        if (!$module->onUninstall()) {
            logger()->error('Module onUninstall failed', array('module' => $moduleName));
            return false;
        }

        logger()->info('Module uninstalled', array('module' => $moduleName));
        return true;
    }

    /**
     * Discover all available modules (installed but not necessarily enabled)
     * @return array Array of module manifests keyed by module ID
     */
    public function discoverModules()
    {
        $discovered = array();

        if (!is_dir(MANTRA_MODULES)) {
            return $discovered;
        }

        $dirs = scandir(MANTRA_MODULES);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $modulePath = MANTRA_MODULES . '/' . $dir;
            if (!is_dir($modulePath)) {
                continue;
            }

            $manifestPath = $modulePath . '/module.json';
            if (!file_exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = JsonCodec::decode(file_get_contents($manifestPath));

                // Determine module ID
                $moduleId = isset($manifest['id']) ? $manifest['id'] :
                    (isset($manifest['name']) ? strtolower($manifest['name']) : $dir);

                $discovered[$moduleId] = array(
                    'id' => $moduleId,
                    'path' => $modulePath,
                    'manifest' => $manifest,
                    'enabled' => $this->isLoaded($moduleId),
                );
            } catch (Exception $e) {
                logger()->warning('Failed to read module manifest', array(
                    'module' => $dir,
                    'error' => $e->getMessage()
                ));
            }
        }

        return $discovered;
    }

    /**
     * Get module information (for admin UI)
     * @param string $moduleName
     * @return array|null
     */
    public function getModuleInfo($moduleName)
    {
        $this->assertValidModuleName($moduleName, 'getModuleInfo');

        if (!isset($this->modules[$moduleName])) {
            return null;
        }

        $data = $this->modules[$moduleName];
        $module = $data['instance'];

        return array(
            'id' => $module->getId(),
            'name' => $module->getName(),
            'description' => $module->getDescription(),
            'version' => $module->getVersion(),
            'author' => $module->getAuthor(),
            'homepage' => $module->getHomepage(),
            'license' => $module->getLicense(),
            'type' => $module->getType(),
            'capabilities' => $module->getCapabilities(),
            'dependencies' => $module->getDependencies(),
            'disableable' => $module->isDisableable(),
            'deletable' => $module->isDeletable(),
            'has_settings' => $module->hasSettings(),
            'has_translations' => $module->hasTranslations(),
            'path' => $data['path'],
            'enabled' => true,
        );
    }
}
