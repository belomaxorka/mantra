<?php
/**
 * ModuleType - Defines standard module types
 * Helps categorize modules for better organization
 */

class ModuleType {
    const CORE = 'core';              // Core system modules (cannot be disabled)
    const FEATURE = 'feature';        // Feature modules (pages, posts, etc.)
    const ADMIN = 'admin';            // Admin panel modules
    const INTEGRATION = 'integration'; // Third-party integrations
    const THEME = 'theme';            // Theme-related modules
    const UTILITY = 'utility';        // Utility/helper modules
    const CUSTOM = 'custom';          // Custom/user-created modules
    
    /**
     * Get all available types
     * @return array
     */
    public static function all() {
        return array(
            self::CORE,
            self::FEATURE,
            self::ADMIN,
            self::INTEGRATION,
            self::THEME,
            self::UTILITY,
            self::CUSTOM,
        );
    }
    
    /**
     * Validate type name
     * @param string $type
     * @return bool
     */
    public static function isValid($type) {
        return in_array($type, self::all(), true);
    }
    
    /**
     * Get human-readable type name
     * @param string $type
     * @return string
     */
    public static function getLabel($type) {
        $labels = array(
            self::CORE => 'Core Module',
            self::FEATURE => 'Feature Module',
            self::ADMIN => 'Admin Module',
            self::INTEGRATION => 'Integration',
            self::THEME => 'Theme Module',
            self::UTILITY => 'Utility',
            self::CUSTOM => 'Custom Module',
        );
        
        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }
}
