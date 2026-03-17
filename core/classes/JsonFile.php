<?php

/**
 * JsonFile - Safe JSON file read/write helper.
 *
 * Provides:
 * - shared/exclusive locking via a dedicated .lock file
 * - atomic writes (tmp + rename)
 * - file size validation
 * - improved error handling
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

class JsonFile
{
    const MAX_FILE_SIZE = 10485760; // 10MB

    /**
     * Read and decode a JSON file.
     *
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
        if ($size > self::MAX_FILE_SIZE) {
            throw new JsonFileException('JSON file exceeds maximum size (' . self::MAX_FILE_SIZE . ' bytes)', $path);
        }

        $lockHandle = self::openLock($path);
        if (!flock($lockHandle, LOCK_SH)) {
            fclose($lockHandle);
            throw new JsonFileException('Failed to acquire shared lock for JSON file', $path);
        }

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new JsonFileException('Failed to read JSON file', $path);
            }

            $data = self::decode($raw, $path);
            return $data;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Encode and atomically write a JSON file.
     *
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

        // Encode first to validate before acquiring lock
        $json = self::encode($data, $path);
        
        // Validate size before writing
        if (strlen($json) > self::MAX_FILE_SIZE) {
            throw new JsonFileException('JSON data exceeds maximum size (' . self::MAX_FILE_SIZE . ' bytes)', $path);
        }

        $lockHandle = self::openLock($path);
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new JsonFileException('Failed to acquire exclusive lock for JSON file', $path);
        }

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
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private static function decode($raw, $path)
    {
        // json_decode() exceptions require PHP 7.3+ (JSON_THROW_ON_ERROR).
        // The project supports PHP 5.5+, so use json_last_error().
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $err = json_last_error();
            if ($err !== JSON_ERROR_NONE) {
                throw new JsonFileException('Invalid JSON: ' . json_last_error_msg(), $path);
            }
            // Valid JSON but not an object/array.
            throw new JsonFileException('JSON root must be an object', $path);
        }
        return $data;
    }

    private static function encode($data, $path)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new JsonFileException('Failed to encode JSON: ' . json_last_error_msg(), $path);
        }
        return $json;
    }

    private static function openLock($path)
    {
        $lockPath = $path . '.lock';
        $h = @fopen($lockPath, 'c');
        if ($h === false) {
            throw new JsonFileException('Failed to open lock file', $lockPath);
        }
        return $h;
    }

    private static function randomSuffix()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(8));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes(8);
            if ($b !== false) {
                return bin2hex($b);
            }
        }

        return str_replace('.', '', uniqid('', true));
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
        $pattern = $directory . '/*.lock';
        
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
