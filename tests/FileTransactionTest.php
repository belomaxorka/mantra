<?php declare(strict_types=1);

use Storage\FileIO;
use Storage\FileTransaction;

class FileTransactionTest extends MantraTestCase
{
    private $testDir;
    private $transactionRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-file-transaction-' . bin2hex(random_bytes(4));
        $this->transactionRoot = $this->testDir . '/transactions';
        mkdir($this->testDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testCommitsMultipleFileOperations(): void
    {
        $first = $this->testDir . '/first.txt';
        $second = $this->testDir . '/second.txt';
        FileIO::writeAtomic($first, 'old');
        FileIO::writeAtomic($second, 'delete');

        (new FileTransaction($this->transactionRoot))
            ->write($first, 'new')
            ->delete($second)
            ->commit();

        $this->assertSame('new', file_get_contents($first));
        $this->assertFileDoesNotExist($second);
        $this->assertSame([], glob($this->transactionRoot . '/tx-*', GLOB_ONLYDIR));
    }

    public function testRecoversPreparedJournal(): void
    {
        $target = $this->testDir . '/document.txt';
        FileIO::writeAtomic($target, 'mutated');

        $journalDir = $this->transactionRoot . '/tx-manual';
        mkdir($journalDir, 0o755, true);
        FileIO::writeAtomic($journalDir . '/0.backup', 'original');
        FileIO::writeAtomic($journalDir . '/journal.json', JsonCodec::encode([
            'state' => 'prepared',
            'targets' => [[
                'path' => $target,
                'existed' => true,
                'backup' => '0.backup',
            ]],
        ]));

        $this->assertSame(1, FileTransaction::recoverPending($this->transactionRoot));
        $this->assertSame('original', file_get_contents($target));
        $this->assertDirectoryDoesNotExist($journalDir);
    }

    public function testDiscardsInterruptedJournalPreparationWithoutTouchingTargets(): void
    {
        $target = $this->testDir . '/document.txt';
        FileIO::writeAtomic($target, 'current');

        $journalDir = $this->transactionRoot . '/tx-preparing';
        mkdir($journalDir, 0o755, true);
        FileIO::writeAtomic($journalDir . '/0.backup', 'stale-backup');
        FileIO::writeAtomic($journalDir . '/journal.json', JsonCodec::encode([
            'state' => 'preparing',
            'targets' => [[
                'path' => $target,
                'existed' => true,
                'backup' => '0.backup',
            ]],
        ]));

        $this->assertSame(0, FileTransaction::recoverPending($this->transactionRoot));
        $this->assertSame('current', file_get_contents($target));
        $this->assertDirectoryDoesNotExist($journalDir);
    }

    public function testRejectsTargetOutsideProject(): void
    {
        $this->expectException(Storage\FileTransactionException::class);
        (new FileTransaction($this->transactionRoot))->delete(dirname(MANTRA_ROOT) . '/outside.txt');
    }
}
