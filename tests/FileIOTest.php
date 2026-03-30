<?php
/**
 * FileIO Tests
 * Tests for atomic file operations: locking, atomic writes, size validation
 */

require_once __DIR__ . '/../core/bootstrap.php';

use Storage\FileIO;
use Storage\FileIOException;

class FileIOTest {
    private $testDir;
    private $results = array();

    public function __construct() {
        $this->testDir = MANTRA_STORAGE . '/test-fileio-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    public function __destruct() {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function run() {
        echo "Running FileIO Tests...\n\n";

        $this->testWriteAndRead();
        $this->testReadNonExistent();
        $this->testWriteCreatesDirectory();
        $this->testAtomicWrite();
        $this->testDeleteLocked();
        $this->testDeleteCleansLockFile();
        $this->testLockFileCreation();
        $this->testValidateFileSizeOnWrite();
        $this->testValidateFileSizeOnRead();
        $this->testEmptyContent();
        $this->testBinaryContent();
        $this->testUnicodeContent();
        $this->testLargeContent();
        $this->testRepeatedReads();
        $this->testJsonCodecIntegration();
        $this->testCleanOrphanedLocks();
        $this->testCleanOrphanedLocksPreservesFresh();

        $this->printResults();
    }

    private function assert($condition, $message) {
        if ($condition) {
            $this->results[] = array('status' => 'PASS', 'message' => $message);
            echo "  PASS: $message\n";
        } else {
            $this->results[] = array('status' => 'FAIL', 'message' => $message);
            echo "  FAIL: $message\n";
        }
    }

    private function testWriteAndRead() {
        $path = $this->testDir . '/test.txt';
        $content = 'Hello, World!';

        $result = FileIO::writeAtomic($path, $content);
        $this->assert($result === true, 'writeAtomic() returns true on success');

        $read = FileIO::readLocked($path);
        $this->assert($read === $content, 'readLocked() returns correct content');
    }

    private function testReadNonExistent() {
        $path = $this->testDir . '/non-existent.txt';

        try {
            FileIO::readLocked($path);
            $this->assert(false, 'readLocked() throws exception for non-existent file');
        } catch (FileIOException $e) {
            $this->assert(true, 'readLocked() throws FileIOException for non-existent file');
            $this->assert($e->getPath() === $path, 'Exception contains file path');
        }
    }

    private function testWriteCreatesDirectory() {
        $path = $this->testDir . '/subdir/nested/file.txt';
        $content = 'nested content';

        $result = FileIO::writeAtomic($path, $content);
        $this->assert($result === true, 'writeAtomic() creates nested directories');
        $this->assert(file_exists($path), 'File exists after write with nested path');
    }

    private function testAtomicWrite() {
        $path = $this->testDir . '/atomic.txt';

        FileIO::writeAtomic($path, 'version 1');
        FileIO::writeAtomic($path, 'version 2');

        $read = FileIO::readLocked($path);
        $this->assert($read === 'version 2', 'Atomic write replaces file correctly');

        // Check no temp files left behind
        $tempFiles = glob($this->testDir . '/*.tmp.*');
        $this->assert(count($tempFiles) === 0, 'No temporary files left after atomic write');
    }

    private function testDeleteLocked() {
        $path = $this->testDir . '/to-delete.txt';
        FileIO::writeAtomic($path, 'delete me');
        $this->assert(file_exists($path), 'File exists before delete');

        $result = FileIO::deleteLocked($path);
        $this->assert($result === true, 'deleteLocked() returns true on success');
        $this->assert(!file_exists($path), 'File removed after delete');

        // Delete non-existent file
        $result = FileIO::deleteLocked($this->testDir . '/nope.txt');
        $this->assert($result === false, 'deleteLocked() returns false for non-existent file');
    }

    private function testDeleteCleansLockFile() {
        $path = $this->testDir . '/delete-lock-cleanup.txt';
        FileIO::writeAtomic($path, 'data');
        $lockPath = $path . '.lock';
        $this->assert(file_exists($lockPath), 'Lock file exists before delete');

        FileIO::deleteLocked($path);
        $this->assert(!file_exists($path), 'Data file removed');
        $this->assert(!file_exists($lockPath), 'Lock file cleaned up after delete');
    }

    private function testLockFileCreation() {
        $path = $this->testDir . '/locked.txt';
        FileIO::writeAtomic($path, 'locked content');

        $lockPath = $path . '.lock';
        $this->assert(file_exists($lockPath), 'Lock file exists after write (for reuse)');

        // Can still read/write with existing lock file
        $read = FileIO::readLocked($path);
        $this->assert($read === 'locked content', 'Can read file with existing lock file');

        FileIO::writeAtomic($path, 'updated');
        $read = FileIO::readLocked($path);
        $this->assert($read === 'updated', 'Can write file with existing lock file');
    }

    private function testValidateFileSizeOnWrite() {
        $path = $this->testDir . '/oversized-write.txt';
        // Content exceeding 10MB limit
        $oversized = str_repeat('X', FileIO::MAX_FILE_SIZE + 1);

        try {
            FileIO::writeAtomic($path, $oversized);
            $this->assert(false, 'writeAtomic() should throw on oversized content');
        } catch (FileIOException $e) {
            $this->assert(
                strpos($e->getMessage(), 'exceeds maximum') !== false,
                'writeAtomic() throws FileIOException for oversized content'
            );
        }
        $this->assert(!file_exists($path), 'Oversized file not created on disk');
    }

