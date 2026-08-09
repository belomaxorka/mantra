<?php declare(strict_types=1);

use Storage\FileIO;

/**
 * Single authority for installation state and installer serialization.
 */
final class InstallationState
{
    public static function isInstalled(): bool
    {
        if (file_exists(self::markerPath())) {
            return true;
        }

        $users = glob(MANTRA_CONTENT . '/users/*.json');
        return is_array($users) && count($users) > 0;
    }

    /** @return resource */
    public static function acquireLock()
    {
        if (!is_dir(MANTRA_STORAGE) && !mkdir(MANTRA_STORAGE, 0o755, true) && !is_dir(MANTRA_STORAGE)) {
            throw new RuntimeException('Unable to create storage directory for installer lock');
        }

        $handle = fopen(MANTRA_STORAGE . '/install.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('Unable to open installer lock');
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire installer lock');
        }

        return $handle;
    }

    /** @param resource|null $handle */
    public static function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public static function markInstalled(): void
    {
        FileIO::writeAtomic(self::markerPath(), JsonCodec::encode([
            'installed_at' => gmdate('c'),
            'version' => MANTRA_PROJECT_INFO['version'],
        ]));
    }

    private static function markerPath(): string
    {
        return MANTRA_CONTENT . '/settings/installed.json';
    }
}
