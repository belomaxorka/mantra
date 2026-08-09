<?php declare(strict_types=1);

class PermissionRegistryTest extends MantraTestCase
{
    public function testExplicitEmptyOverrideRevokesAllDefaults(): void
    {
        $config = new InMemoryConfig([
            'permissions' => [
                'roles' => [
                    'editor' => [],
                ],
            ],
        ]);
        $registry = new PermissionRegistry($config);
        $registry->registerPermissions(['posts.view', 'posts.edit'], 'Posts');
        $registry->addRoleDefaults('editor', ['posts.view', 'posts.edit']);

        $this->assertTrue($registry->hasOverride('editor'));
        $this->assertSame([], $registry->getPermissionsForRole('editor'));
        $this->assertFalse($registry->hasPermission('editor', 'posts.view'));
    }

    public function testResetRemovesOverrideAndRestoresDefaults(): void
    {
        $config = new InMemoryConfig([
            'permissions' => [
                'roles' => [
                    'viewer' => [],
                ],
            ],
        ]);
        $registry = new PermissionRegistry($config);
        $registry->registerPermissions(['pages.view'], 'Pages');
        $registry->addRoleDefaults('viewer', ['pages.view']);

        $registry->resetRole('viewer');

        $this->assertFalse($registry->hasOverride('viewer'));
        $this->assertSame(['pages.view'], $registry->getPermissionsForRole('viewer'));
    }
}
