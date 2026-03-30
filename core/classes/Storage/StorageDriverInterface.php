<?php
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
     * @return array Array of documents
     */
    public function readCollection($collection);

    /**
     * Get the file extension used by this driver
     *
     * @return string File extension (e.g., 'json', 'md')
     */
    public function getExtension();
}
