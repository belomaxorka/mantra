<?php declare(strict_types=1);

use Storage\FileIO;

class ConcurrentSettingsModificationException extends RuntimeException
{
}

/**
 * Shared implementation for global and module settings repositories.
 */
abstract class SettingsRepository implements SettingsStoreInterface
{
    protected $path;
    protected $schemaPath;
    protected $loaded = false;
    protected $schemaLoaded = false;
    protected $schema = null;
    protected $data = [];
    protected $defaults = [];
    protected $loadError = null;
    protected $sourceHash = null;

    private $autoPersistMigrations;

    public function __construct($path, $schemaPath = null, $autoPersistMigrations = true)
    {
        $this->path = (string)$path;
        $this->schemaPath = $schemaPath === null ? null : (string)$schemaPath;
        $this->autoPersistMigrations = (bool)$autoPersistMigrations;
    }

    public function schema()
    {
        if ($this->schemaLoaded) {
            return $this->schema;
        }

        $this->schemaLoaded = true;
        if ($this->schemaPath === null || !file_exists($this->schemaPath)) {
            return null;
        }

        $schema = require $this->schemaPath;
        if (!is_array($schema)) {
            throw new SchemaMigrationException('Settings schema must return an array: ' . $this->schemaPath);
        }

        $this->schema = $schema;
        return $this->schema;
    }

    public function load()
    {
        if ($this->loaded) {
            return $this;
        }
        $this->loaded = true;

        $schema = $this->schema();
        $this->defaults = $this->buildDefaults($schema);
        $raw = [];

        if (file_exists($this->path)) {
            try {
                $source = FileIO::readLocked($this->path);
                $this->sourceHash = hash('sha256', $source);
                $decoded = JsonCodec::decode($source);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Settings document must decode to an array');
                }
                $raw = $decoded;
            } catch (Throwable $e) {
                $this->loadError = $e;
                $this->data = $this->defaults;
                $this->report('warning', 'Failed to read settings; refusing automatic overwrite', $e);
                return $this;
            }
        }

        try {
            $migrated = SchemaMigrator::migrate($raw, $schema);
        } catch (UnsupportedSchemaVersionException $e) {
            // A newer application may have written fields this version does
            // not understand. Keep them readable, but make the store read-only.
            $this->loadError = $e;
            $this->data = $this->normalizeData(Config::deepMerge($this->defaults, $raw));
            $this->report('warning', 'Settings schema is newer than this runtime', $e);
            return $this;
        }
        $migrationChanged = $migrated !== $raw;

        // Migrate raw data before defaults are merged. Otherwise a newly added
        // default can shadow the legacy field a migration needs to inspect.
        $this->data = $this->normalizeData(Config::deepMerge($this->defaults, $migrated));

        if ($migrationChanged && $this->autoPersistMigrations) {
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
        if (is_array($values)) {
            foreach ($values as $path => $value) {
                Config::setNested($this->data, (string)$path, $value);
            }
        }
        return $this;
    }

    public function replace($data)
    {
        $this->load();
        if (!is_array($data)) {
            throw new InvalidArgumentException('Settings replacement must be an array');
        }
        $this->data = $data;
        return $this;
    }

    public function delete($path)
    {
        $this->load();
        $path = trim((string)$path);
        if ($path === '') {
            return false;
        }

        $parts = explode('.', $path);
        $last = array_pop($parts);
        $cursor = &$this->data;
        foreach ($parts as $part) {
            if ($part === '' || !is_array($cursor) || !array_key_exists($part, $cursor)) {
                return false;
            }
            $cursor = &$cursor[$part];
        }
        if ($last === '' || !is_array($cursor) || !array_key_exists($last, $cursor)) {
            return false;
        }

        unset($cursor[$last]);
        return true;
    }

    public function save()
    {
        $this->load();
        if ($this->loadError !== null) {
            throw new RuntimeException(
                'Refusing to overwrite unreadable settings: ' . $this->path,
                0,
                $this->loadError,
            );
        }

        $schema = $this->schema();
        $schemaVersion = is_array($schema) ? max(0, (int)($schema['version'] ?? 0)) : 0;
        if ($schemaVersion > 0) {
            $this->data['schema_version'] = $schemaVersion;
        }

        $overrides = Config::diffOverrides(
            $this->defaults,
            $this->data,
            $this->preserveUnknownKeys(),
        );
        if (!is_array($overrides)) {
            $overrides = [];
        }
        if ($schemaVersion > 0) {
            $overrides['schema_version'] = $schemaVersion;
        }

        $encoded = JsonCodec::encode($overrides);
        $expectedHash = $this->sourceHash;
        FileIO::updateAtomic($this->path, function ($current) use ($expectedHash, $encoded) {
            $actualHash = $current === null ? null : hash('sha256', $current);
            if ($actualHash !== $expectedHash) {
                throw new ConcurrentSettingsModificationException(
                    'Settings changed on disk after this repository was loaded: ' . $this->path,
                );
            }
            return $encoded;
        });
        $this->sourceHash = hash('sha256', $encoded);
        $this->afterSave();
        return true;
    }

    public function reload()
    {
        $this->loaded = false;
        $this->data = [];
        $this->defaults = [];
        $this->loadError = null;
        $this->sourceHash = null;
        return $this->load();
    }

    public function path()
    {
        return $this->path;
    }

    abstract protected function buildDefaults($schema);

    protected function preserveUnknownKeys(): bool
    {
        return true;
    }

    protected function afterSave(): void
    {
    }

    protected function normalizeData($data)
    {
        return $data;
    }

    protected static function defaultsFromTabs($schema)
    {
        $defaults = [];
        if (!is_array($schema) || empty($schema['tabs']) || !is_array($schema['tabs'])) {
            return $defaults;
        }

        foreach ($schema['tabs'] as $tab) {
            if (!is_array($tab) || empty($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }
            foreach ($tab['fields'] as $field) {
                if (!is_array($field) || empty($field['path']) || !array_key_exists('default', $field)) {
                    continue;
                }
                Config::setNested($defaults, (string)$field['path'], $field['default']);
            }
        }

        return $defaults;
    }

    private function report($level, $message, Throwable $error): void
    {
        if (function_exists('logger')) {
            logger()->{$level}($message, [
                'path' => $this->path,
                'error' => $error->getMessage(),
            ]);
            return;
        }

        error_log($message . ': ' . $error->getMessage());
    }
}
