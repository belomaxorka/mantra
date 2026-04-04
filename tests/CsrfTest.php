<?php declare(strict_types=1);

/**
 * CSRF Protection Tests
 *
 * Tests Auth CSRF token generation/verification and CsrfMiddleware behavior.
 *
 * @covers \Auth
 * @covers \CsrfMiddleware
 */
class CsrfTest extends MantraTestCase
{
    private array $savedSession;
    private array $savedServer;
    private array $savedPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedSession = $_SESSION ?? [];
        $this->savedServer = $_SERVER;
        $this->savedPost = $_POST;

        $_SESSION = [];
        $_POST = [];

        // Load CsrfMiddleware (not under core/classes/, so autoloader won't find it)
        if (!class_exists('CsrfMiddleware', false)) {
            require_once MANTRA_MODULES . '/admin/middleware/CsrfMiddleware.php';
        }

        // Fresh Auth instance with clean session (no current user)
        app()->provide('auth', fn() => new Auth());
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
        $_SERVER = $this->savedServer;
        $_POST = $this->savedPost;

        // Restore default services
        app()->provide('auth', fn() => new Auth());
        app()->provide('request', fn() => new \Http\Request());

        parent::tearDown();
    }

    // ==========================================================
    //  Auth::generateCsrfToken()
    // ==========================================================

    public function testGenerateTokenReturns64HexChars(): void
    {
        $token = app()->auth()->generateCsrfToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateTokenStoresInSession(): void
    {
        $token = app()->auth()->generateCsrfToken();

        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateTokenReusesExistingToken(): void
    {
        $first = app()->auth()->generateCsrfToken();
        $second = app()->auth()->generateCsrfToken();

        $this->assertSame($first, $second);
    }

    public function testGenerateTokenCreatesNewWhenSessionEmpty(): void
    {
        $token1 = app()->auth()->generateCsrfToken();

        // Clear and recreate Auth to reset internal state
        unset($_SESSION['csrf_token']);
        app()->provide('auth', fn() => new Auth());

        $token2 = app()->auth()->generateCsrfToken();

        $this->assertNotSame($token1, $token2);
    }

    public function testGenerateTokenReplacesEmptyStringInSession(): void
    {
        $_SESSION['csrf_token'] = '';

        $token = app()->auth()->generateCsrfToken();

        $this->assertNotSame('', $token);
        $this->assertSame(64, strlen($token));
    }

    public function testGenerateTokenReplacesNonStringInSession(): void
    {
        $_SESSION['csrf_token'] = 12345;

        $token = app()->auth()->generateCsrfToken();

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
    }

    // ==========================================================
    //  Auth::verifyCsrfToken()
    // ==========================================================

    public function testVerifyValidTokenReturnsTrue(): void
    {
        $token = app()->auth()->generateCsrfToken();

        $this->assertTrue(app()->auth()->verifyCsrfToken($token));
    }

    public function testVerifyInvalidTokenReturnsFalse(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken('wrong_token'));
    }

    public function testVerifyReturnsFalseWhenNoTokenInSession(): void
    {
        // No token generated — session is empty
        $this->assertFalse(app()->auth()->verifyCsrfToken('anything'));
    }

    public function testVerifyEmptyStringReturnsFalse(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken(''));
    }

    public function testVerifyNearMatchReturnsFalse(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $tampered = substr($token, 0, -1) . '0';

        $this->assertFalse(app()->auth()->verifyCsrfToken($tampered));
    }

    // ==========================================================
    //  Token rotation after login
    // ==========================================================

    public function testLoginDeletesCsrfTokenFromSession(): void
    {
        // Generate a pre-login token
        $preLoginToken = app()->auth()->generateCsrfToken();
        $this->assertSame($preLoginToken, $_SESSION['csrf_token']);

        // Simulate what Auth::login() does after successful authentication
        app()->session()->delete('csrf_token');

        $this->assertArrayNotHasKey('csrf_token', $_SESSION);

        // Next call should generate a fresh token
        app()->provide('auth', fn() => new Auth());
        $postLoginToken = app()->auth()->generateCsrfToken();

        $this->assertNotSame($preLoginToken, $postLoginToken);
    }

    // ==========================================================
    //  CsrfMiddleware — GET requests pass through
    // ==========================================================

    public function testGetRequestPassesThrough(): void
    {
        $this->setRequestMethod('GET');

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testHeadRequestPassesThrough(): void
    {
        $this->setRequestMethod('HEAD');

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
    }

    // ==========================================================
    //  CsrfMiddleware — POST with valid token
    // ==========================================================

    public function testPostWithValidTokenInBodyPasses(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = $token;
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testPostWithValidTokenInHeaderPasses(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    // ==========================================================
    //  CsrfMiddleware — POST with invalid/missing token
    // ==========================================================

    public function testPostWithInvalidTokenBlocks(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = 'wrong';
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        ob_start();
        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    public function testPostWithMissingTokenBlocks(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        // No csrf_token in POST or header
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        ob_start();
        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    public function testPostWithEmptyTokenBlocks(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = '';
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        ob_start();
        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    // ==========================================================
    //  CsrfMiddleware — response format
    // ==========================================================

    public function testBlockedJsonResponseFormat(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();

        ob_start();
        $middleware->handle(fn() => true);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['ok']);
        $this->assertSame('Invalid CSRF token', $decoded['error']);
    }

    public function testBlockedPlainResponseFormat(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        // No Accept: application/json
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();

        ob_start();
        $middleware->handle(fn() => true);
        $output = ob_get_clean();

        $this->assertSame('Invalid CSRF token', $output);
    }

    // ==========================================================
    //  CsrfMiddleware — token source priority
    // ==========================================================

    public function testBodyTokenTakesPriorityOverHeader(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = $token;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'wrong_header_token';
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        // Body token is valid, should pass even though header is wrong
        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testHeaderUsedWhenBodyEmpty(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = '';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->refreshRequest();

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    // ==========================================================
    //  CsrfMiddleware — pipeline integration
    // ==========================================================

    public function testCsrfMiddlewareInPipeline(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = $token;
        $this->refreshRequest();

        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(new CsrfMiddleware());

        $called = false;
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testCsrfMiddlewareBlocksInPipeline(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        // No token
        $this->refreshRequest();

        $pipeline = new \Http\MiddlewarePipeline();
        $pipeline->pipe(new CsrfMiddleware());

        $called = false;
        ob_start();
        $result = $pipeline->run(function () use (&$called): void {
            $called = true;
        });
        ob_end_clean();

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    // ==========================================================
    //  Helpers
    // ==========================================================

    private function setRequestMethod(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
    }

    private function refreshRequest(): void
    {
        app()->provide('request', fn() => new \Http\Request());
    }

}
