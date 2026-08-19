<?php declare(strict_types=1);

/** Single persistence authority for content/settings/config.json. */
final class ConfigRepository extends SettingsRepository
{
    public function __construct($path = null, $schemaPath = null, $autoPersistMigrations = true)
    {
        parent::__construct(
            $path ?? (MANTRA_CONTENT . '/settings/config.json'),
            $schemaPath ?? (MANTRA_CORE . '/config.settings.schema.php'),
            $autoPersistMigrations,
        );
    }

    protected function buildDefaults($schema)
    {
        return Config::defaults();
    }

    protected function preserveUnknownKeys(): bool
    {
        // Global config is an explicit allow-list defined by Config::defaults().
        return false;
    }

    protected function afterSave(): void
    {
        $GLOBALS['MANTRA_CONFIG'] = $this->data;
    }

    protected function normalizeData($data)
    {
        if (isset($data['site']['url']) && is_string($data['site']['url'])) {
            $data['site']['url'] = rtrim($data['site']['url'], '/');
        }
        return $data;
    }
}
