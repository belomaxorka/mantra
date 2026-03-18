<?php
/**
 * AbstractFileStorage - Base class for file-based storage implementations
 *
 * Provides common functionality for file operations:
 * - File locking mechanism
 * - Random suffix generation for temp files
 * - File size validation
 */

abstract class AbstractFileStorage
{

    const MAX_FILE_SIZE = 10485760; // 10MB
    const LOCK_EXTENSION = '.lock';

    /**
     * Open lock file for the given path
     *
     * @param string $path File path to lock
     * @return resource File handle for lock file
     * @throws Exception If lock file cannot be opened
     */
    protected static function openLock($path)
    {
        $lockPath = $path . static::LOCK_EXTENSION;
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            throw new Exception('Failed to open lock file: ' . $lockPath);
        }
        return $handle;
    }

    /**
     * Acquire lock on file handle
     *
     * @param string $path File path to lock
     * @param int $lockType Lock type (LOCK_SH or LOCK_EX)
     * @return resource File handle with acquired lock
     * @throws Exception If lock cannot be acquired
     */
    protected static function acquireLock($path, $lockType = LOCK_EX)
    {
        $lockHandle = static::openLock($path);

        if (!flock($lockHandle, $lockType)) {
            fclose($lockHandle);
            throw new Exception('Failed to acquire lock on file: ' . $path);
        }

        return $lockHandle;
    }

    /**
     * Release lock and close file handle
     *
     * @param resource $lockHandle Lock file handle
     * @param string|null $path Optional file path for lock cleanup
     * @param bool $cleanup Whether to delete lock file after release
     */
    protected static function releaseLock($lockHandle, $path = null, $cleanup = false)
    {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        if ($cleanup && $path !== null) {
            @unlink($path . static::LOCK_EXTENSION);
        }
    }

    /**
     * Generate random suffix for temporary files
     *
     * @return string Random suffix
     */
    protected static function randomSuffix()
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

    /**
     * Validate file size against maximum limit
     *
     * @param int $size File size in bytes
     * @throws Exception If size exceeds maximum
     */
    protected static function validateFileSize($size)
    {
        if ($size > static::MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum limit (' . static::MAX_FILE_SIZE . ' bytes)');
        }
    }
}
