<?php declare(strict_types=1);
/**
 * ModuleCapability - Defines module capability flags
 * Modules can declare what features they provide
 */

namespace Module;

class ModuleCapability
{
    // Core capabilities
    public const ROUTES = 'routes';              // Registers routes
    public const HOOKS = 'hooks';                // Provides hooks for other modules
    public const CONTENT_TYPE = 'content_type';  // Registers custom content types
    public const ADMIN_UI = 'admin_ui';          // Provides admin interface
    public const SETTINGS = 'settings';          // Has configurable settings
    public const PARTIALS = 'partials';           // Provides template partials
    public const TEMPLATES = 'templates';        // Provides templates
    public const TRANSLATIONS = 'translations';  // Provides translations
    public const API = 'api';                    // Provides API endpoints
    public const CLI = 'cli';                    // Provides CLI commands
    public const MIDDLEWARE = 'middleware';      // Provides middleware
    public const ASSETS = 'assets';              // Provides static assets (CSS/JS)

    /**
     * Get all available capabilities
     * @return array
     */
    public static function all()
    {
        return [
            self::ROUTES,
            self::HOOKS,
            self::CONTENT_TYPE,
            self::ADMIN_UI,
            self::SETTINGS,
            self::PARTIALS,
            self::TEMPLATES,
            self::TRANSLATIONS,
            self::API,
            self::CLI,
            self::MIDDLEWARE,
            self::ASSETS,
        ];
    }

    /**
     * Validate capability name
     * @param string $capability
     * @return bool
     */
    public static function isValid($capability)
    {
        return in_array($capability, self::all(), true);
    }
}
