<?php declare(strict_types=1);

namespace Storage {
    /** Test-only control for the namespaced rename() shim below. */
    final class FileIORenameTestDouble
    {
        public static ?string $target = null;
        public static int $calls = 0;
        public static int $failuresRemaining = 0;
        public static string $failureMessage = '';

        public static function failFor(string $target, int $times, string $message): void
        {
            self::$target = $target;
            self::$calls = 0;
            self::$failuresRemaining = $times;
            self::$failureMessage = $message;
        }

        public static function reset(): void
        {
            self::$target = null;
            self::$calls = 0;
            self::$failuresRemaining = 0;
            self::$failureMessage = '';
        }
    }

    /** @internal Deterministic failure injection for FileIO replacement tests. */
    function rename(string $from, string $to): bool
    {
        if ($to === FileIORenameTestDouble::$target) {
            FileIORenameTestDouble::$calls++;
            if (FileIORenameTestDouble::$failuresRemaining > 0) {
                FileIORenameTestDouble::$failuresRemaining--;
                trigger_error(FileIORenameTestDouble::$failureMessage, E_USER_WARNING);
                return false;
            }
        }

        return \rename($from, $to);
    }
}

namespace {
    use Storage\FileIO;
    use Storage\FileIOException;
    use Storage\FileIORenameTestDouble;

    class FileIORetryTest extends MantraTestCase
    {
        private string $testDir;

        protected function setUp(): void
        {
            parent::setUp();
            $this->testDir = MANTRA_STORAGE . '/test-fileio-retry-' . bin2hex(random_bytes(6));
            mkdir($this->testDir, 0o755, true);
        }

        protected function tearDown(): void
        {
            FileIORenameTestDouble::reset();
            $this->removeDirectory($this->testDir);
            parent::tearDown();
        }

        public function testTransientReplaceFailuresAreRetried(): void
        {
            $path = $this->testDir . '/transient.txt';
            FileIO::writeAtomic($path, 'old content');
            FileIORenameTestDouble::failFor(
                $path,
                2,
                'rename(): Отказано в доступе (code: 5)',
            );

            $this->assertTrue(FileIO::writeAtomic($path, 'new content'));
            $this->assertSame(3, FileIORenameTestDouble::$calls);
            $this->assertSame('new content', FileIO::readLocked($path));
            $this->assertSame([], glob($this->testDir . '/*.tmp.*'));
        }

        public function testStructuralReplaceFailureIsNotRetried(): void
        {
            $path = $this->testDir . '/structural.txt';
            FileIO::writeAtomic($path, 'last known good');
            FileIORenameTestDouble::failFor($path, 1, 'rename(): No such file or directory');

            try {
                FileIO::writeAtomic($path, 'replacement');
                $this->fail('Expected FileIOException');
            } catch (FileIOException $e) {
                $this->assertSame('Failed to replace file', $e->getMessage());
            }

            $this->assertSame(1, FileIORenameTestDouble::$calls);
            $this->assertSame('last known good', FileIO::readLocked($path));
            $this->assertSame([], glob($this->testDir . '/*.tmp.*'));
        }

        public function testExhaustedTransientFailuresPreserveTargetAndCleanTempFile(): void
        {
            $path = $this->testDir . '/exhausted.txt';
            FileIO::writeAtomic($path, 'last known good');
            FileIORenameTestDouble::failFor($path, PHP_INT_MAX, 'rename(): Permission denied');

            try {
                FileIO::writeAtomic($path, 'replacement');
                $this->fail('Expected FileIOException');
            } catch (FileIOException $e) {
                $this->assertSame('Failed to replace file', $e->getMessage());
            }

            $this->assertSame(6, FileIORenameTestDouble::$calls);
            $this->assertSame('last known good', FileIO::readLocked($path));
            $this->assertSame([], glob($this->testDir . '/*.tmp.*'));
        }
    }
}
