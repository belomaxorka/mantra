<?php declare(strict_types=1);
/**
 * StorageDriverInterface - Interface for content storage drivers
 *
 * Defines the contract for storage implementations (JSON, Markdown, etc.)
 */

namespace Storage;

interface StorageDriverInterface
{

    /**
     * Read a single document from storage
     *
     * @param string $collection Collection name (e.g., 'pages', 'posts')
     * @param string $id Document ID
     * @return array|null Document data or null if not found
     */
    public function read($collection, $id);

    /**
     * Write a document to storage
     *
     * @param string $collection Collection name
     * @param string $id Document ID
     * @param array $data Document data
     * @return bool Success status
     */
    public function write($collection, $id, $data);

    /**
     * Delete a document from storage
     *
     * @param string $collection Collection name
     * @param string $id Document ID
     * @return bool Success status
     */
    public function delete($collection, $id);

    /**
     * Check if a document exists
     *
     * @param string $collection Collection name
     * @param string $id Document ID
     * @return bool True if exists
     */
    public function exists($collection, $id);

    /**
     * Read all documents in a collection
     *
     * @param string $collection Collection name
     * @return array Array of documents keyed by ID
     */
    public function readCollection($collection);

    /**
     * Count files in a collection without reading their contents
     *
     * @param string $collection Collection name
     * @return int Number of documents
     */
    public function countFiles($collection);

    /**
     * List document IDs in a collection without reading contents
     *
     * @param string $collection Collection name
     * @return array Array of document IDs
     */
    public function listIds($collection);

    /**
     * Get the file extension used by this driver
     *
     * @return string File extension (e.g., 'json', 'md')
     */
    public function getExtension();
}
