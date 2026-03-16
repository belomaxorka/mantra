<?php
/**
 * ConfigSettings - Schema-driven settings storage for global config.
 *
 * - Values stored at: content/settings/config.json
 * - Schema stored at: core/settings/config.settings.schema.php
 * - Persists config.json as overrides-only (diff from Config::defaults()).
 */
class ConfigSettings
{
    private $path;

    private $schemaLoaded = false;
    private $schema = null;

    private $loaded = false;
    private $data = array();
    private $defaults = array();

    public function __construct()
    {
        $this->path = MANTRA_CONTENT . '/settings/config.json';
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
        $schemaPath = MANTRA_CORE . '/settings/config.settings.schema.php';
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
     * Load config from disk; applies schema migrations + field defaults.
     */
    public function load()
    {
        if ($this->loaded) {
            return $this;
        }
        $this->loaded = true;

        $schema = $this->schema();

        $this->defaults = Config::defaults();
        $raw = array();

        if (file_exists($this->path)) {
            try {
                $decoded = JsonFile::read($this->path, array('recover' => true));
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            } catch (Exception $e) {
                logger()->warning('Failed to read config.json, using defaults', array(
                    'path' => $this->path,
                    'error' => $e->getMessage(),
                ));
            }
        }

        // Backward-compat: config.json may contain a full merged config. Treat it as overrides anyway.
        $data = Config::deepMerge($this->defaults, $raw);

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

        $overrides = Config::diffOverrides($this->defaults, $this->data);
        if (!is_array($overrides)) {
            $overrides = array();
        }

        if ($schemaVersion > 0) {
            $overrides['schema_version'] = $schemaVersion;
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return JsonFile::write($this->path, $overrides);
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
                if (!Config::hasNested($data, $path)) {
                    Config::setNested($data, $path, $field['default']);
                    $dirty = true;
                }
            }
        }

        return $dirty;
    }

}
