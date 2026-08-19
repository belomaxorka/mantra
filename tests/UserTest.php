<?php declare(strict_types=1);

class UserTest extends MantraTestCase
{
    private $testDir;
    private $db;
    private $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-users-' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0o755, true);
        $this->db = new Database($this->testDir);
        $this->users = new User($this->db);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testDefaultsToSharedApplicationDatabase(): void
    {
        $users = new User();
        $property = new ReflectionProperty(User::class, 'db');

        $this->assertSame(app()->db(), $property->getValue($users));
    }

    public function testCreateValidatesAndHashesUserData(): void
    {
        $id = $this->users->create([
            'username' => '  valid_user  ',
            'email' => '  user@example.com  ',
            'password' => 'StrongPassword123!',
        ]);

        $this->assertIsString($id);
        $stored = $this->users->find($id);
        $this->assertSame('valid_user', $stored['username']);
        $this->assertSame('user@example.com', $stored['email']);
        $this->assertSame('editor', $stored['role']);
        $this->assertSame('active', $stored['status']);
        $this->assertTrue(password_verify('StrongPassword123!', $stored['password']));
        $this->assertSame(1, $stored['schema_version']);
    }

    public function testCreateRejectsInvalidCoreFields(): void
    {
        $valid = [
            'username' => 'valid_user',
            'email' => 'user@example.com',
            'password' => 'StrongPassword123!',
            'role' => 'editor',
            'status' => 'active',
        ];

        foreach ([
            array_merge($valid, ['username' => 'ab']),
            array_merge($valid, ['username' => 'invalid user']),
            array_merge($valid, ['email' => 'not-an-email']),
            array_merge($valid, ['password' => 'short']),
            array_merge($valid, ['password' => ['not-a-string']]),
            array_merge($valid, ['role' => 'superadmin']),
            array_merge($valid, ['status' => 'unknown']),
        ] as $invalid) {
            $this->assertFalse($this->users->create($invalid));
        }

        $this->assertSame([], $this->users->all());
    }

    public function testUsernameAndEmailAreUniqueCaseInsensitively(): void
    {
        $this->assertIsString($this->users->create([
            'username' => 'AuditAdmin',
            'email' => 'Admin@Example.com',
            'password' => 'StrongPassword123!',
        ]));

        $this->assertFalse($this->users->create([
            'username' => 'auditadmin',
            'email' => 'other@example.com',
            'password' => 'StrongPassword123!',
        ]));
        $this->assertFalse($this->users->create([
            'username' => 'other_admin',
            'email' => 'admin@example.COM',
            'password' => 'StrongPassword123!',
        ]));
        $this->assertSame('AuditAdmin', $this->users->findByUsername('AUDITADMIN')['username']);
        $this->assertSame('AuditAdmin', $this->users->findByEmail('ADMIN@example.com')['username']);
    }

    public function testUpdateValidatesProfileAndPassword(): void
    {
        $id = $this->users->create([
            'username' => 'editor_one',
            'email' => 'before@example.com',
            'password' => 'StrongPassword123!',
        ]);
        $originalHash = $this->users->find($id)['password'];

        $this->assertFalse($this->users->update($id, ['email' => 'invalid']));
        $this->assertFalse($this->users->update($id, ['role' => 'owner']));
        $this->assertFalse($this->users->update($id, ['status' => 'deleted']));
        $this->assertFalse($this->users->update($id, ['password' => 'short']));
        $this->assertSame($originalHash, $this->users->find($id)['password']);

        $this->assertTrue($this->users->update($id, [
            'username' => 'renamed_user',
            'email' => 'after@example.com',
            'password' => 'AnotherStrongPassword123!',
        ]));
        $updated = $this->users->find($id);
        $this->assertSame('editor_one', $updated['username']);
        $this->assertSame('after@example.com', $updated['email']);
        $this->assertTrue(password_verify('AnotherStrongPassword123!', $updated['password']));
    }

    public function testLastActiveAdministratorCannotBeDemotedOrDeactivated(): void
    {
        $activeId = $this->createAdmin('active_admin', 'active');
        $inactiveId = $this->createAdmin('inactive_admin', 'inactive');

        $this->assertFalse($this->users->update($activeId, ['role' => 'editor']));
        $this->assertFalse($this->users->update($activeId, ['status' => 'banned']));

        $this->assertTrue($this->users->update($inactiveId, ['status' => 'active']));
        $this->assertTrue($this->users->update($activeId, ['role' => 'editor']));
    }

    public function testDeleteProtectsLastActiveAdministrator(): void
    {
        $activeId = $this->createAdmin('active_admin', 'active');
        $inactiveId = $this->createAdmin('inactive_admin', 'inactive');

        $this->assertFalse($this->users->delete($activeId));
        $this->assertTrue($this->users->delete($inactiveId));

        $secondActiveId = $this->createAdmin('second_admin', 'active');
        $this->assertTrue($this->users->delete($activeId));
        $this->assertNotNull($this->users->find($secondActiveId));
    }

    private function createAdmin($username, $status)
    {
        return $this->users->create([
            'username' => $username,
            'email' => '',
            'password' => 'StrongPassword123!',
            'role' => 'admin',
            'status' => $status,
        ]);
    }
}
