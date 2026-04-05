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
        // Flip the last character to a guaranteed different hex digit
        // (avoids flakiness when the token happens to end in '0')
        $lastChar = substr($token, -1);
        $differentChar = $lastChar === '0' ? '1' : '0';
        $tampered = substr($token, 0, -1) . $differentChar;

        $this->assertFalse(app()->auth()->verifyCsrfToken($tampered));
    }

    public function testVerifyRejectsArrayToken(): void
    {
        app()->auth()->generateCsrfToken();

        // Simulates csrf_token[]=foo attack — must not raise TypeError
        $this->assertFalse(app()->auth()->verifyCsrfToken(['foo']));
    }

    public function testVerifyRejectsNullToken(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken(null));
    }

    public function testVerifyRejectsIntegerToken(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken(12345));
    }

    public function testVerifyRejectsEmptySessionToken(): void
    {
        // Session contains an empty-string token (corrupted state);
        // verifying an empty string must NOT pass.
        $_SESSION['csrf_token'] = '';

        $this->assertFalse(app()->auth()->verifyCsrfToken(''));
    }

    public function testVerifyRejectsNonStringSessionToken(): void
    {
        // Corrupted session: non-string value must not cause TypeError
        $_SESSION['csrf_token'] = 12345;

        $this->assertFalse(app()->auth()->verifyCsrfToken('anything'));
    }

    public function testVerifyRejectsObjectToken(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken(new \stdClass()));
    }

    public function testVerifyRejectsBooleanToken(): void
    {
        app()->auth()->generateCsrfToken();

        $this->assertFalse(app()->auth()->verifyCsrfToken(true));
        $this->assertFalse(app()->auth()->verifyCsrfToken(false));
    }

    // ==========================================================
    //  Auth::extractCsrfTokenFromRequest()
    // ==========================================================

    public function testExtractTokenFromPostBody(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $_POST['csrf_token'] = $token;
        $this->refreshRequest();

        $extracted = app()->auth()->extractCsrfTokenFromRequest(app()->request());
        $this->assertSame($token, $extracted);
    }

    public function testExtractTokenFallsBackToHeader(): void
    {
        $token = app()->auth()->generateCsrfToken();
        // No body token
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->refreshRequest();

        $extracted = app()->auth()->extractCsrfTokenFromRequest(app()->request());
        $this->assertSame($token, $extracted);
    }

    public function testExtractTokenPrefersBodyOverHeader(): void
    {
        $bodyToken = 'body_token_value';
        $_POST['csrf_token'] = $bodyToken;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'header_token_value';
        $this->refreshRequest();

        $extracted = app()->auth()->extractCsrfTokenFromRequest(app()->request());
        $this->assertSame($bodyToken, $extracted);
    }

    public function testExtractTokenIgnoresArrayInBody(): void
    {
        // Attacker sends csrf_token[]=foo — must degrade gracefully
        $_POST['csrf_token'] = ['foo'];
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'fallback_header_token';
        $this->refreshRequest();

        $extracted = app()->auth()->extractCsrfTokenFromRequest(app()->request());
        $this->assertSame('fallback_header_token', $extracted);
    }

    public function testExtractTokenReturnsEmptyWhenNothingProvided(): void
    {
        $this->refreshRequest();

        $extracted = app()->auth()->extractCsrfTokenFromRequest(app()->request());
        $this->assertSame('', $extracted);
    }

    public function testExtractTokenFromJsonBody(): void
    {
        $token = app()->auth()->generateCsrfToken();

        // Simulate a JSON request without touching php://input
        $jsonRequest = new class ($token) extends \Http\Request {
            public function __construct(private string $jsonToken)
            {
            }
            public function isJson(): bool
            {
                return true;
            }
            public function jsonBody()
            {
                return ['csrf_token' => $this->jsonToken];
            }
        };

        $extracted = app()->auth()->extractCsrfTokenFromRequest($jsonRequest);
        $this->assertSame($token, $extracted);
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

    public function testOptionsRequestPassesThrough(): void
    {
        $this->setRequestMethod('OPTIONS');

        $middleware = new CsrfMiddleware();
        $called = false;

        $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
    }

    // ==========================================================
    //  CsrfMiddleware — unsafe methods (PUT, PATCH, DELETE)
    // ==========================================================

    public function testPutRequestBlockedWithoutToken(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('PUT');
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

    public function testPutRequestPassesWithValidHeaderToken(): void
    {
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('PUT');
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

    public function testPutRequestPassesWithValidBodyToken(): void
    {
        // Symmetry check: token in $_POST must be accepted for PUT, not just GET/POST.
        // PHP parses form-encoded bodies into $_POST regardless of HTTP method.
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('PUT');
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

    public function testDeleteRequestBlockedWithoutToken(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('DELETE');
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

    public function testPatchRequestBlockedWithoutToken(): void
    {
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('PATCH');
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

    public function testPostWithArrayTokenBlocks(): void
    {
        // csrf_token[]=foo attack — must be rejected, not raise TypeError
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');
        $_POST['csrf_token'] = ['foo'];
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

    public function testMiddlewareAcceptsTokenFromJsonBody(): void
    {
        // Full-stack integration: JSON request body → middleware → extractor → verify.
        // Bypasses php://input by stubbing Request::isJson() and jsonBody().
        $token = app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');

        app()->provide('request', function () use ($token) {
            return new class ($token) extends \Http\Request {
                public function __construct(private string $jsonToken)
                {
                }
                public function method(): string
                {
                    return 'POST';
                }
                public function isJson(): bool
                {
                    return true;
                }
                public function jsonBody()
                {
                    return ['csrf_token' => $this->jsonToken];
                }
            };
        });

        $middleware = new CsrfMiddleware();
        $called = false;

        $result = $middleware->handle(function () use (&$called) {
            $called = true;
            return true;
        });

        $this->assertTrue($called);
        $this->assertTrue($result);
    }

    public function testMiddlewareBlocksInvalidJsonBodyToken(): void
    {
        // Negative counterpart: JSON body contains wrong token → middleware blocks.
        app()->auth()->generateCsrfToken();
        $this->setRequestMethod('POST');

        app()->provide('request', function () {
            return new class () extends \Http\Request {
                public function method(): string
                {
                    return 'POST';
                }
                public function isJson(): bool
                {
                    return true;
                }
                public function jsonBody()
                {
                    return ['csrf_token' => 'forged-token'];
                }
                public function acceptsJson(): bool
                {
                    return true;
                }
            };
        });

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
