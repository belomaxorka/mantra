<?php
/**
 * ModuleCapability - Defines module capability flags
 * Modules can declare what features they provide
 */

class ModuleCapability {
    // Core capabilities
    const ROUTES = 'routes';              // Registers routes
    const HOOKS = 'hooks';                // Provides hooks for other modules
    const CONTENT_TYPE = 'content_type';  // Registers custom content types
    const ADMIN_UI = 'admin_ui';          // Provides admin interface
    const SETTINGS = 'settings';          // Has configurable settings
    const WIDGETS = 'widgets';            // Provides widgets
    const TEMPLATES = 'templates';        // Provides templates
    const TRANSLATIONS = 'translations';  // Provides translations
    const API = 'api';                    // Provides API endpoints
    const CLI = 'cli';                    // Provides CLI commands
    const MIDDLEWARE = 'middleware';      // Provides middleware
    const ASSETS = 'assets';              // Provides static assets (CSS/JS)
    
    /**
     * Get all available capabilities
     * @return array
     */
    public static function all() {
        return array(
            self::ROUTES,
            self::HOOKS,
            self::CONTENT_TYPE,
            self::ADMIN_UI,
            self::SETTINGS,
            self::WIDGETS,
            self::TEMPLATES,
            self::TRANSLATIONS,
            self::API,
            self::CLI,
            self::MIDDLEWARE,
            self::ASSETS,
        );
    }
    
    /**
     * Validate capability name
     * @param string $capability
     * @return bool
     */
    public static function isValid($capability) {
        return in_array($capability, self::all(), true);
    }
}