    private function testValidateFileSizeOnRead() {
        $path = $this->testDir . '/oversized-read.txt';
        // Create file bypassing FileIO to exceed the limit
        $oversized = str_repeat('X', FileIO::MAX_FILE_SIZE + 1);
        file_put_contents($path, $oversized);

        try {
            FileIO::readLocked($path);
            $this->assert(false, 'readLocked() should throw on oversized file');
        } catch (FileIOException $e) {
            $this->assert(
                strpos($e->getMessage(), 'exceeds maximum') !== false,
                'readLocked() throws FileIOException for oversized file'
            );
        }

        // Cleanup large file immediately to free disk space
        @unlink($path);
    }

    private function testEmptyContent() {
        $path = $this->testDir . '/empty.txt';
        FileIO::writeAtomic($path, '');
        $read = FileIO::readLocked($path);
        $this->assert($read === '', 'Empty content written and read correctly');
    }

    private function testBinaryContent() {
        $path = $this->testDir . '/binary.bin';
        // Content with null bytes and arbitrary binary data
        $content = "before\x00middle\x00after\xFF\xFE\x01\x02";

        FileIO::writeAtomic($path, $content);
        $read = FileIO::readLocked($path);
        $this->assert($read === $content, 'Binary content with null bytes preserved');
        $this->assert(strlen($read) === strlen($content), 'Binary content length preserved');
    }

    private function testUnicodeContent() {
        $path = $this->testDir . '/unicode.txt';
        $content = "Russian: \xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82\nChinese: \xE4\xBD\xA0\xE5\xA5\xBD";

        FileIO::writeAtomic($path, $content);
        $read = FileIO::readLocked($path);
        $this->assert($read === $content, 'Unicode content preserved correctly');
    }

    private function testLargeContent() {
        $path = $this->testDir . '/large.txt';
        $content = str_repeat('Lorem ipsum dolor sit amet. ', 10000);

        $result = FileIO::writeAtomic($path, $content);
        $this->assert($result === true, 'Large content written successfully');

        $read = FileIO::readLocked($path);
        $this->assert($read === $content, 'Large content read correctly');
    }

    private function testRepeatedReads() {
        $path = $this->testDir . '/repeated.txt';
        FileIO::writeAtomic($path, 'shared value');

        $allCorrect = true;
        for ($i = 0; $i < 5; $i++) {
            if (FileIO::readLocked($path) !== 'shared value') {
                $allCorrect = false;
                break;
            }
        }
        $this->assert($allCorrect, 'Repeated reads return consistent data');
    }

    private function testJsonCodecIntegration() {
        $path = $this->testDir . '/integration.json';
        $data = array(
            'key' => 'value',
            'number' => 42,
            'nested' => array('deep' => true),
            'russian' => "\xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82",
        );

        // Write: JsonCodec::encode + FileIO::writeAtomic
        $json = JsonCodec::encode($data);
        FileIO::writeAtomic($path, $json);

        // Read: FileIO::readLocked + JsonCodec::decode
        $raw = FileIO::readLocked($path);
        $decoded = JsonCodec::decode($raw);

        $this->assert($decoded['key'] === 'value', 'JsonCodec + FileIO: string preserved');
        $this->assert($decoded['number'] === 42, 'JsonCodec + FileIO: number preserved');
        $this->assert($decoded['nested']['deep'] === true, 'JsonCodec + FileIO: nested data preserved');
        $this->assert($decoded['russian'] === "\xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82", 'JsonCodec + FileIO: unicode preserved');
    }

    private function testCleanOrphanedLocks() {
        $lockDir = $this->testDir . '/locks-orphaned';
        mkdir($lockDir, 0755, true);

        // Create a fake orphaned lock file with old timestamp
        $lockFile = $lockDir . '/orphan.json.lock';
        file_put_contents($lockFile, '');
        touch($lockFile, time() - 7200); // 2 hours old

        $cleaned = FileIO::cleanOrphanedLocks($lockDir, 3600);
        $this->assert($cleaned === 1, 'cleanOrphanedLocks() cleaned 1 orphaned lock');
        $this->assert(!file_exists($lockFile), 'Orphaned lock file removed');
    }

    private function testCleanOrphanedLocksPreservesFresh() {
        $lockDir = $this->testDir . '/locks-fresh';
        mkdir($lockDir, 0755, true);

        // Create a fresh lock file (just created)
        $freshLock = $lockDir . '/fresh.json.lock';
        file_put_contents($freshLock, '');
        // Touch is not needed - default mtime is now

        $cleaned = FileIO::cleanOrphanedLocks($lockDir, 3600);
        $this->assert($cleaned === 0, 'cleanOrphanedLocks() does not clean fresh locks');
        $this->assert(file_exists($freshLock), 'Fresh lock file preserved');
    }

    private function printResults() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Test Results Summary\n";
        echo str_repeat('=', 50) . "\n";

        $passed = 0;
        $failed = 0;

        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }

        $total = $passed + $failed;
        echo "Total: $total | Passed: $passed | Failed: $failed\n";

        if ($failed === 0) {
            echo "\nAll tests passed!\n";
        } else {
            echo "\nSome tests failed.\n";
        }
    }
}

// Run tests
$test = new FileIOTest();
$test->run();
