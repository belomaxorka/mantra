<?php declare(strict_types=1);
/**
 * FileIO - Atomic file operations with locking
 *
 * Provides safe file read/write/delete with:
 * - Shared locks for reading (LOCK_SH)
 * - Exclusive locks for writing/deleting (LOCK_EX)
 * - Atomic writes via temp file + rename
 * - File size validation (10MB limit)
 * - Replacement without deleting the last known-good target first
 */

namespace Storage;

use Exception;

class FileIOException extends Exception
{
    private $path;

    public function __construct($message, $path = null, $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->path = $path;
    }

    public function getPath()
    {
        return $this->path;
    }
}

class FileIO
{
    public const MAX_FILE_SIZE = 10485760; // 10MB
    public const LOCK_EXTENSION = '.lock';
    private const REPLACE_MAX_ATTEMPTS = 6;
    private const REPLACE_RETRY_DELAY_US = 2000;

    /**
     * Resolve an untrusted relative path inside a trusted root.
     *
     * @throws FileIOException when the path is absolute, traverses, is a
     * symlink escape, or cannot be resolved as requested.
     */
    public static function resolveWithin($root, $relativePath, $mustExist = true)
    {
        $rootReal = realpath($root);
        if ($rootReal === false || !is_dir($rootReal)) {
            throw new FileIOException('Trusted root does not exist', $root);
        }

        $relative = str_replace('\\', '/', str_replace("\0", '', (string)$relativePath));
        if ($relative === '' || str_starts_with($relative, '/')
            || preg_match('/^[a-zA-Z]:\//', $relative) === 1) {
            throw new FileIOException('Path must be relative', $relativePath);
        }

        $segments = explode('/', $relative);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new FileIOException('Invalid relative path segment', $relativePath);
            }
        }

        $candidate = $rootReal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $resolved = $mustExist ? realpath($candidate) : realpath(dirname($candidate));
        if ($resolved === false) {
            throw new FileIOException('Path cannot be resolved', $candidate);
        }
        if (!$mustExist) {
            $resolved .= DIRECTORY_SEPARATOR . basename($candidate);
        }

        if (!self::pathIsWithin($resolved, $rootReal)) {
            throw new FileIOException('Path escapes trusted root', $candidate);
        }

        return $resolved;
    }

    public static function isWithin($path, $root): bool
    {
        $pathReal = realpath($path);
        $rootReal = realpath($root);
        if ($pathReal === false || $rootReal === false) {
            return false;
        }
        return self::pathIsWithin($pathReal, $rootReal);
    }

    /** Compare already resolved paths using platform-appropriate casing. */
    public static function pathIsWithin($path, $root, $allowRoot = false): bool
    {
        $path = rtrim((string)$path, '/\\');
        $root = rtrim((string)$root, '/\\');
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        if ($allowRoot && $path === $root) {
            return true;
        }
        return str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Read file contents with shared lock.
     *
     * @param string $path Absolute file path
     * @return string Raw file contents
     * @throws FileIOException If file not found, unreadable, or too large
     */
    public static function readLocked($path)
    {
        $lockHandle = self::acquireLock($path, LOCK_SH);

        try {
            if (!is_file($path)) {
                throw new FileIOException('File not found', $path);
            }
            $size = @filesize($path);
            if ($size === false) {
                throw new FileIOException('Failed to get file size', $path);
            }
            self::validateFileSize($size);

            $content = file_get_contents($path);
            if ($content === false) {
                throw new FileIOException('Failed to read file', $path);
            }
            return $content;
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Read a deployment-owned file that is immutable during a request.
     *
     * Unlike readLocked(), this does not create a sidecar lock and therefore
     * works for read-only module/theme directories. Runtime-mutable data must
     * always use readLocked() instead.
     */
    public static function readImmutable($path)
    {
        if (!is_file($path)) {
            throw new FileIOException('Immutable file not found', $path);
        }
        $size = @filesize($path);
        if ($size === false) {
            throw new FileIOException('Failed to get immutable file size', $path);
        }
        self::validateFileSize($size);

        $content = file_get_contents($path);
        if ($content === false) {
            throw new FileIOException('Failed to read immutable file', $path);
        }
        return $content;
    }

    /**
     * Write file atomically with exclusive lock (temp + rename).
     *
     * Creates parent directories if needed.
     *
     * @param string $path Absolute file path
     * @param string $content Content to write
     * @return bool True on success
     * @throws FileIOException On any failure
     */
    public static function writeAtomic($path, $content)
    {
        self::ensureParentDirectory($path);
        self::validateFileSize(strlen($content));

        $lockHandle = self::acquireLock($path, LOCK_EX);
        try {
            return self::replaceUnlocked($path, $content);
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Atomically update a file from the latest contents while holding its lock.
     * The callback receives null when the target does not exist and must return
     * the complete replacement string.
     */
    public static function updateAtomic($path, $callback)
    {
        if (!is_callable($callback)) {
            throw new FileIOException('Update callback is not callable', $path);
        }

        self::ensureParentDirectory($path);
        $lockHandle = self::acquireLock($path, LOCK_EX);
        try {
            $current = null;
            if (file_exists($path)) {
                $current = file_get_contents($path);
                if ($current === false) {
                    throw new FileIOException('Failed to read current file for update', $path);
                }
                self::validateFileSize(strlen($current));
            }

            $replacement = $callback($current);
            if (!is_string($replacement)) {
                throw new FileIOException('Update callback must return a string', $path);
            }
            self::validateFileSize(strlen($replacement));
            return self::replaceUnlocked($path, $replacement);
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Execute a critical section guarded by an exclusive sidecar lock.
     * The protected path itself does not need to exist.
     */
    public static function withExclusiveLock($path, $callback)
    {
        if (!is_callable($callback)) {
            throw new FileIOException('Lock callback is not callable', $path);
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new FileIOException('Failed to create lock directory', $path);
        }

        $lockHandle = self::acquireLock($path, LOCK_EX);
        try {
            return $callback();
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Delete file with exclusive lock.
     *
     * @param string $path Absolute file path
     * @return bool True if deleted, false if not found
     */
    public static function deleteLocked($path)
    {
        try {
            $lockHandle = self::acquireLock($path, LOCK_EX);
        } catch (Exception $e) {
            return false;
        }

        try {
            if (!file_exists($path)) {
                return false;
            }
            $result = @unlink($path);
            return $result;
        } catch (\Throwable $e) {
            return false;
        } finally {
            // Sidecar locks are stable mutex identities. Removing one after
            // unlock creates a race where waiters can lock different inodes.
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Validate file size against maximum limit.
     *
     * @param int $size File size in bytes
     * @throws FileIOException If size exceeds maximum
     */
    public static function validateFileSize($size): void
    {
        if ($size > self::MAX_FILE_SIZE) {
            throw new FileIOException(
                'File size exceeds maximum limit (' . self::MAX_FILE_SIZE . ' bytes)',
            );
        }
    }

    /**
     * Clean up lock files older than specified time.
     *
     * This is a maintenance-only operation. It must run while no application
     * worker can start, because sidecar lock files are stable mutex identities.
     *
     * @param string $directory Directory to clean
     * @param int $maxAge Maximum age in seconds (default: 1 hour)
     * @return int Number of files cleaned
     */
    public static function cleanOrphanedLocks($directory, $maxAge = 3600)
    {
        $cleaned = 0;
        $pattern = $directory . '/*' . self::LOCK_EXTENSION;

        foreach (glob($pattern) as $lockFile) {
            if (!file_exists($lockFile)) {
                continue;
            }

            $age = time() - filemtime($lockFile);
            if ($age > $maxAge) {
                $handle = @fopen($lockFile, 'c');
                if ($handle !== false) {
                    if (flock($handle, LOCK_EX | LOCK_NB)) {
                        flock($handle, LOCK_UN);
                        fclose($handle);
                        if (@unlink($lockFile)) {
                            $cleaned++;
                        }
                    } else {
                        fclose($handle);
                    }
                }
            }
        }

        return $cleaned;
    }

    /**
     * Acquire lock on a file.
     *
     * @param string $path File path to lock
     * @param int $lockType LOCK_SH or LOCK_EX
     * @return resource Lock file handle
     * @throws FileIOException If lock cannot be acquired
     */
    private static function acquireLock($path, $lockType = LOCK_EX)
    {
        $lockPath = $path . self::LOCK_EXTENSION;
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            throw new FileIOException('Failed to open lock file', $lockPath);
        }

        if (!flock($handle, $lockType)) {
            fclose($handle);
            throw new FileIOException('Failed to acquire lock on file', $path);
        }

        return $handle;
    }

    /**
     * Release lock and close file handle.
     *
     * @param resource $lockHandle Lock file handle
     */
    private static function releaseLock($lockHandle): void
    {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Generate random suffix for temporary files.
     *
     * @return string Random hex string
     */
    private static function randomSuffix()
    {
        return bin2hex(random_bytes(8));
    }

    private static function ensureParentDirectory($path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new FileIOException('Failed to create directory', $path);
        }
    }

    /** Replace a target while its sidecar lock is already held. */
    private static function replaceUnlocked($path, $content)
    {
        $tmp = $path . '.tmp.' . self::randomSuffix();
        try {
            $bytes = file_put_contents($tmp, $content, LOCK_EX);
            if ($bytes === false) {
                throw new FileIOException('Failed to write temp file', $path);
            }
            if (!self::replaceFile($tmp, $path)) {
                throw new FileIOException('Failed to replace file', $path);
            }
            return true;
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Rename the prepared file without removing the last known-good target.
     *
     * Windows can briefly reject an atomic replacement while antivirus,
     * indexing, or another reader holds a handle. Retry only errors that
     * indicate this kind of contention; structural failures fail immediately.
     */
    private static function replaceFile($tmp, $path): bool
    {
        for ($attempt = 0; $attempt < self::REPLACE_MAX_ATTEMPTS; $attempt++) {
            error_clear_last();
            if (@rename($tmp, $path)) {
                return true;
            }

            $error = error_get_last();
            if ($attempt + 1 >= self::REPLACE_MAX_ATTEMPTS
                || !self::isTransientReplaceError($error)) {
                return false;
            }

            usleep(self::REPLACE_RETRY_DELAY_US * (2 ** $attempt));
        }

        return false;
    }

    /** @param array{message?: string}|null $error */
    private static function isTransientReplaceError($error): bool
    {
        if (!is_array($error) || !isset($error['message'])) {
            return false;
        }

        $message = strtolower((string)$error['message']);
        // PHP appends the native Windows error code to localized warnings.
        // 5 = access denied, 32 = sharing violation, 33 = lock violation.
        if (preg_match('/\(code:\s*(?:5|32|33)\)/', $message) === 1) {
            return true;
        }

        foreach ([
            'access is denied',
            'being used by another process',
            'permission denied',
            'resource busy',
            'resource temporarily unavailable',
            'sharing violation',
        ] as $fragment) {
            if (str_contains($message, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
