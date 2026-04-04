<?php declare(strict_types=1);

/**
 * AjaxDispatcher Tests
 *
 * Tests the unified AJAX action registration and dispatch system.
 *
 * Dispatch tests use a TestJsonResponse exception to capture what
 * Response::json() would send, since the real method calls exit.
 *
 * @covers \Ajax\AjaxDispatcher
 */
class AjaxDispatcherTest extends MantraTestCase
{
    private ?\Ajax\AjaxDispatcher $dispatcher = null;

    /** @var array Saved superglobals for restoration */
    private array $savedGet;
    private array $savedPost;
    private array $savedServer;

    protected function setUp(): void
    {
        parent::setUp();

        // Save superglobals
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $this->savedServer = $_SERVER;

        // Reset to clean state
        $_GET = [];
        $_POST = [];

        // Reset HookManager to clean state (prevents leakage between tests)
        $app = Application::getInstance();
        $ref = new ReflectionClass($app);
        $prop = $ref->getProperty('hookManager');
        $prop->setValue($app, new HookManager());

        // Override response service with one that throws instead of exit
        $app->provide('response', fn() => new TestResponse());

        // Create a fresh dispatcher (bypasses lazy service to avoid theme hook registration issues)
        $this->dispatcher = new \Ajax\AjaxDispatcher();
    }

    protected function tearDown(): void
    {
        // Restore superglobals
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        $_SERVER = $this->savedServer;

        // Restore real response service
        $app = Application::getInstance();
        $app->provide('response', fn() => new \Http\Response());

        $this->dispatcher = null;

        parent::tearDown();
    }

    // ========== Registration Tests ==========

    public function testRegisterAndHas(): void
    {
        $this->assertFalse($this->dispatcher->has('test.action'));

        $this->dispatcher->register('test.action', fn() => null);

        $this->assertTrue($this->dispatcher->has('test.action'));
    }

    public function testHasReturnsFalseForUnregistered(): void
    {
        $this->assertFalse($this->dispatcher->has('nonexistent'));
    }

    public function testGetRegisteredReturnsActionNames(): void
    {
        $this->assertSame([], $this->dispatcher->getRegistered());

        $this->dispatcher->register('alpha', fn() => null);
        $this->dispatcher->register('beta', fn() => null);
        $this->dispatcher->register('gamma', fn() => null);

        $this->assertSame(['alpha', 'beta', 'gamma'], $this->dispatcher->getRegistered());
    }

    public function testRegisterOverwritesPreviousAction(): void
    {
        $this->dispatcher->register('test.action', fn() => 'first');
        $this->dispatcher->register('test.action', fn() => 'second');

        $this->assertTrue($this->dispatcher->has('test.action'));
        $this->assertCount(1, $this->dispatcher->getRegistered());
    }

    // ========== Option Defaults Tests ==========

    public function testDefaultOptionsPostMethodAuthCsrf(): void
    {
        $this->dispatcher->register('test.post', fn() => null);

        $this->setRequest('GET', 'test.post');
        $response = $this->captureDispatch();

        // POST action should reject GET request with 405
        $this->assertSame(405, $response->getCode());
        $this->assertFalse($response->data['ok']);
    }

    public function testGetMethodDisablesCsrfByDefault(): void
    {
        $this->dispatcher->register('test.get', fn() => 'ok', [
            'method' => 'GET',
            'auth' => false,
        ]);

        // GET request without CSRF token should succeed
        $this->setRequest('GET', 'test.get');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
        $this->assertSame('ok', $response->data['data']);
    }

