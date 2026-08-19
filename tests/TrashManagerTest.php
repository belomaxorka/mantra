<?php declare(strict_types=1);

use Storage\TrashManager;

class TrashManagerTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-trash-' . bin2hex(random_bytes(4));
        mkdir($this->testDir . '/source', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testMoveAndRestoreDirectory(): void
    {
        $source = $this->testDir . '/source/module';
        mkdir($source, 0o755, true);
        file_put_contents($source . '/module.json', '{}');
        $trash = new TrashManager($this->testDir . '/trash');

        $trashed = $trash->move($source, 'modules', 'example');
        $this->assertDirectoryDoesNotExist($source);
        $this->assertFileExists($trashed . '/module.json');

        $this->assertTrue($trash->restore($trashed, $source));
        $this->assertFileExists($source . '/module.json');
    }

    public function testRejectsInvalidBucket(): void
    {
        $this->expectException(RuntimeException::class);
        (new TrashManager($this->testDir . '/trash'))->move(
            $this->testDir . '/source',
            '../escape',
            'item',
        );
    }
}
