<?php
/**
 * FileIO - Atomic file operations with locking
 *
 * Provides safe file read/write/delete with:
 * - Shared locks for reading (LOCK_SH)
 * - Exclusive locks for writing/deleting (LOCK_EX)
 * - Atomic writes via temp file + rename
 * - File size validation (10MB limit)
 * - Windows compatibility (unlink before rename)
 */

class FileIOException extends Exception
{
    private $path;

    public function __construct($message, $path = null, $code = 0, Exception $previous = null)
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

    const MAX_FILE_SIZE = 10485760; // 10MB
    const LOCK_EXTENSION = '.lock';

    /**
     * Read file contents with shared lock.
     *
     * @param string $path Absolute file path
     * @return string Raw file contents
     * @throws FileIOException If file not found, unreadable, or too large
     */
    public static function readLocked($path)
    {
        if (!file_exists($path)) {
            throw new FileIOException('File not found', $path);
        }

        $size = @filesize($path);
        if ($size === false) {
            throw new FileIOException('Failed to get file size', $path);
        }
        self::validateFileSize($size);

        $lockHandle = self::acquireLock($path, LOCK_SH);

        try {
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
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new FileIOException('Failed to create directory', $path);
            }
        }

        self::validateFileSize(strlen($content));

        $lockHandle = self::acquireLock($path, LOCK_EX);

        try {
            $tmp = $path . '.tmp.' . self::randomSuffix();
            $bytes = file_put_contents($tmp, $content, LOCK_EX);
            if ($bytes === false) {
                throw new FileIOException('Failed to write temp file', $path);
            }

            // Windows: delete target first (rename cannot overwrite on Windows)
            if (DIRECTORY_SEPARATOR === '\\' && file_exists($path)) {
                if (!@unlink($path)) {
                    @unlink($tmp);
                    throw new FileIOException('Failed to remove existing file for replacement', $path);
                }
            }

            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new FileIOException('Failed to replace file', $path);
            }

            return true;
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
        if (!file_exists($path)) {
            return false;
        }

        try {
            $lockHandle = self::acquireLock($path, LOCK_EX);
        } catch (Exception $e) {
            return false;
        }

        try {
            $result = @unlink($path);
            self::releaseLock($lockHandle, $path, true);
            return $result;
        } catch (Exception $e) {
            self::releaseLock($lockHandle);
            return false;
        }
    }

    /**
     * Validate file size against maximum limit.
     *
     * @param int $size File size in bytes
     * @throws FileIOException If size exceeds maximum
     */
    public static function validateFileSize($size)
    {
        if ($size > self::MAX_FILE_SIZE) {
            throw new FileIOException(
                'File size exceeds maximum limit (' . self::MAX_FILE_SIZE . ' bytes)'
            );
        }
    }

    /**
     * Clean up orphaned lock files older than specified time.
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
     * @param string|null $path Optional file path for lock cleanup
     * @param bool $cleanup Whether to delete lock file after release
     */
    private static function releaseLock($lockHandle, $path = null, $cleanup = false)
    {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        if ($cleanup && $path !== null) {
            @unlink($path . self::LOCK_EXTENSION);
        }
    }

    /**
     * Generate random suffix for temporary files.
     *
     * @return string Random hex string
     */
    private static function randomSuffix()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(8));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(8);
            if ($bytes !== false) {
                return bin2hex($bytes);
            }
        }

        return str_replace('.', '', uniqid('', true));
    }
}
