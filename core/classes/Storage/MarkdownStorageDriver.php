<?php
/**
 * MarkdownStorageDriver - Markdown file storage implementation
 *
 * Stores content as Markdown files with YAML frontmatter
 */

class MarkdownStorageDriver extends AbstractFileStorage implements StorageDriverInterface
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
            $content = file_get_contents($path);
            if ($content === false) {
                throw new Exception('Failed to read file');
            }

            $data = $this->parseMarkdown($content);
        } catch (Exception $e) {
            logger()->error('Failed to read Markdown document', array(
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

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new Exception('Failed to create directory');
            }
        }

        // Build content first to validate before acquiring lock
        $content = $this->buildMarkdown($data);

        // Validate size
        self::validateFileSize(strlen($content));

        // Acquire exclusive lock
        $lockHandle = self::openLock($path);

        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new Exception('Failed to acquire exclusive lock');
        }

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

            logger()->debug('Markdown data written', array('collection' => $collection, 'id' => $id));
            return true;

        } catch (Exception $e) {
            logger()->error('Failed to write Markdown document', array(
                'collection' => $collection,
                'id' => $id,
                'path' => $path,
                'error' => $e->getMessage()
            ));
            throw $e;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
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
            $lockHandle = self::openLock($path);
        } catch (Exception $e) {
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
            @unlink($path . '.lock');

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
        $files = glob($collectionPath . '/*' . self::getExtension());

        foreach ($files as $file) {
            $id = basename($file, self::getExtension());

            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    throw new Exception('Failed to read file');
                }

                $data = $this->parseMarkdown($content);
            } catch (Exception $e) {
                logger()->error('Failed to read Markdown document in collection', array(
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
        return '.md';
    }

    private function getPath($collection, $id)
    {
        return $this->basePath . '/' . $collection . '/' . $id . self::getExtension();
    }

    /**
     * Parse Markdown file with YAML frontmatter
     *
     * @param string $content File content
     * @return array Parsed data
     */
    private function parseMarkdown($content)
    {
        $data = array();

        // Check for YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontmatter = $matches[1];
            $markdown = $matches[2];

            // Parse YAML frontmatter
            $data = $this->parseYaml($frontmatter);

            // Store markdown content (convert to HTML for editor compatibility)
            $data['content'] = MarkdownConverter::toHtml(trim($markdown));
            $data['_markdown'] = trim($markdown); // Keep original markdown
        } else {
            // No frontmatter, entire content is markdown
            $markdown = trim($content);
            $data['content'] = MarkdownConverter::toHtml($markdown);
            $data['_markdown'] = $markdown;
        }

        return $data;
    }

    /**
     * Build Markdown file with YAML frontmatter
     *
     * @param array $data Document data
     * @return string Markdown content
     */
    private function buildMarkdown($data)
    {
        $frontmatter = array();
        $content = '';

        // Extract content field
        if (isset($data['content'])) {
            $content = $data['content'];
            unset($data['content']);
        }

        // Remove internal markdown field
        if (isset($data['_markdown'])) {
            unset($data['_markdown']);
        }

        // Remove _id field (internal)
        if (isset($data['_id'])) {
            unset($data['_id']);
        }

        // Convert HTML to Markdown if needed
        if (!empty($content) && $this->isHtml($content)) {
            $content = MarkdownConverter::toMarkdown($content);
        }

        // Build YAML frontmatter
        $yaml = $this->buildYaml($data);

        return "---\n" . $yaml . "---\n\n" . $content;
    }

    /**
     * Simple YAML parser for frontmatter
     *
     * @param string $yaml YAML content
     * @return array Parsed data
     */
    private function parseYaml($yaml)
    {
        $data = array();
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Handle boolean values
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = (int)$value;
                } else {
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                }

                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Simple YAML builder for frontmatter
     *
     * @param array $data Data to convert
     * @return string YAML content
     */
    private function buildYaml($data)
    {
        $yaml = '';

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $value = (string)$value;
            } elseif (is_string($value)) {
                // Escape quotes and wrap in quotes if contains special chars
                if (strpos($value, ':') !== false || strpos($value, "\n") !== false) {
                    $value = '"' . str_replace('"', '\\"', $value) . '"';
                }
            } else {
                continue; // Skip arrays and objects
            }

            $yaml .= $key . ': ' . $value . "\n";
        }

        return $yaml;
    }

    /**
     * Check if content is HTML
     *
     * @param string $content Content to check
     * @return bool True if HTML
     */
    private function isHtml($content)
    {
        return preg_match('/<[^>]+>/', $content) === 1;
    }
}
