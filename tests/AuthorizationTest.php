<?php declare(strict_types=1);

class AuthorizationTest extends MantraTestCase
{
    public function testPermissionDecisionsAndOwnershipUseOneGateway(): void
    {
        $config = new InMemoryConfig();
        $registry = new PermissionRegistry($config);
        $registry->registerPermissions(['posts.edit', 'posts.edit.own'], 'Posts');
        $registry->addRoleDefaults('editor', ['posts.edit.own']);
        $authorization = new Authorization($registry);

        $editor = ['_id' => 'user-1', 'username' => 'editor', 'role' => 'editor'];

        $this->assertSame('own', $authorization->check($editor, 'posts.edit'));
        $this->assertTrue($authorization->owns($editor, ['author_id' => 'user-1']));
        $this->assertFalse($authorization->owns($editor, ['author_id' => 'user-2']));
        $this->assertTrue($authorization->check(['role' => 'admin'], 'anything'));
    }
}
