<?php declare(strict_types=1);

namespace Storage;

use RuntimeException;
use Throwable;

class FileTransactionException extends RuntimeException
{
}

/**
 * Crash-recoverable transaction for a small set of project files.
 *
 * Original contents are journaled before the first mutation. A failed commit
 * rolls every target back; Application startup recovers journals left by a
 * terminated PHP process.
 */
final class FileTransaction
{
    private $operations = [];
    private $root;

    public function __construct($root = null)
    {
        $this->root = $root ?? (MANTRA_STORAGE . '/transactions');
    }

    public function write($path, $content)
    {
        FileIO::validateFileSize(strlen((string)$content));
        $this->operations[] = [
            'type' => 'write',
            'path' => self::normalizeTarget($path),
            'content' => (string)$content,
        ];
        return $this;
    }

    public function delete($path)
    {
        $this->operations[] = [
            'type' => 'delete',
            'path' => self::normalizeTarget($path),
        ];
        return $this;
    }

    public function commit()
    {
        if (empty($this->operations)) {
            return true;
        }

        return FileIO::withExclusiveLock($this->root . '/.global', function () {
            $directory = $this->createJournal();
            try {
                foreach ($this->operations as $operation) {
                    if ($operation['type'] === 'write') {
                        FileIO::writeAtomic($operation['path'], $operation['content']);
                    } else {
                        FileIO::deleteLocked($operation['path']);
                    }
                }

                $journal = self::readJournal($directory);
                $journal['state'] = 'committed';
                self::writeJournal($directory, $journal);
                self::removeDirectory($directory);
                return true;
            } catch (Throwable $error) {
                try {
                    self::rollbackDirectory($directory);
                    self::removeDirectory($directory);
                } catch (Throwable $rollbackError) {
                    throw new FileTransactionException(
                        'File transaction failed and automatic rollback is pending: '
                        . $rollbackError->getMessage(),
                        0,
                        $error,
                    );
                }

                throw new FileTransactionException('File transaction failed: ' . $error->getMessage(), 0, $error);
            }
        });
    }

    /** Recover incomplete transactions left by an interrupted request. */
    public static function recoverPending($root = null)
    {
        $root ??= (MANTRA_STORAGE . '/transactions');
        if (!is_dir($root)) {
            return 0;
        }

        return FileIO::withExclusiveLock($root . '/.global', function () use ($root) {
            $recovered = 0;
            $directories = glob($root . '/tx-*', GLOB_ONLYDIR);
            foreach (is_array($directories) ? $directories : [] as $directory) {
                try {
                    // No target is ever mutated before a complete prepared
                    // journal exists, so an interrupted preparation is safe
                    // to discard without touching application files.
                    if (!is_file($directory . '/journal.json')) {
                        self::removeDirectory($directory);
                        continue;
                    }
                    $journal = self::readJournal($directory);
                    if (($journal['state'] ?? '') === 'prepared') {
                        self::rollbackDirectory($directory);
                        $recovered++;
                    }
                    self::removeDirectory($directory);
                } catch (Throwable $e) {
                    if (function_exists('logger')) {
                        logger()->critical('Failed to recover file transaction', [
                            'directory' => $directory,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            return $recovered;
        });
    }

    private function createJournal()
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0o755, true) && !is_dir($this->root)) {
            throw new FileTransactionException('Failed to create transaction root');
        }

        $directory = $this->root . '/tx-' . bin2hex(random_bytes(12));
        if (!mkdir($directory, 0o700)) {
            throw new FileTransactionException('Failed to create transaction directory');
        }

        try {
            self::writeJournal($directory, [
                'state' => 'preparing',
                'targets' => [],
            ]);

            $targets = [];
            foreach ($this->operations as $index => $operation) {
                $path = $operation['path'];
                $existed = is_file($path);
                $backup = null;
                if ($existed) {
                    $backup = $index . '.backup';
                    FileIO::writeAtomic($directory . '/' . $backup, FileIO::readLocked($path));
                }
                $targets[] = [
                    'path' => $path,
                    'existed' => $existed,
                    'backup' => $backup,
                ];
            }

            self::writeJournal($directory, [
                'state' => 'prepared',
                'targets' => $targets,
            ]);
            return $directory;
        } catch (Throwable $error) {
            try {
                self::removeDirectory($directory);
            } catch (Throwable $cleanupError) {
                throw new FileTransactionException(
                    'Failed to prepare file transaction and clean its journal: '
                    . $cleanupError->getMessage(),
                    0,
                    $error,
                );
            }
            throw new FileTransactionException(
                'Failed to prepare file transaction: ' . $error->getMessage(),
                0,
                $error,
            );
        }
    }

    private static function rollbackDirectory($directory): void
    {
        $journal = self::readJournal($directory);
        $targets = isset($journal['targets']) && is_array($journal['targets'])
            ? array_reverse($journal['targets'])
            : [];

        foreach ($targets as $target) {
            $path = self::normalizeTarget($target['path'] ?? '');
            if (!empty($target['existed'])) {
                $backup = basename((string)($target['backup'] ?? ''));
                if ($backup === '') {
                    throw new FileTransactionException('Transaction backup is missing');
                }
                FileIO::writeAtomic($path, FileIO::readLocked($directory . '/' . $backup));
            } elseif (file_exists($path) && !FileIO::deleteLocked($path)) {
                throw new FileTransactionException('Failed to remove newly created transaction target');
            }
        }
    }

    private static function writeJournal($directory, $journal): void
    {
        FileIO::writeAtomic($directory . '/journal.json', \JsonCodec::encode($journal, true));
    }

    private static function readJournal($directory)
    {
        $journal = \JsonCodec::decode(FileIO::readLocked($directory . '/journal.json'));
        if (!is_array($journal)) {
            throw new FileTransactionException('Invalid transaction journal');
        }
        return $journal;
    }

    private static function normalizeTarget($path)
    {
        $path = str_replace("\0", '', (string)$path);
        $candidate = file_exists($path) ? realpath($path) : realpath(dirname($path));
        $root = realpath(MANTRA_ROOT);
        if ($candidate === false || $root === false) {
            throw new FileTransactionException('Transaction target cannot be resolved');
        }
        if (!file_exists($path)) {
            $candidate .= DIRECTORY_SEPARATOR . basename($path);
        }

        if (!FileIO::pathIsWithin($candidate, $root)) {
            throw new FileTransactionException('Transaction target escapes the project root');
        }
        return $candidate;
    }

    private static function removeDirectory($directory): void
    {
        $resolved = realpath($directory);
        $projectRoot = realpath(MANTRA_ROOT);
        if ($resolved === false || $projectRoot === false
            || !str_starts_with(basename($resolved), 'tx-')
            || !FileIO::pathIsWithin($resolved, $projectRoot)) {
            throw new FileTransactionException('Unsafe transaction cleanup path');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        if (!@rmdir($resolved)) {
            throw new FileTransactionException('Failed to remove transaction directory');
        }
    }
}
