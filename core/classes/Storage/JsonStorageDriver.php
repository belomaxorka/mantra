<?php
/**
 * JsonStorageDriver - JSON file storage implementation
 *
 * Stores content as JSON files with atomic writes and file locking.
 * Uses JsonCodec for format handling and FileIO for file operations.
 */

namespace Storage;

use JsonCodec;
use JsonCodecException;
use Exception;

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
            $raw = FileIO::readLocked($path);
            return JsonCodec::decode($raw);
        } catch (Exception $e) {
            logger()->error('Failed to read JSON document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    public function write($collection, $id, $data)
    {
        $path = $this->getPath($collection, $id);

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

        try {
            FileIO::writeAtomic($path, $content);
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
        }
    }

    public function delete($collection, $id)
    {
        $path = $this->getPath($collection, $id);
        return FileIO::deleteLocked($path);
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
        $files = glob($collectionPath . '/*' . $this->getExtension());

        foreach ($files as $file) {
            $id = basename($file, $this->getExtension());

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

    public function countFiles($collection)
    {
        $collectionPath = $this->basePath . '/' . $collection;

        if (!is_dir($collectionPath)) {
            return 0;
        }

        return count(glob($collectionPath . '/*' . $this->getExtension()));
    }

    public function listIds($collection)
    {
        $collectionPath = $this->basePath . '/' . $collection;

        if (!is_dir($collectionPath)) {
            return array();
        }

        $ext = $this->getExtension();
        $files = glob($collectionPath . '/*' . $ext);
        $ids = array();

        foreach ($files as $file) {
            $ids[] = basename($file, $ext);
        }

        return $ids;
    }

    public function getExtension()
    {
        return '.json';
    }

    private function getPath($collection, $id)
    {
        return $this->basePath . '/' . $collection . '/' . $id . $this->getExtension();
    }
}
