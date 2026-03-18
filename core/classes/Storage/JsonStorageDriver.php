<?php
/**
 * JsonStorageDriver - JSON file storage implementation
 *
 * Stores content as JSON files with atomic writes and file locking.
 * Uses JsonCodec for format handling and AbstractFileStorage for file operations.
 */

class JsonStorageDriver extends AbstractFileStorage implements StorageDriverInterface
{

    private $basePath;

    public function __construct($basePath = null)
    {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
    }

    public function read($collection, $id)
    {
        $path = $this->getPath($collection, $id);

        if (!file_exists($path)) {
            return null;
        }

        // Validate file size before reading
        $size = @filesize($path);
        if ($size === false) {
            throw new Exception('Failed to get file size');
        }
        self::validateFileSize($size);

        // Acquire shared lock for reading
        $lockHandle = self::acquireLock($path, LOCK_SH);

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new Exception('Failed to read file');
            }

            $data = JsonCodec::decode($raw);
            return $data;

        } catch (Exception $e) {
            logger()->error('Failed to read JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    public function write($collection, $id, $data)
    {
        $path = $this->getPath($collection, $id);

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new Exception('Failed to create directory');
            }
        }

        // Encode content first to validate before acquiring lock
        try {
            $content = JsonCodec::encode($data);
        } catch (JsonCodecException $e) {
            logger()->error('Failed to encode JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage()
            ));
            throw $e;
        }

        // Validate size
        self::validateFileSize(strlen($content));

        // Acquire exclusive lock
        $lockHandle = self::acquireLock($path, LOCK_EX);

        try {
            // Atomic write with temp file
            $tmp = $path . '.tmp.' . self::randomSuffix();
            if (file_put_contents($tmp, $content) === false) {
                throw new Exception('Failed to write temp file');
            }

            // Handle Windows compatibility
            if (DIRECTORY_SEPARATOR === '\\' && file_exists($path)) {
                if (!@unlink($path)) {
                    @unlink($tmp);
                    throw new Exception('Failed to remove existing file for replacement');
                }
            }

            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new Exception('Failed to rename temp file');
            }

            logger()->debug('Data written', array('collection' => $collection, 'id' => $id));
            return true;

        } catch (Exception $e) {
            logger()->error('Failed to write JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        } finally {
            self::releaseLock($lockHandle);
        }
    }

    public function delete($collection, $id)
    {
        $path = $this->getPath($collection, $id);

        if (!file_exists($path)) {
            return false;
        }

        // Use locking to prevent deletion during read
        try {
            $lockHandle = self::acquireLock($path, LOCK_EX);
        } catch (Exception $e) {
            return false;
        }

        try {
            $result = @unlink($path);

            // Clean up lock file and release
            self::releaseLock($lockHandle, $path, true);

            return $result;
        } catch (Exception $e) {
            self::releaseLock($lockHandle);
            return false;
        }
    }

    public function exists($collection, $id)
    {
        $path = $this->getPath($collection, $id);
        return file_exists($path);
    }

    public function readCollection($collection)
    {
        $collectionPath = $this->basePath . '/' . $collection;

        if (!is_dir($collectionPath)) {
            return array();
        }

        $items = array();
        $files = glob($collectionPath . '/*' . self::getExtension());

        foreach ($files as $file) {
            $id = basename($file, self::getExtension());

            try {
                $raw = file_get_contents($file);
                if ($raw === false) {
                    throw new Exception('Failed to read file');
                }

                $data = JsonCodec::decode($raw);
            } catch (Exception $e) {
                logger()->error('Failed to read JSON document in collection', array(
                    'collection' => $collection,
                    'id' => $id,
                    'path' => $file,
                    'error' => $e->getMessage()
                ));
                continue;
            }

            $items[$id] = $data;
        }

        return $items;
    }

    public function getExtension()
    {
        return '.json';
    }

    private function getPath($collection, $id)
    {
        return $this->basePath . '/' . $collection . '/' . $id . self::getExtension();
    }
}
