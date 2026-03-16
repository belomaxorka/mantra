<?php
/**
 * ModuleInterface - Contract for all modules
 * Defines the standard interface that all modules must implement
 */

interface ModuleInterface {
    /**
     * Initialize the module
     * Called after module is loaded and dependencies are resolved
     */
    public function init();
    
    /**
     * Get module metadata
     * @return array Module manifest data
     */
    public function getManifest();
    
    /**
     * Get module ID (kebab-case identifier)
     * @return string
     */
    public function getId();
    
    /**
     * Get module name (human-readable)
     * @return string
     */
    public function getName();
    
    /**
     * Get module version
     * @return string
     */
    public function getVersion();
    
    /**
     * Get module description
     * @return string
     */
    public function getDescription();
    
    /**
     * Check if module can be disabled
     * @return bool
     */
    public function isDisableable();
    
    /**
     * Check if module can be deleted
     * @return bool
     */
    public function isDeletable();
    
    /**
     * Get module dependencies
     * @return array Array of module IDs this module depends on
     */
    public function getDependencies();
    
    /**
     * Called when module is being enabled
     * @return bool True on success, false on failure
     */
    public function onEnable();
    
    /**
     * Called when module is being disabled
     * @return bool True on success, false on failure
     */
    public function onDisable();
    
    /**
     * Called when module is being uninstalled
     * Should clean up module data, settings, etc.
     * @return bool True on success, false on failure
     */
    public function onUninstall();
}
