<?php

/**
 * JsonFile - Safe JSON file read/write helper.
 *
 * @deprecated This class is deprecated. Use JsonCodec for encoding/decoding
 *             and storage drivers for file operations.
 *
 * Provides backward compatibility for existing code.
 * New code should use:
 * - JsonCodec::encode() / JsonCodec::decode() for format handling
 * - JsonStorageDriver or direct file operations for storage
 *
 * This class will be removed in a future version.
 */
class JsonFileException extends Exception
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

class JsonFile extends AbstractFileStorage
{

    /**
     * Read and decode a JSON file.
     *
     * @deprecated Use JsonCodec::decode() with file_get_contents() or JsonStorageDriver
     * @return array
     * @throws JsonFileException
     */
    public static function read($path, $options = array())
    {
        if (!file_exists($path)) {
            throw new JsonFileException('JSON file not found', $path);
        }

        // Validate file size before reading
        $size = @filesize($path);
        if ($size === false) {
            throw new JsonFileException('Failed to get file size', $path);
        }
        self::validateFileSize($size);

        $lockHandle = self::acquireLock($path, LOCK_SH);

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new JsonFileException('Failed to read JSON file', $path);
            }

            // Use JsonCodec for decoding
            try {
                $data = JsonCodec::decode($raw);
            } catch (JsonCodecException $e) {
                throw new JsonFileException($e->getMessage(), $path);
            }

            return $data;
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Encode and atomically write a JSON file.
     *
     * @deprecated Use JsonCodec::encode() with atomic file operations or JsonStorageDriver
     * @return bool
     * @throws JsonFileException
     */
    public static function write($path, $data, $options = array())
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new JsonFileException('Failed to create directory for JSON file', $path);
            }
        }

        // Use JsonCodec for encoding
        try {
            $json = JsonCodec::encode($data);
        } catch (JsonCodecException $e) {
            throw new JsonFileException($e->getMessage(), $path);
        }

        // Validate size before writing
        self::validateFileSize(strlen($json));

        $lockHandle = self::acquireLock($path, LOCK_EX);

        try {
            $tmp = $path . '.tmp.' . self::randomSuffix();
            $bytes = file_put_contents($tmp, $json, LOCK_EX);
            if ($bytes === false) {
                throw new JsonFileException('Failed to write temp JSON file', $tmp);
            }

            // Atomic replace - handle Windows compatibility
            if (DIRECTORY_SEPARATOR === '\\' && file_exists($path)) {
                // Windows: delete target first
                if (!@unlink($path)) {
                    @unlink($tmp);
                    throw new JsonFileException('Failed to remove existing JSON file for replacement', $path);
                }
            }

            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new JsonFileException('Failed to replace JSON file', $path);
            }

            return true;
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    /**
     * Clean up orphaned lock files older than specified time
     *
     * @param string $directory Directory to clean
     * @param int $maxAge Maximum age in seconds (default: 1 hour)
     * @return int Number of files cleaned
     */
    public static function cleanOrphanedLocks($directory, $maxAge = 3600)
    {
        $cleaned = 0;
        $pattern = $directory . '/*' . static::LOCK_EXTENSION;

        foreach (glob($pattern) as $lockFile) {
            if (!file_exists($lockFile)) {
                continue;
            }

            $age = time() - filemtime($lockFile);
            if ($age > $maxAge) {
                // Try to acquire exclusive lock - if we can, it's orphaned
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

    protected static function openLock($path)
    {
        $lockPath = $path . static::LOCK_EXTENSION;
        $handle = @fopen($lockPath, 'c');
        if ($handle === false) {
            throw new JsonFileException('Failed to open lock file', $lockPath);
        }
        return $handle;
    }

    private static function logWarning($message, $context = array())
    {
        // Avoid hard dependency on helpers.php during early bootstrap.
        if (function_exists('logger')) {
            logger('app')->warning($message, $context);
            return;
        }

        error_log($message . ' ' . json_encode($context));
    }

    /**
     * Read JSON file with fallback on error
     *
     * @deprecated Use JsonCodec with error handling
     * @param string $path File path
     * @param mixed $default Default value if file doesn't exist or is invalid
     * @return mixed
     */
    public static function readSafe($path, $default = array())
    {
        try {
            return self::read($path);
        } catch (JsonFileException $e) {
            self::logWarning('Failed to read JSON file', array(
                'path' => $path,
                'error' => $e->getMessage()
            ));
            return $default;
        }
    }

    /**
     * Write JSON file with error handling
     *
     * @deprecated Use JsonCodec with error handling
     * @param string $path File path
     * @param mixed $data Data to write
     * @return bool Success status
     */
    public static function writeSafe($path, $data)
    {
        try {
            self::write($path, $data);
            return true;
        } catch (JsonFileException $e) {
            self::logWarning('Failed to write JSON file', array(
                'path' => $path,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}
