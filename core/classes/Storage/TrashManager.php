<?php declare(strict_types=1);

namespace Storage;

use RuntimeException;

/** Recoverable replacement for destructive module and settings deletion. */
final class TrashManager
{
    private $root;

    public function __construct($root = null)
    {
        $this->root = $root ?? (MANTRA_STORAGE . '/trash');
    }

    public function move($source, $bucket, $label)
    {
        $this->assertName($bucket, 'trash bucket');
        $this->assertName($label, 'trash label');
        if (is_link($source)) {
            throw new RuntimeException('Refusing to trash a symbolic link');
        }

        $sourcePath = realpath($source);
        $projectRoot = realpath(MANTRA_ROOT);
        if ($sourcePath === false || $projectRoot === false || !FileIO::pathIsWithin($sourcePath, $projectRoot)) {
            throw new RuntimeException('Trash source is outside the project');
        }

        $bucketPath = $this->root . '/' . $bucket;
        if (!is_dir($bucketPath) && !mkdir($bucketPath, 0o700, true) && !is_dir($bucketPath)) {
            throw new RuntimeException('Failed to create trash directory');
        }

        $destination = $bucketPath . '/' . $label . '-'
            . gmdate('YmdHis') . '-' . bin2hex(random_bytes(6));
        return FileIO::withExclusiveLock($sourcePath, function () use ($sourcePath, $destination) {
            if (!@rename($sourcePath, $destination)) {
                throw new RuntimeException('Failed to move item to trash');
            }
            return $destination;
        });
    }

    public function restore($trashPath, $destination)
    {
        $trashReal = realpath($trashPath);
        $rootReal = realpath($this->root);
        $destinationParent = realpath(dirname($destination));
        $projectRoot = realpath(MANTRA_ROOT);
        if ($trashReal === false || $rootReal === false || !FileIO::pathIsWithin($trashReal, $rootReal)) {
            throw new RuntimeException('Restore source is outside trash');
        }
        if ($destinationParent === false || $projectRoot === false || !FileIO::pathIsWithin($destinationParent, $projectRoot)) {
            throw new RuntimeException('Restore destination is outside the project');
        }
        if (file_exists($destination) || is_link($destination)) {
            throw new RuntimeException('Restore destination already exists');
        }
        return FileIO::withExclusiveLock($destination, function () use ($trashReal, $destination) {
            if (!@rename($trashReal, $destination)) {
                throw new RuntimeException('Failed to restore trashed item');
            }
            return true;
        });
    }

    private function assertName($value, $label): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', (string)$value) !== 1) {
            throw new RuntimeException("Invalid {$label}");
        }
    }

}
