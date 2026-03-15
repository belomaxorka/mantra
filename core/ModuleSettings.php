<?php
/**
 * ModuleSettings - Schema-driven settings storage for modules.
 *
 * - Values stored at: content/settings/<module>.json
 * - Schema stored at: modules/<module>/settings.schema.php
 * - Uses JsonFile for atomic + backed-up writes.
 */
class ModuleSettings
{
    private $module;
    private $path;

    private $schemaLoaded = false;
    private $schema = null;

    private $loaded = false;
    private $data = array();

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
        $data = array();

        if (file_exists($this->path)) {
            try {
                $data = JsonFile::read($this->path);
            } catch (Exception $e) {
                // Treat unreadable settings as empty.
                logger()->warning('Failed to read module settings, using empty doc', array(
                    'module' => $this->module,
                    'path' => $this->path,
                    'error' => $e->getMessage(),
                ));
                $data = array();
            }
        }

        $dirty = false;

        if (is_array($schema)) {
            $dirty = $this->applyMigrations($data, $schema) || $dirty;
            $dirty = $this->applyFieldDefaults($data, $schema) || $dirty;
        }

        $this->data = $data;

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
        return self::getNested($this->data, (string)$path, $default);
    }

    public function has($path)
    {
        $this->load();
        return self::hasNested($this->data, (string)$path);
    }

    public function set($path, $value)
    {
        $this->load();
        self::setNested($this->data, (string)$path, $value);
        return $this;
    }

    public function setMultiple($values)
    {
        $this->load();
        if (!is_array($values)) {
            return $this;
        }
        foreach ($values as $path => $value) {
            self::setNested($this->data, (string)$path, $value);
        }
        return $this;
    }

    public function save()
    {
        JsonFile::write($this->path, $this->data);
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

    private function applyFieldDefaults(&$data, $schema)
    {
        $dirty = false;

        if (empty($schema['tabs']) || !is_array($schema['tabs'])) {
            return false;
        }

        foreach ($schema['tabs'] as $tab) {
            if (empty($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }

            foreach ($tab['fields'] as $field) {
                if (!is_array($field) || empty($field['path'])) {
                    continue;
                }

                if (!array_key_exists('default', $field)) {
                    continue;
                }

                $path = (string)$field['path'];
                if (!self::hasNested($data, $path)) {
                    self::setNested($data, $path, $field['default']);
                    $dirty = true;
                }
            }
        }

        return $dirty;
    }

    public static function getNested($arr, $path, $default = null)
    {
        if (!is_array($arr)) {
            return $default;
        }
        $path = trim((string)$path);
        if ($path === '') {
            return $default;
        }

        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $part) {
            if ($part === '') {
                return $default;
            }
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }

    public static function hasNested($arr, $path)
    {
        if (!is_array($arr)) {
            return false;
        }
        $path = trim((string)$path);
        if ($path === '') {
            return false;
        }

        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $part) {
            if ($part === '') {
                return false;
            }
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return false;
            }
            $cur = $cur[$part];
        }
        return true;
    }

    public static function setNested(&$arr, $path, $value)
    {
        if (!is_array($arr)) {
            $arr = array();
        }

        $path = trim((string)$path);
        if ($path === '') {
            return;
        }

        $parts = explode('.', $path);
        $cur =& $arr;

        $last = array_pop($parts);
        foreach ($parts as $part) {
            if ($part === '') {
                return;
            }
            if (!isset($cur[$part]) || !is_array($cur[$part])) {
                $cur[$part] = array();
            }
            $cur =& $cur[$part];
        }

        if ($last === '') {
            return;
        }
        $cur[$last] = $value;
    }
}
