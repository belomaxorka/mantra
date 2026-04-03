<?php declare(strict_types=1);
/**
 * ModuleType - Defines standard module types
 * Helps categorize modules for better organization
 */

namespace Module;

class ModuleType
{
    public const CORE = 'core';              // Core system modules (cannot be disabled)
    public const FEATURE = 'feature';        // Feature modules (pages, posts, etc.)
    public const ADMIN = 'admin';            // Admin panel modules
    public const INTEGRATION = 'integration'; // Third-party integrations
    public const THEME = 'theme';            // Theme-related modules
    public const UTILITY = 'utility';        // Utility/helper modules
    public const CUSTOM = 'custom';          // Custom/user-created modules

    /**
     * Get all available types
     * @return array
     */
    public static function all()
    {
        return [
            self::CORE,
            self::FEATURE,
            self::ADMIN,
            self::INTEGRATION,
            self::THEME,
            self::UTILITY,
            self::CUSTOM,
        ];
    }

    /**
     * Validate type name
     * @param string $type
     * @return bool
     */
    public static function isValid($type)
    {
        return in_array($type, self::all(), true);
    }
}