    public function testAnyMethodAcceptsBothGetAndPost(): void
    {
        $this->dispatcher->register('test.any', fn() => 'yes', [
            'method' => 'ANY',
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('GET', 'test.any');
        $response = $this->captureDispatch();
        $this->assertTrue($response->data['ok']);

        $this->setRequest('POST', 'test.any');
        $response = $this->captureDispatch();
        $this->assertTrue($response->data['ok']);
    }

    // ========== Dispatch: Unknown Action ==========

    public function testDispatchUnknownActionReturns404(): void
    {
        $this->setRequest('POST', 'nonexistent');
        $response = $this->captureDispatch();

        $this->assertSame(404, $response->getCode());
        $this->assertFalse($response->data['ok']);
        $this->assertSame('Unknown action', $response->data['error']);
    }

    public function testDispatchEmptyActionReturns404(): void
    {
        $this->setRequest('POST', '');
        $response = $this->captureDispatch();

        $this->assertSame(404, $response->getCode());
    }

    public function testDispatchMissingActionReturns404(): void
    {
        // No ?action= parameter at all
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = $this->captureDispatch();

        $this->assertSame(404, $response->getCode());
    }

    // ========== Dispatch: Method Check ==========

    public function testDispatchWrongMethodReturns405(): void
    {
        $this->dispatcher->register('test.post_only', fn() => null, [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('GET', 'test.post_only');
        $response = $this->captureDispatch();

        $this->assertSame(405, $response->getCode());
        $this->assertSame('Method not allowed', $response->data['error']);
    }

    // ========== Dispatch: Auth Check ==========

    public function testDispatchAuthRequiredReturns401WhenNotLoggedIn(): void
    {
        $this->dispatcher->register('test.auth', fn() => null, [
            'auth' => true,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.auth');
        $response = $this->captureDispatch();

        $this->assertSame(401, $response->getCode());
        $this->assertSame('Authentication required', $response->data['error']);
    }

    public function testDispatchNoAuthSkipsAuthCheck(): void
    {
        $this->dispatcher->register('test.public', fn() => 'public_data', [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.public');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
        $this->assertSame('public_data', $response->data['data']);
    }

    // ========== Dispatch: CSRF Check ==========

    public function testDispatchInvalidCsrfReturns403(): void
    {
        $this->dispatcher->register('test.csrf', fn() => null, [
            'auth' => false,
            'csrf' => true,
        ]);

        $this->setRequest('POST', 'test.csrf');
        // No CSRF token header
        $response = $this->captureDispatch();

        $this->assertSame(403, $response->getCode());
        $this->assertSame('Invalid CSRF token', $response->data['error']);
    }

    public function testDispatchValidCsrfPasses(): void
    {
        // Generate a real CSRF token in the session
        $token = app()->auth()->generateCsrfToken();

        $this->dispatcher->register('test.csrf_ok', fn() => 'passed', [
            'auth' => false,
            'csrf' => true,
        ]);

        $this->setRequest('POST', 'test.csrf_ok');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        // Force re-creation of Request so it picks up new headers
        app()->provide('request', fn() => new \Http\Request());

        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
    }

    public function testDispatchCsrfDisabledSkipsCheck(): void
    {
        $this->dispatcher->register('test.no_csrf', fn() => 'ok', [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.no_csrf');
        // No token, but csrf is disabled
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
    }

    // ========== Dispatch: Handler Execution ==========

    public function testDispatchCallsHandlerWithRequest(): void
    {
        $called = false;
        $receivedRequest = null;

        $this->dispatcher->register('test.handler', function ($request, $access) use (&$called, &$receivedRequest) {
            $called = true;
            $receivedRequest = $request;
            return ['result' => 42];
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.handler');
        $response = $this->captureDispatch();

        $this->assertTrue($called);
        $this->assertInstanceOf(\Http\Request::class, $receivedRequest);
        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
        $this->assertSame(['result' => 42], $response->data['data']);
    }

    public function testDispatchHandlerReceivesAccessTrue(): void
    {
        $receivedAccess = null;

        $this->dispatcher->register('test.access', function ($request, $access) use (&$receivedAccess) {
            $receivedAccess = $access;
            return null;
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.access');
        $this->captureDispatch();

        $this->assertTrue($receivedAccess);
    }

    public function testDispatchReturnsNullData(): void
    {
        $this->dispatcher->register('test.null', fn() => null, [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.null');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
        $this->assertNull($response->data['data']);
    }

    public function testDispatchReturnsStringData(): void
    {
        $this->dispatcher->register('test.string', fn() => 'hello', [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.string');
        $response = $this->captureDispatch();

        $this->assertSame('hello', $response->data['data']);
    }

    public function testDispatchReturnsArrayData(): void
    {
        $this->dispatcher->register('test.array', fn() => ['a' => 1, 'b' => 2], [
            'auth' => false,
            'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.array');
        $response = $this->captureDispatch();

        $this->assertSame(['a' => 1, 'b' => 2], $response->data['data']);
    }

    // ========== Dispatch: Error Handling ==========

    public function testDispatchAjaxExceptionReturnsErrorWithCode(): void
    {
        $this->dispatcher->register('test.ajax_err', function (): void {
            throw new \Ajax\AjaxException('File too large', 413);
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.ajax_err');
        $response = $this->captureDispatch();

        $this->assertSame(413, $response->getCode());
        $this->assertFalse($response->data['ok']);
        $this->assertSame('File too large', $response->data['error']);
    }

    public function testDispatchAjaxExceptionDefaultCode400(): void
    {
        $this->dispatcher->register('test.ajax_err_default', function (): void {
            throw new \Ajax\AjaxException('Bad input');
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.ajax_err_default');
        $response = $this->captureDispatch();

        $this->assertSame(400, $response->getCode());
        $this->assertSame('Bad input', $response->data['error']);
    }

    public function testDispatchUnhandledExceptionReturns500(): void
    {
        $this->dispatcher->register('test.crash', function (): void {
            throw new \RuntimeException('Something broke');
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.crash');
        $response = $this->captureDispatch();

        $this->assertSame(500, $response->getCode());
        $this->assertFalse($response->data['ok']);
        // In debug mode, message is passed through; in production, it's generic
        $this->assertNotEmpty($response->data['error']);
    }

    public function testDispatchAjaxExceptionInvalidCodeFallsTo400(): void
    {
        $this->dispatcher->register('test.bad_code', function (): void {
            throw new \Ajax\AjaxException('Weird error', 999);
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.bad_code');
        $response = $this->captureDispatch();

        // Code 999 is outside 400-599 range, should fall back to 400
        $this->assertSame(400, $response->getCode());
    }

    // ========== Dispatch: Hooks ==========

    public function testAjaxBeforeHookCanHaltDispatch(): void
    {
        $this->dispatcher->register('test.halt', fn() => 'should not reach', [
            'auth' => false,
            'csrf' => false,
        ]);

        app()->hooks()->register('ajax.before', function ($context) {
            $context['halt'] = true;
            $context['error'] = 'Rate limited';
            $context['code'] = 429;
            return $context;
        });

        $this->setRequest('POST', 'test.halt');
        $response = $this->captureDispatch();

        $this->assertSame(429, $response->getCode());
        $this->assertFalse($response->data['ok']);
        $this->assertSame('Rate limited', $response->data['error']);
    }

    public function testAjaxAfterHookCanModifyResponse(): void
    {
        $this->dispatcher->register('test.after', fn() => ['original' => true], [
            'auth' => false,
            'csrf' => false,
        ]);

        app()->hooks()->register('ajax.after', function ($response) {
            $response['data']['injected'] = true;
            return $response;
        });

        $this->setRequest('POST', 'test.after');
        $response = $this->captureDispatch();

        $this->assertTrue($response->data['ok']);
        $this->assertTrue($response->data['data']['original']);
        $this->assertTrue($response->data['data']['injected']);
    }

    // ========== Pipeline Order ==========

    public function testCheckOrderUnknownActionBeforeMethodCheck(): void
    {
        // Unknown action + wrong method → should be 404, not 405
        $this->setRequest('GET', 'nonexistent');
        $response = $this->captureDispatch();

        $this->assertSame(404, $response->getCode());
    }

    public function testCheckOrderMethodBeforeAuth(): void
    {
        $this->dispatcher->register('test.order', fn() => null, [
            'auth' => true,
            'csrf' => false,
        ]);

        // Wrong method + not logged in → should be 405, not 401
        $this->setRequest('GET', 'test.order');
        $response = $this->captureDispatch();

        $this->assertSame(405, $response->getCode());
    }

    public function testCheckOrderAuthBeforeCsrf(): void
    {
        $this->dispatcher->register('test.order2', fn() => null, [
            'auth' => true,
            'csrf' => true,
        ]);

        // Not logged in + no CSRF → should be 401, not 403
        $this->setRequest('POST', 'test.order2');
        $response = $this->captureDispatch();

        $this->assertSame(401, $response->getCode());
    }

    // ========== CSRF Edge Cases ==========

    public function testDispatchWrongCsrfTokenReturns403(): void
    {
        // Generate real token, then send a wrong one
        app()->auth()->generateCsrfToken();

        $this->dispatcher->register('test.wrong_csrf', fn() => null, [
            'auth' => false,
            'csrf' => true,
        ]);

        $this->setRequest('POST', 'test.wrong_csrf');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'definitely_not_the_right_token';
        app()->provide('request', fn() => new \Http\Request());

        $response = $this->captureDispatch();

        $this->assertSame(403, $response->getCode());
    }

    public function testGetWithCsrfForcedOn(): void
    {
        $this->dispatcher->register('test.get_csrf', fn() => null, [
            'method' => 'GET',
            'auth' => false,
            'csrf' => true,
        ]);

        // GET request, but csrf forced on — should fail without token
        $this->setRequest('GET', 'test.get_csrf');
        $response = $this->captureDispatch();

        $this->assertSame(403, $response->getCode());
    }

    // ========== Registration: Overwrite Behavior ==========

    public function testRegisterOverwriteCallsSecondHandler(): void
    {
        $this->dispatcher->register('test.overwrite', fn() => 'first', [
            'auth' => false, 'csrf' => false,
        ]);
        $this->dispatcher->register('test.overwrite', fn() => 'second', [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.overwrite');
        $response = $this->captureDispatch();

        $this->assertSame('second', $response->data['data']);
    }

    // ========== Action Name Edge Cases ==========

    public function testActionNameWithWhitespaceIsTrimmed(): void
    {
        $this->dispatcher->register('test.trim', fn() => 'ok', [
            'auth' => false, 'csrf' => false,
        ]);

        // Query string has spaces around name
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['action'] = '  test.trim  ';
        app()->provide('request', fn() => new \Http\Request());

        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertSame('ok', $response->data['data']);
    }

    public function testActionNameWithDotsAndDashes(): void
    {
        $this->dispatcher->register('my-module.do-thing.v2', fn() => 'ok', [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'my-module.do-thing.v2');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
    }

    // ========== Handler Return Edge Cases ==========

    public function testHandlerReturnsFalse(): void
    {
        $this->dispatcher->register('test.false', fn() => false, [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.false');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertTrue($response->data['ok']);
        $this->assertFalse($response->data['data']);
    }

    public function testHandlerReturnsZero(): void
    {
        $this->dispatcher->register('test.zero', fn() => 0, [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.zero');
        $response = $this->captureDispatch();

        $this->assertSame(0, $response->data['data']);
    }

    public function testHandlerReturnsEmptyString(): void
    {
        $this->dispatcher->register('test.empty_str', fn() => '', [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.empty_str');
        $response = $this->captureDispatch();

        $this->assertSame('', $response->data['data']);
    }

    public function testHandlerReturnsEmptyArray(): void
    {
        $this->dispatcher->register('test.empty_arr', fn() => [], [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.empty_arr');
        $response = $this->captureDispatch();

        $this->assertSame([], $response->data['data']);
    }

    public function testHandlerReturnsDeepNestedArray(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => 42]]]];

        $this->dispatcher->register('test.deep', fn() => $deep, [
            'auth' => false, 'csrf' => false,
        ]);

        $this->setRequest('POST', 'test.deep');
        $response = $this->captureDispatch();

        $this->assertSame(42, $response->data['data']['a']['b']['c']['d']);
    }

    // ========== Multiple Actions ==========

    public function testDispatchCorrectActionAmongMany(): void
    {
        $this->dispatcher->register('first', fn() => 'one', ['auth' => false, 'csrf' => false]);
        $this->dispatcher->register('second', fn() => 'two', ['auth' => false, 'csrf' => false]);
        $this->dispatcher->register('third', fn() => 'three', ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'second');
        $response = $this->captureDispatch();

        $this->assertSame('two', $response->data['data']);
    }

    public function testMultipleDispatchesWorkIndependently(): void
    {
        $counter = 0;

        $this->dispatcher->register('test.counter', function () use (&$counter) {
            $counter++;
            return $counter;
        }, ['auth' => false, 'csrf' => false]);

        $this->setRequest('POST', 'test.counter');
        $r1 = $this->captureDispatch();
        $r2 = $this->captureDispatch();

        $this->assertSame(1, $r1->data['data']);
        $this->assertSame(2, $r2->data['data']);
    }

    // ========== Hooks: Context & Defaults ==========

    public function testAjaxBeforeHookReceivesContext(): void
    {
        $receivedContext = null;

        $this->dispatcher->register('test.ctx', fn() => 'ok', [
            'auth' => false, 'csrf' => false,
        ]);

        app()->hooks()->register('ajax.before', function ($context) use (&$receivedContext) {
            $receivedContext = $context;
            return $context;
        });

        $this->setRequest('POST', 'test.ctx');
        $this->captureDispatch();

        $this->assertSame('test.ctx', $receivedContext['action']);
        $this->assertTrue($receivedContext['access']);
        $this->assertArrayHasKey('definition', $receivedContext);
        $this->assertSame('POST', $receivedContext['definition']['method']);
    }

    public function testAjaxBeforeHookHaltDefaultErrorAndCode(): void
    {
        $this->dispatcher->register('test.halt_defaults', fn() => null, [
            'auth' => false, 'csrf' => false,
        ]);

        app()->hooks()->register('ajax.before', function ($context) {
            $context['halt'] = true;
            // No 'error' or 'code' set — should use defaults
            return $context;
        });

        $this->setRequest('POST', 'test.halt_defaults');
        $response = $this->captureDispatch();

        $this->assertSame(403, $response->getCode());
        $this->assertSame('Blocked', $response->data['error']);
    }

    public function testAjaxBeforeHookWithoutHaltDoesNotBlock(): void
    {
        $this->dispatcher->register('test.pass', fn() => 'reached', [
            'auth' => false, 'csrf' => false,
        ]);

        app()->hooks()->register('ajax.before', function ($context) {
            // Modify context but do NOT set halt
            $context['custom'] = true;
            return $context;
        });

        $this->setRequest('POST', 'test.pass');
        $response = $this->captureDispatch();

        $this->assertSame(200, $response->getCode());
        $this->assertSame('reached', $response->data['data']);
    }

    public function testAjaxBeforeHookHandlerNotCalledOnHalt(): void
    {
        $called = false;

        $this->dispatcher->register('test.no_call', function () use (&$called) {
            $called = true;
            return 'should not happen';
        }, ['auth' => false, 'csrf' => false]);

        app()->hooks()->register('ajax.before', function ($context) {
            $context['halt'] = true;
            return $context;
        });

        $this->setRequest('POST', 'test.no_call');
        $this->captureDispatch();

        $this->assertFalse($called);
    }

    public function testAjaxAfterHookReceivesActionInContext(): void
    {
        $receivedContext = null;

        $this->dispatcher->register('test.after_ctx', fn() => 'data', [
            'auth' => false, 'csrf' => false,
        ]);

        app()->hooks()->register('ajax.after', function ($response, $context) use (&$receivedContext) {
            $receivedContext = $context;
            return $response;
        });

        $this->setRequest('POST', 'test.after_ctx');
        $this->captureDispatch();

        $this->assertSame('test.after_ctx', $receivedContext['action']);
    }

    // ========== Helpers ==========

    /**
     * Set up superglobals to simulate an AJAX request.
     */
    private function setRequest(string $method, string $action): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_GET['action'] = $action;

        // Force fresh Request instance so it reads updated superglobals
        app()->provide('request', fn() => new \Http\Request());
    }

    /**
     * Call dispatch() and capture the JSON response thrown by TestResponse.
     */
    private function captureDispatch(): TestJsonResponse
    {
        try {
            $this->dispatcher->dispatch();
            $this->fail('dispatch() should have thrown TestJsonResponse');
        } catch (TestJsonResponse $e) {
            return $e;
        }

        // @phpstan-ignore-next-line
        return new TestJsonResponse([], 500);
    }
}

// ========== Test Doubles ==========

/**
 * Exception that captures what Response::json() would send.
 * Thrown instead of calling exit, so tests can inspect the response.
 */
/**
 * Extends \Error (not \Exception) so the dispatcher's
 * catch(\Exception) blocks do not intercept it.
 */
class TestJsonResponse extends \Error
{
    public array $data;

    public function __construct(array $data, int $code = 200)
    {
        $this->data = $data;
        parent::__construct('TestJsonResponse', $code);
    }
}

/**
 * Response replacement that throws TestJsonResponse instead of calling exit.
 * Only json() is overridden; other methods behave normally.
 */
class TestResponse extends \Http\Response
{
    public function json($data, $code = 200): void
    {
        throw new TestJsonResponse((array)$data, (int)$code);
    }
}
