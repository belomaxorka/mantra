<?php declare(strict_types=1);

namespace Storage;

use InvalidArgumentException;

/** Append-only document snapshots used for recovery and rollback. */
final class RevisionStore
{
    private $root;
    private $limit;
    private $lastRevisionTick = 0;

    public function __construct($root = null, $limit = null)
    {
        $this->root = $root ?? (MANTRA_STORAGE . '/revisions');
        $this->limit = $limit === null
            ? max(0, (int)\config('content.revision_limit', 20))
            : max(0, (int)$limit);
    }

    public function capture($collection, $id, $data, $reason = 'update')
    {
        $this->assertName($collection, 'collection');
        $this->assertName($id, 'document ID');
        if ($this->limit === 0) {
            return null;
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Revision data must be an array');
        }

        unset($data['_id']);
        $tick = (int)floor(microtime(true) * 1000000);
        if ($tick <= $this->lastRevisionTick) {
            $tick = $this->lastRevisionTick + 1;
        }
        $this->lastRevisionTick = $tick;
        $revisionId = sprintf('%016d', $tick) . '-' . bin2hex(random_bytes(6));
        $path = $this->directory($collection, $id) . '/' . $revisionId . '.json';
        FileIO::writeAtomic($path, \JsonCodec::encode([
            'revision_id' => $revisionId,
            'collection' => $collection,
            'document_id' => $id,
            'reason' => (string)$reason,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'data' => $data,
        ]));

        $this->prune($collection, $id);
        return $revisionId;
    }

    public function all($collection, $id)
    {
        $files = $this->files($collection, $id);
        rsort($files, SORT_STRING);
        $revisions = [];
        foreach ($files as $file) {
            try {
                $revision = \JsonCodec::decode(FileIO::readLocked($file));
                if (is_array($revision)) {
                    $revisions[] = $revision;
                }
            } catch (\Throwable $e) {
                if (function_exists('logger')) {
                    \logger()->warning('Skipping unreadable document revision', [
                        'path' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        return $revisions;
    }

    public function read($collection, $id, $revisionId)
    {
        $this->assertName($revisionId, 'revision ID');
        $path = $this->directory($collection, $id) . '/' . $revisionId . '.json';
        if (!is_file($path)) {
            return null;
        }
        $revision = \JsonCodec::decode(FileIO::readLocked($path));
        return is_array($revision) ? $revision : null;
    }

    private function prune($collection, $id): void
    {
        $files = $this->files($collection, $id);
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $this->limit) as $file) {
            FileIO::deleteLocked($file);
        }
    }

    private function files($collection, $id)
    {
        $this->assertName($collection, 'collection');
        $this->assertName($id, 'document ID');
        $directory = $this->directory($collection, $id);
        if (!is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/*.json');
        return is_array($files) ? $files : [];
    }

    private function directory($collection, $id)
    {
        return $this->root . '/' . $collection . '/' . $id;
    }

    private function assertName($value, $label): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', (string)$value) !== 1) {
            throw new InvalidArgumentException("Invalid {$label}");
        }
    }
}
