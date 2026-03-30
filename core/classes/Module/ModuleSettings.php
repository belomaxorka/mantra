<?php
/**
 * ModuleSettings - Schema-driven settings storage for modules.
 *
 * - Values stored at: content/settings/<module>.json
 * - Schema stored at: modules/<module>/settings.schema.php
 * - Uses FileIO for atomic writes and JsonCodec for encoding.
 */

namespace Module;

use Storage\FileIO;
use Config;
use JsonCodec;
use Exception;

class ModuleSettings
{
    private $module;
    private $path;

    private $schemaLoaded = false;
    private $schema = null;

    private $loaded = false;
    private $data = array();
    private $defaults = array();
    private $raw = array();
    private $dirty = false;

    public function __construct($module)
    {
        $this->module = (string)$module;
        $this->path = MANTRA_CONTENT . '/settings/' . $this->module . '.json';
    }

    /**
     * Load schema array (or null if none).
     */
    public function schema()
    {
        if ($this->schemaLoaded) {
            return $this->schema;
        }

        $this->schemaLoaded = true;
        $schemaPath = MANTRA_MODULES . '/' . $this->module . '/settings.schema.php';
        if (!file_exists($schemaPath)) {
            $this->schema = null;
            return null;
        }

        $schema = require $schemaPath;
        if (!is_array($schema)) {
            $this->schema = null;
            return null;
        }

        $this->schema = $schema;
        return $this->schema;
    }

    /**
     * Load settings from disk; applies migrations + defaults; writes back if changed.
     */
    public function load()
    {
        if ($this->loaded) {
            return $this;
        }
        $this->loaded = true;

        $schema = $this->schema();

        $raw = array();
        if (file_exists($this->path)) {
            try {
                $fileContent = FileIO::readLocked($this->path);
                $decoded = JsonCodec::decode($fileContent);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            } catch (Exception $e) {
                // Treat unreadable settings as empty.
                logger()->warning('Failed to read module settings, using empty doc', array(
                    'module' => $this->module,
                    'path' => $this->path,
                    'error' => $e->getMessage(),
                ));
                $raw = array();
            }
        }

        $this->raw = $raw;

        // Compute defaults from schema field defaults.
        $defaults = array();
        if (is_array($schema) && !empty($schema['tabs']) && is_array($schema['tabs'])) {
            foreach ($schema['tabs'] as $tab) {
                if (empty($tab['fields']) || !is_array($tab['fields'])) {
                    continue;
                }
                foreach ($tab['fields'] as $field) {
                    if (!is_array($field) || empty($field['path']) || !array_key_exists('default', $field)) {
                        continue;
                    }
                    Config::setNested($defaults, (string)$field['path'], $field['default']);
                }
            }
        }
        $this->defaults = $defaults;

        // Treat stored JSON as overrides-only: merge defaults over raw overrides.
        $data = Config::deepMerge($defaults, $raw);

        // Apply migrations + schema_version normalization on merged document.
        $dirty = false;
        if (is_array($schema)) {
            $dirty = $this->applyMigrations($data, $schema) || $dirty;
        }

        $this->data = $data;
        $this->dirty = $dirty;

        if ($dirty) {
            $this->save();
        }

        return $this;
    }

    public function all()
    {
        $this->load();
        return $this->data;
    }

    public function get($path, $default = null)
    {
        $this->load();
        return Config::getNested($this->data, (string)$path, $default);
    }

    public function has($path)
    {
        $this->load();
        return Config::hasNested($this->data, (string)$path);
    }

    public function set($path, $value)
    {
        $this->load();
        Config::setNested($this->data, (string)$path, $value);
        return $this;
    }

    public function setMultiple($values)
    {
        $this->load();
        if (!is_array($values)) {
            return $this;
        }
        foreach ($values as $path => $value) {
            Config::setNested($this->data, (string)$path, $value);
        }
        return $this;
    }

    public function save()
    {
        $this->load();

        $schema = $this->schema();
        $schemaVersion = is_array($schema) && isset($schema['version']) ? (int)$schema['version'] : 0;

        $defaults = is_array($this->defaults) ? $this->defaults : array();

        // Persist as overrides-only (diff from field defaults) for consistency with config.json.
        // Unlike global config, module settings may contain module-specific extra keys not declared in schema.
        // Preserve such keys to avoid data loss.
        $overrides = self::diffOverridesPreserveUnknown($defaults, $this->data);
        if (!is_array($overrides)) {
            $overrides = array();
        }

        if ($schemaVersion > 0) {
            $overrides['schema_version'] = $schemaVersion;
        }

        FileIO::writeAtomic($this->path, JsonCodec::encode($overrides));
        return true;
    }

    private function applyMigrations(&$data, $schema)
    {
        $dirty = false;

        $to = isset($schema['version']) ? (int)$schema['version'] : 0;
        $from = isset($data['schema_version']) ? (int)$data['schema_version'] : 0;

        if ($to > 0 && $from < $to) {
            if (isset($schema['migrate']) && is_callable($schema['migrate'])) {
                $data = call_user_func($schema['migrate'], $data, $from, $to);
                if (!is_array($data)) {
                    $data = array();
                }
            }
            $data['schema_version'] = $to;
            $dirty = true;
        } elseif ($to > 0 && !isset($data['schema_version'])) {
            $data['schema_version'] = $to;
            $dirty = true;
        }

        return $dirty;
    }


    /**
     * Diff current settings against defaults and return overrides-only structure,
     * preserving unknown keys present in the current document.
     */
    private static function diffOverridesPreserveUnknown($defaults, $current)
    {
        if (!is_array($defaults) || !is_array($current)) {
            if ($defaults === $current) {
                return null;
            }
            return $current;
        }

        $isAssocDefaults = Config::isAssoc($defaults);
        $isAssocCurrent = Config::isAssoc($current);

        // For list arrays, treat as atomic.
        if (!$isAssocDefaults || !$isAssocCurrent) {
            if ($defaults === $current) {
                return null;
            }
            return $current;
        }

        $out = array();
        $hasAny = false;

        // Known keys: diff against defaults.
        foreach ($defaults as $k => $defVal) {
            if (!array_key_exists($k, $current)) {
                continue;
            }

            $curVal = $current[$k];
            $child = self::diffOverridesPreserveUnknown($defVal, $curVal);
            if ($child !== null) {
                $out[$k] = $child;
                $hasAny = true;
            }
        }

        // Unknown keys: preserve as-is.
        foreach ($current as $k => $curVal) {
            if (array_key_exists($k, $defaults)) {
                continue;
            }
            $out[$k] = $curVal;
            $hasAny = true;
        }

        return $hasAny ? $out : null;
    }
}

