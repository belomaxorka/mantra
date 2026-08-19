<?php declare(strict_types=1);

use Storage\RevisionStore;

class RevisionStoreTest extends MantraTestCase
{
    private $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-revisions-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testRetentionKeepsNewestSnapshots(): void
    {
        $store = new RevisionStore($this->testDir, 2);
        $store->capture('posts', 'doc', ['title' => 'one']);
        $store->capture('posts', 'doc', ['title' => 'two']);
        $store->capture('posts', 'doc', ['title' => 'three']);

        $revisions = $store->all('posts', 'doc');
        $this->assertCount(2, $revisions);
        $this->assertSame('three', $revisions[0]['data']['title']);
        $this->assertSame('two', $revisions[1]['data']['title']);
    }

    public function testDatabaseCanRestoreUpdatedAndDeletedDocument(): void
    {
        $databaseRoot = $this->testDir . '/database';
        $store = new RevisionStore($this->testDir . '/history', 10);
        $db = new Database($databaseRoot, $store);

        $id = $db->create('notes', ['title' => 'original']);
        $db->write('notes', $id, ['title' => 'changed']);
        $updateRevision = $db->revisions('notes', $id)[0];

        $this->assertSame('original', $updateRevision['data']['title']);
        $this->assertTrue($db->restoreRevision('notes', $id, $updateRevision['revision_id']));
        $this->assertSame('original', $db->read('notes', $id)['title']);

        $this->assertTrue($db->delete('notes', $id));
        $deleteRevision = $db->revisions('notes', $id)[0];
        $this->assertSame('delete', $deleteRevision['reason']);
        $this->assertTrue($db->restoreRevision('notes', $id, $deleteRevision['revision_id']));
        $this->assertSame('original', $db->read('notes', $id)['title']);
    }

    public function testDatabaseDeletesRelatedFileThroughItsTransactionBoundary(): void
    {
        $databaseRoot = $this->testDir . '/database-related';
        $store = new RevisionStore($this->testDir . '/history-related', 10);
        $db = new Database($databaseRoot, $store);
        $id = $db->create('uploads', ['name' => 'asset']);
        $asset = $this->testDir . '/asset.bin';
        file_put_contents($asset, 'binary');

        $this->assertTrue($db->deleteWithRelatedFiles('uploads', $id, [$asset]));
        $this->assertFileDoesNotExist($asset);
        $this->assertNull($db->read('uploads', $id));
        $this->assertSame('delete', $db->revisions('uploads', $id)[0]['reason']);
    }
}
