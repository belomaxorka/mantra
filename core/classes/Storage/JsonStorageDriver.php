<?php
/**
 * JsonStorageDriver - JSON file storage implementation
 *
 * Stores content as JSON files (original Database behavior)
 */

class JsonStorageDriver implements StorageDriverInterface
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

        try {
            $data = JsonFile::read($path);
        } catch (JsonFileException $e) {
            logger()->error('Failed to read JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        }

        return $data;
    }

    public function write($collection, $id, $data)
    {
        $path = $this->getPath($collection, $id);

        try {
            $result = JsonFile::write($path, $data);
        } catch (JsonFileException $e) {
            logger()->error('Failed to write JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        }

        if ($result) {
            logger()->debug('Data written', array('collection' => $collection, 'id' => $id));
        }

        return $result;
    }

    public function delete($collection, $id)
    {
        $path = $this->getPath($collection, $id);

        if (!file_exists($path)) {
            return false;
        }

        // Use locking to prevent deletion during read
        $lockPath = $path . '.lock';
        $lockHandle = @fopen($lockPath, 'c');
        if ($lockHandle === false) {
            return false;
        }

        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            return false;
        }

        try {
            $result = @unlink($path);

            // Clean up lock file
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);

            return $result;
        } catch (Exception $e) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
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
        $files = glob($collectionPath . '/*.json');

        foreach ($files as $file) {
            $id = basename($file, '.json');

            try {
                $data = JsonFile::read($file);
            } catch (JsonFileException $e) {
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
        return 'json';
    }

    private function getPath($collection, $id)
    {
        return $this->basePath . '/' . $collection . '/' . $id . '.json';
    }
}
