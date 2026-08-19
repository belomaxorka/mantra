<?php declare(strict_types=1);

namespace Module;

use Exception;

/** Schema-backed settings repository scoped to one module. */
class ModuleSettings extends \SettingsRepository
{
    private $module;
    private static $instances = [];

    public static function instance($module)
    {
        $module = (string)$module;
        if (!isset(self::$instances[$module])) {
            self::$instances[$module] = new self($module);
        }
        return self::$instances[$module];
    }

    public function __construct($module)
    {
        $module = (string)$module;
        if (!ModuleValidator::isValidModuleId($module)) {
            throw new Exception("Invalid module settings identifier: '{$module}'");
        }

        $this->module = $module;
        parent::__construct(
            MANTRA_CONTENT . '/settings/' . $module . '.json',
            MANTRA_MODULES . '/' . $module . '/settings.schema.php',
        );
    }

    protected function buildDefaults($schema)
    {
        return self::defaultsFromTabs($schema);
    }

    protected function preserveUnknownKeys(): bool
    {
        // Module-owned settings may contain extension data not represented in
        // the admin UI schema, so unknown keys are deliberately retained.
        return true;
    }
}
