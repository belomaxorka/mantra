<?php declare(strict_types=1);
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

class JsonStorageDriver extends AbstractFileStorageDriver
{
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
            logger()->error('Failed to read JSON document', [
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function write($collection, $id, $data)
    {
        $path = $this->getPath($collection, $id);

        try {
            $compact = !empty($GLOBALS['MANTRA_CONFIG']['content']['compact_json']);
            $content = JsonCodec::encode($data, $compact);
        } catch (JsonCodecException $e) {
            logger()->error('Failed to encode JSON document', [
                'collection' => $collection,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        try {
            FileIO::writeAtomic($path, $content);
            logger()->debug('Data written', ['collection' => $collection, 'id' => $id]);
            return true;
        } catch (Exception $e) {
            logger()->error('Failed to write JSON document', [
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getExtension()
    {
        return '.json';
    }

}
