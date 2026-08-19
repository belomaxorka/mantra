<?php declare(strict_types=1);

namespace Storage;

use Exception;
use InvalidArgumentException;

/** Shared path, collection traversal, and locking behavior for file drivers. */
abstract class AbstractFileStorageDriver implements StorageDriverInterface
{
    protected $basePath;

    public function __construct($basePath = null)
    {
        $this->basePath = $basePath ? $basePath : MANTRA_CONTENT;
    }

    public function delete($collection, $id)
    {
        return FileIO::deleteLocked($this->getPath($collection, $id));
    }

    public function exists($collection, $id)
    {
        return file_exists($this->getPath($collection, $id));
    }

    public function readCollection($collection)
    {
        $items = [];
        foreach ($this->listIds($collection) as $id) {
            try {
                $data = $this->read($collection, $id);
                if (is_array($data)) {
                    $items[$id] = $data;
                }
            } catch (Exception $e) {
                logger()->error('Failed to read document in collection', [
                    'driver' => static::class,
                    'collection' => $collection,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $items;
    }

    public function countFiles($collection)
    {
        return count($this->documentFiles($collection));
    }

    public function listIds($collection)
    {
        $extension = $this->getExtension();
        return array_map(
            fn($file) => basename($file, $extension),
            $this->documentFiles($collection),
        );
    }

    public function pathFor($collection, $id)
    {
        return $this->getPath($collection, $id);
    }

    protected function getPath($collection, $id)
    {
        $this->assertName($collection, 'collection');
        $this->assertName($id, 'document ID');
        return $this->basePath . '/' . $collection . '/' . $id . $this->getExtension();
    }

    private function documentFiles($collection)
    {
        $this->assertName($collection, 'collection');
        $path = $this->basePath . '/' . $collection;
        if (!is_dir($path)) {
            return [];
        }
        $files = glob($path . '/*' . $this->getExtension());
        return is_array($files) ? $files : [];
    }

    private function assertName($value, $label): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', (string)$value) !== 1) {
            throw new InvalidArgumentException("Invalid {$label}");
        }
    }
}
