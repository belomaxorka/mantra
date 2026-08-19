<?php declare(strict_types=1);

class SchemaMigrationException extends RuntimeException
{
}

class UnsupportedSchemaVersionException extends SchemaMigrationException
{
}

/**
 * Single migration engine for content documents and settings stores.
 *
 * Preferred schema format:
 *
 *   'version' => 3,
 *   'migrations' => [
 *       2 => fn(array $data, int $from, int $to): array => $data,
 *       3 => fn(array $data, int $from, int $to): array => $data,
 *   ],
 *
 * The legacy one-shot `migrate` callback remains supported so third-party
 * modules can upgrade without an immediate schema rewrite.
 */
final class SchemaMigrator
{
    public static function migrate($data, $schema)
    {
        if (!is_array($data)) {
            throw new SchemaMigrationException('Migration input must be an array');
        }
        if (!is_array($schema)) {
            return $data;
        }

        $target = max(0, (int)($schema['version'] ?? 0));
        $current = max(0, (int)($data['schema_version'] ?? 0));

        if ($target === 0 || $current === $target) {
            return $data;
        }
        if ($current > $target) {
            throw new UnsupportedSchemaVersionException(
                "Document schema version {$current} is newer than supported version {$target}",
            );
        }

        if (array_key_exists('migrations', $schema)) {
            if (!is_array($schema['migrations'])) {
                throw new SchemaMigrationException('Schema migrations must be an array');
            }

            for ($version = $current + 1; $version <= $target; $version++) {
                if (array_key_exists($version, $schema['migrations'])) {
                    $callback = $schema['migrations'][$version];
                    if (!is_callable($callback)) {
                        throw new SchemaMigrationException("Migration to version {$version} is not callable");
                    }
                    $data = $callback($data, $version - 1, $version);
                    self::assertResult($data, $version);
                }
                $data['schema_version'] = $version;
            }

            return $data;
        }

        if (array_key_exists('migrate', $schema)) {
            if (!is_callable($schema['migrate'])) {
                throw new SchemaMigrationException('Legacy schema migrator is not callable');
            }
            $data = ($schema['migrate'])($data, $current, $target);
            self::assertResult($data, $target);
        }

        $data['schema_version'] = $target;
        return $data;
    }

    private static function assertResult($data, $version): void
    {
        if (!is_array($data)) {
            throw new SchemaMigrationException(
                "Migration to version {$version} must return an array; original data was not written",
            );
        }
    }
}
