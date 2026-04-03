<?php declare(strict_types=1);

/**
 * FileIO Tests (PHPUnit)
 * Tests for atomic file operations: locking, atomic writes, size validation
 */

use Storage\FileIO;
use Storage\FileIOException;

class FileIOTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-fileio-' . time();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0o755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testWriteAndRead(): void
    {
        $path = $this->testDir . '/test.txt';
        $content = 'Hello, World!';

        $result = FileIO::writeAtomic($path, $content);
        $this->assertTrue($result, 'writeAtomic() returns true on success');

        $read = FileIO::readLocked($path);
        $this->assertSame($content, $read, 'readLocked() returns correct content');
    }

    public function testReadNonExistent(): void
    {
        $path = $this->testDir . '/non-existent.txt';

        try {
            FileIO::readLocked($path);
            $this->fail('Expected FileIOException');
        } catch (FileIOException $e) {
            $this->assertSame($path, $e->getPath(), 'Exception contains file path');
        }
    }

    public function testWriteCreatesDirectory(): void
    {
        $path = $this->testDir . '/subdir/nested/file.txt';
        $content = 'nested content';

        $result = FileIO::writeAtomic($path, $content);
        $this->assertTrue($result, 'writeAtomic() creates nested directories');
        $this->assertFileExists($path, 'File exists after write with nested path');
    }

    public function testAtomicWrite(): void
    {
        $path = $this->testDir . '/atomic.txt';

        FileIO::writeAtomic($path, 'version 1');
        FileIO::writeAtomic($path, 'version 2');

        $read = FileIO::readLocked($path);
        $this->assertSame('version 2', $read, 'Atomic write replaces file correctly');

        // Check no temp files left behind
        $tempFiles = glob($this->testDir . '/*.tmp.*');
        $this->assertCount(0, $tempFiles, 'No temporary files left after atomic write');
    }

    public function testDeleteLocked(): void
    {
        $path = $this->testDir . '/to-delete.txt';
        FileIO::writeAtomic($path, 'delete me');
        $this->assertFileExists($path, 'File exists before delete');

        $result = FileIO::deleteLocked($path);
        $this->assertTrue($result, 'deleteLocked() returns true on success');
        $this->assertFileDoesNotExist($path, 'File removed after delete');

        // Delete non-existent file
        $result = FileIO::deleteLocked($this->testDir . '/nope.txt');
        $this->assertFalse($result, 'deleteLocked() returns false for non-existent file');
    }

    public function testDeleteCleansLockFile(): void
    {
        $path = $this->testDir . '/delete-lock-cleanup.txt';
        FileIO::writeAtomic($path, 'data');
        $lockPath = $path . '.lock';
        $this->assertFileExists($lockPath, 'Lock file exists before delete');

        FileIO::deleteLocked($path);
        $this->assertFileDoesNotExist($path, 'Data file removed');
        $this->assertFileDoesNotExist($lockPath, 'Lock file cleaned up after delete');
    }

    public function testLockFileCreation(): void
    {
        $path = $this->testDir . '/locked.txt';
        FileIO::writeAtomic($path, 'locked content');

        $lockPath = $path . '.lock';
        $this->assertFileExists($lockPath, 'Lock file exists after write (for reuse)');

        // Can still read/write with existing lock file
        $read = FileIO::readLocked($path);
        $this->assertSame('locked content', $read, 'Can read file with existing lock file');

        FileIO::writeAtomic($path, 'updated');
        $read = FileIO::readLocked($path);
        $this->assertSame('updated', $read, 'Can write file with existing lock file');
    }

    public function testValidateFileSizeOnWrite(): void
    {
        $path = $this->testDir . '/oversized-write.txt';
        // Content exceeding 10MB limit
        $oversized = str_repeat('X', FileIO::MAX_FILE_SIZE + 1);

        try {
            FileIO::writeAtomic($path, $oversized);
            $this->fail('Expected FileIOException');
        } catch (FileIOException $e) {
            $this->assertStringContainsString('exceeds maximum', $e->getMessage(), 'writeAtomic() throws FileIOException for oversized content');
        }
        $this->assertFileDoesNotExist($path, 'Oversized file not created on disk');
    }

    public function testValidateFileSizeOnRead(): void
    {
        $path = $this->testDir . '/oversized-read.txt';
        // Create file bypassing FileIO to exceed the limit
        $oversized = str_repeat('X', FileIO::MAX_FILE_SIZE + 1);
        file_put_contents($path, $oversized);

        try {
            FileIO::readLocked($path);
            $this->fail('Expected FileIOException');
        } catch (FileIOException $e) {
            $this->assertStringContainsString('exceeds maximum', $e->getMessage(), 'readLocked() throws FileIOException for oversized file');
        }

        // Cleanup large file immediately to free disk space
        @unlink($path);
    }

    public function testEmptyContent(): void
    {
        $path = $this->testDir . '/empty.txt';
        FileIO::writeAtomic($path, '');
        $read = FileIO::readLocked($path);
        $this->assertSame('', $read, 'Empty content written and read correctly');
    }

    public function testBinaryContent(): void
    {
        $path = $this->testDir . '/binary.bin';
        // Content with null bytes and arbitrary binary data
        $content = "before\x00middle\x00after\xFF\xFE\x01\x02";

        FileIO::writeAtomic($path, $content);
        $read = FileIO::readLocked($path);
        $this->assertSame($content, $read, 'Binary content with null bytes preserved');
        $this->assertSame(strlen($content), strlen($read), 'Binary content length preserved');
    }

    public function testUnicodeContent(): void
    {
        $path = $this->testDir . '/unicode.txt';
        $content = "Russian: \xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82\nChinese: \xE4\xBD\xA0\xE5\xA5\xBD";

        FileIO::writeAtomic($path, $content);
        $read = FileIO::readLocked($path);
        $this->assertSame($content, $read, 'Unicode content preserved correctly');
    }

    public function testLargeContent(): void
    {
        $path = $this->testDir . '/large.txt';
        $content = str_repeat('Lorem ipsum dolor sit amet. ', 10000);

        $result = FileIO::writeAtomic($path, $content);
        $this->assertTrue($result, 'Large content written successfully');

        $read = FileIO::readLocked($path);
        $this->assertSame($content, $read, 'Large content read correctly');
    }

    public function testRepeatedReads(): void
    {
        $path = $this->testDir . '/repeated.txt';
        FileIO::writeAtomic($path, 'shared value');

        $allCorrect = true;
        for ($i = 0; $i < 5; $i++) {
            if (FileIO::readLocked($path) !== 'shared value') {
                $allCorrect = false;
                break;
            }
        }
        $this->assertTrue($allCorrect, 'Repeated reads return consistent data');
    }

    public function testJsonCodecIntegration(): void
    {
        $path = $this->testDir . '/integration.json';
        $data = [
            'key' => 'value',
            'number' => 42,
            'nested' => ['deep' => true],
            'russian' => "\xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82",
        ];

        // Write: JsonCodec::encode + FileIO::writeAtomic
        $json = JsonCodec::encode($data);
        FileIO::writeAtomic($path, $json);

        // Read: FileIO::readLocked + JsonCodec::decode
        $raw = FileIO::readLocked($path);
        $decoded = JsonCodec::decode($raw);

        $this->assertSame('value', $decoded['key'], 'JsonCodec + FileIO: string preserved');
        $this->assertSame(42, $decoded['number'], 'JsonCodec + FileIO: number preserved');
        $this->assertTrue($decoded['nested']['deep'], 'JsonCodec + FileIO: nested data preserved');
        $this->assertSame("\xD0\x9F\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82", $decoded['russian'], 'JsonCodec + FileIO: unicode preserved');
    }

    public function testCleanOrphanedLocks(): void
    {
        $lockDir = $this->testDir . '/locks-orphaned';
        mkdir($lockDir, 0o755, true);

        // Create a fake orphaned lock file with old timestamp
        $lockFile = $lockDir . '/orphan.json.lock';
        file_put_contents($lockFile, '');
        touch($lockFile, time() - 7200); // 2 hours old

        $cleaned = FileIO::cleanOrphanedLocks($lockDir, 3600);
        $this->assertSame(1, $cleaned, 'cleanOrphanedLocks() cleaned 1 orphaned lock');
        $this->assertFileDoesNotExist($lockFile, 'Orphaned lock file removed');
    }

    public function testCleanOrphanedLocksPreservesFresh(): void
    {
        $lockDir = $this->testDir . '/locks-fresh';
        mkdir($lockDir, 0o755, true);

        // Create a fresh lock file (just created)
        $freshLock = $lockDir . '/fresh.json.lock';
        file_put_contents($freshLock, '');
        // Touch is not needed - default mtime is now

        $cleaned = FileIO::cleanOrphanedLocks($lockDir, 3600);
        $this->assertSame(0, $cleaned, 'cleanOrphanedLocks() does not clean fresh locks');
        $this->assertFileExists($freshLock, 'Fresh lock file preserved');
    }
}
