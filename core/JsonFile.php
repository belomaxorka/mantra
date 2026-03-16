<?php

/**
 * JsonFile - Safe JSON file read/write helper.
 *
 * Provides:
 * - shared/exclusive locking via a dedicated .lock file
 * - atomic writes (tmp + rename)
 * - rotating backups (file.json.bak.1, .bak.2, ...)
 * - optional recovery from backups when JSON is corrupted
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
    const DEFAULT_BACKUP_COUNT = 5;

    /**
     * Read and decode a JSON file.
     *
     * If the JSON is corrupted, this will try to recover from backups and will
     * log a warning (when logger() is available).
     *
     * @return array
     * @throws JsonFileException
     */
    public static function read($path, $options = array())
    {
        $backupCount = isset($options['backupCount']) ? (int)$options['backupCount'] : self::DEFAULT_BACKUP_COUNT;
        $recover = array_key_exists('recover', $options) ? (bool)$options['recover'] : true;

        if (!file_exists($path)) {
            throw new JsonFileException('JSON file not found', $path);
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
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return $data;
        } catch (JsonFileException $e) {
            // Try recovery from backups.
            if (!$recover) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                throw $e;
            }

            $recovered = self::tryRecoverFromBackups($path, $backupCount);
            if ($recovered !== null) {
                self::logWarning('Corrupted JSON recovered from backup', array(
                    'path' => $path,
                    'recovered_from' => $recovered['from']
                ));

                // Write recovered content back as the main file.
                // Use the existing shared lock handle: upgrade is not supported,
                // so release and re-acquire exclusive lock.
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);

                self::write($path, $recovered['data'], array(
                    'backupCount' => $backupCount,
                    'skipBackup' => true // backup already contains last-known-good
                ));

                return $recovered['data'];
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            throw $e;
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
        $backupCount = isset($options['backupCount']) ? (int)$options['backupCount'] : self::DEFAULT_BACKUP_COUNT;
        $skipBackup = !empty($options['skipBackup']);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new JsonFileException('Failed to create directory for JSON file', $path);
            }
        }

        $lockHandle = self::openLock($path);
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new JsonFileException('Failed to acquire exclusive lock for JSON file', $path);
        }

        try {
            // Backup current file before overwriting.
            if (!$skipBackup && file_exists($path)) {
                self::rotateBackups($path, $backupCount);
                $bak1 = self::backupPath($path, 1);
                if (!@copy($path, $bak1)) {
                    // Backup failure shouldn't silently pass.
                    throw new JsonFileException('Failed to create JSON backup', $path);
                }
            }

            $json = self::encode($data, $path);

            $tmp = $path . '.tmp.' . self::randomSuffix();
            $bytes = file_put_contents($tmp, $json);
            if ($bytes === false) {
                throw new JsonFileException('Failed to write temp JSON file', $tmp);
            }

            // Replace destination as safely as possible across platforms.
            // - On POSIX, rename() over an existing file is atomic.
            // - On Windows, rename() fails if the destination exists.
            if (@rename($tmp, $path)) {
                // ok
            } else {
                $old = null;
                if (file_exists($path)) {
                    $old = $path . '.old.' . self::randomSuffix();
                    if (!@rename($path, $old)) {
                        @unlink($tmp);
                        throw new JsonFileException('Failed to rotate existing JSON file before replace', $path);
                    }
                }

                if (!@rename($tmp, $path)) {
                    // Restore old file if we moved it.
                    if ($old && file_exists($old)) {
                        @rename($old, $path);
                    }
                    @unlink($tmp);
                    throw new JsonFileException('Failed to replace JSON file', $path);
                }

                if ($old && file_exists($old)) {
                    @unlink($old);
                }
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return true;
        } catch (Exception $e) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            if ($e instanceof JsonFileException) {
                throw $e;
            }
            throw new JsonFileException($e->getMessage(), $path, 0, $e);
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

    private static function backupPath($path, $index)
    {
        return $path . '.bak.' . (int)$index;
    }

    private static function rotateBackups($path, $backupCount)
    {
        if ($backupCount <= 0) {
            return;
        }

        for ($i = $backupCount; $i >= 2; $i--) {
            $from = self::backupPath($path, $i - 1);
            $to = self::backupPath($path, $i);
            if (file_exists($from)) {
                // Best-effort rotation.
                @rename($from, $to);
            }
        }

        // .bak.1 is created by copy() during write.
    }

    private static function tryRecoverFromBackups($path, $backupCount)
    {
        if ($backupCount <= 0) {
            return null;
        }

        for ($i = 1; $i <= $backupCount; $i++) {
            $bak = self::backupPath($path, $i);
            if (!file_exists($bak)) {
                continue;
            }

            $raw = file_get_contents($bak);
            if ($raw === false) {
                continue;
            }

            try {
                $data = self::decode($raw, $bak);
                return array('from' => $bak, 'data' => $data);
            } catch (JsonFileException $e) {
                // try next
            }
        }

        return null;
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
