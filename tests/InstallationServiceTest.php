<?php declare(strict_types=1);

class InstallationServiceTest extends MantraTestCase
{
    private $testDir;
    private $db;
    private $users;
    private $installer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-installer-' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0o755, true);
        $this->db = new Database($this->testDir);
        $this->users = new User($this->db);
        $this->installer = new InstallationService($this->users);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testCredentialValidationUsesUserContract(): void
    {
        $this->assertSame(
            'error_required_fields',
            InstallationService::validateAdminCredentials('', ''),
        );
        $this->assertSame(
            'error_invalid_username',
            InstallationService::validateAdminCredentials('invalid user', 'StrongPassword123!'),
        );
        $this->assertSame(
            'error_password_too_short',
            InstallationService::validateAdminCredentials('valid_user', 'short'),
        );
        $this->assertNull(InstallationService::validateAdminCredentials(
            str_repeat('a', User::MAX_USERNAME_LENGTH),
            str_repeat('x', User::MIN_PASSWORD_LENGTH),
        ));
        $this->assertSame(
            'error_invalid_username',
            InstallationService::validateAdminCredentials(
                str_repeat('a', User::MAX_USERNAME_LENGTH + 1),
                str_repeat('x', User::MIN_PASSWORD_LENGTH),
            ),
        );
    }

    public function testCreateInitialAdminUsesSchemaAndDatabaseTimestamps(): void
    {
        $id = $this->installer->createInitialAdmin('  initial_admin  ', 'StrongPassword123!');

        $this->assertIsString($id);
        $admin = $this->users->find($id);
        $this->assertSame('initial_admin', $admin['username']);
        $this->assertSame('admin', $admin['role']);
        $this->assertSame('active', $admin['status']);
        $this->assertSame('', $admin['email']);
        $this->assertSame(1, $admin['schema_version']);
        $this->assertTrue(password_verify('StrongPassword123!', $admin['password']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $admin['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $admin['updated_at']);
        $this->assertLessThanOrEqual(
            1,
            abs(strtotime($admin['updated_at']) - strtotime($admin['created_at'])),
        );
    }

    public function testCreateInitialAdminRejectsInvalidOrDuplicateCredentials(): void
    {
        $this->assertFalse($this->installer->createInitialAdmin('admin', 'short'));
        $this->assertIsString($this->installer->createInitialAdmin('InitialAdmin', 'StrongPassword123!'));
        $this->assertFalse($this->installer->createInitialAdmin('initialadmin', 'AnotherPassword123!'));
    }
}
