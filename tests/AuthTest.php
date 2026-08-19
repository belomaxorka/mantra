<?php declare(strict_types=1);

class AuthTest extends MantraTestCase
{
    private $testDir;
    private $db;
    private $users;
    private $previousSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = MANTRA_STORAGE . '/test-auth-' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0o755, true);
        $this->db = new Database($this->testDir);
        $this->users = new User($this->db);
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    public function testDefaultsToSharedApplicationDatabase(): void
    {
        $auth = new Auth();
        $property = new ReflectionProperty(Auth::class, 'db');

        $this->assertSame(app()->db(), $property->getValue($auth));
    }

    public function testLoginUsesCaseInsensitiveUsernameAndRotatesAuthState(): void
    {
        $id = $this->createUser('CaseUser', 'active');
        $_SESSION['csrf_token'] = 'pre-login-token';
        $auth = new Auth($this->db);

        $this->assertTrue($auth->login('caseuser', 'StrongPassword123!'));
        $this->assertTrue($auth->check());
        $this->assertSame($id, $_SESSION['user_id']);
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    public function testLoginRejectsInvalidPasswordAndBlockedAccount(): void
    {
        $this->createUser('active_user', 'active');
        $this->createUser('banned_user', 'banned');
        $auth = new Auth($this->db);

        $this->assertFalse($auth->login('active_user', 'WrongPassword123!'));
        $this->assertFalse($auth->login('banned_user', 'StrongPassword123!'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testExistingSessionLoadsOnlyActiveUser(): void
    {
        $id = $this->createUser('active_user', 'active');
        $_SESSION['user_id'] = $id;

        $auth = new Auth($this->db);

        $this->assertTrue($auth->check());
        $this->assertSame($id, $auth->user()['_id']);
    }

    public function testBlockedUserLosesExistingSession(): void
    {
        $id = $this->createUser('blocked_later', 'active');
        $this->assertTrue($this->users->update($id, ['status' => 'banned']));
        $_SESSION = [
            'user_id' => $id,
            'csrf_token' => 'authenticated-token',
        ];

        $auth = new Auth($this->db);

        $this->assertFalse($auth->check());
        $this->assertNull($auth->user());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    public function testMissingOrMalformedSessionUserIsCleared(): void
    {
        $_SESSION = ['user_id' => '../invalid', 'csrf_token' => 'token'];
        $invalid = new Auth($this->db);
        $this->assertFalse($invalid->check());
        $this->assertSame([], $_SESSION);

        $_SESSION = ['user_id' => 'does-not-exist', 'csrf_token' => 'token'];
        $missing = new Auth($this->db);
        $this->assertFalse($missing->check());
        $this->assertSame([], $_SESSION);
    }

    private function createUser($username, $status)
    {
        return $this->users->create([
            'username' => $username,
            'email' => '',
            'password' => 'StrongPassword123!',
            'role' => 'editor',
            'status' => $status,
        ]);
    }
}
