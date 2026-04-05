<?php declare(strict_types=1);

/**
 * Router Tests
 *
 * Focused on middleware integration with the Router. The router itself
 * is deliberately tested lightly — these tests cover the contract that
 * the dispatch layer must honor, not the full URI matching logic.
 *
 * @covers \Router
 */
class RouterTest extends MantraTestCase
{
    private array $savedServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedServer = $_SERVER;

        // Stub the view service so abort() inside notFound() does not try
        // to render real templates — we capture the call instead.
        app()->provide('view', fn () => new CapturingView());
        app()->provide('response', fn () => new \Http\Response());

        // Reset the middleware registry to a known state
        app()->provide('middleware', fn () => new \Http\MiddlewareRegistry());
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;

        // Restore default services
        app()->provide('view', fn () => new View());
        app()->provide('request', fn () => new \Http\Request());

        parent::tearDown();
    }

    // ==========================================================
    //  Global middleware on 404 requests
    // ==========================================================

    public function testGlobalMiddlewareRunsWhenNoRouteMatches(): void
    {
        $executed = false;
        $this->setUpRequest('GET', '/does-not-exist');

        // Halting middleware records execution and prevents notFound() from
        // rendering the 404 template — lets the test stay free of view state.
        $mw = new class ($executed) implements \Http\MiddlewareInterface {
            public bool $wasCalled = false;
            public function __construct(bool &$flag)
            {
                $this->flag = &$flag;
            }
            public bool $flag;
            public function handle(callable $next): bool
            {
                $this->flag = true;
                $this->wasCalled = true;
                return false; // halt before notFound runs
            }
        };

        app()->middleware()->register('marker', $mw);

        $router = new Router();
        $router->addGlobalMiddleware('*', 'marker');

        $router->dispatch();

        $this->assertTrue($executed, 'Global middleware must run even when no route matches');
    }

    public function testGlobalMiddlewareRespectsPatternOnNotFound(): void
    {
        // /admin/* middleware must still scope correctly on 404 requests —
        // a request to /blog/nonexistent should not trigger admin middleware.
        $adminCalled = false;
        $this->setUpRequest('GET', '/blog/nonexistent');

        $mw = new class ($adminCalled) implements \Http\MiddlewareInterface {
            public bool $flag;
            public function __construct(bool &$flag)
            {
                $this->flag = &$flag;
            }
            public function handle(callable $next): bool
            {
                $this->flag = true;
                return false;
            }
        };

        app()->middleware()->register('admin-only', $mw);

        $router = new Router();
        $router->addGlobalMiddleware('/admin/*', 'admin-only');

        $router->dispatch();

        $this->assertFalse($adminCalled, 'Admin-scoped middleware must not fire on /blog/* 404');
    }

    public function testNotFoundReachedWhenGlobalMiddlewareDoesNotHalt(): void
    {
        // With no halting middleware, notFound() runs — but our capturing
        // view lets us verify the 404 was actually produced.
        $this->setUpRequest('GET', '/really-missing');

        $passthrough = new class () implements \Http\MiddlewareInterface {
            public int $calls = 0;
            public function handle(callable $next): bool
            {
                $this->calls++;
                return $next();
            }
        };

        app()->middleware()->register('pass', $passthrough);

        $router = new Router();
        $router->addGlobalMiddleware('*', 'pass');

        $router->dispatch();

        $this->assertSame(1, $passthrough->calls);

        /** @var CapturingView $view */
        $view = app()->service('view');
        $this->assertSame('404', $view->lastTemplate, 'notFound() must render the 404 template');
        $this->assertSame(404, http_response_code());
    }

    public function testMatchedRouteStillWorks(): void
    {
        // Regression: the refactored dispatch() must not break happy-path routing.
        $this->setUpRequest('GET', '/hello');

        $handlerCalled = false;
        $router = new Router();
        $router->get('/hello', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $router->dispatch();

        $this->assertTrue($handlerCalled);
    }

    public function testMatchedRouteAppliesGlobalMiddleware(): void
    {
        // Regression: global middleware still applies to matched routes.
        $this->setUpRequest('GET', '/hello');

        $log = [];
        $mw = new class ($log) implements \Http\MiddlewareInterface {
            public array $log;
            public function __construct(array &$log)
            {
                $this->log = &$log;
            }
            public function handle(callable $next): bool
            {
                $this->log[] = 'before';
                $result = $next();
                $this->log[] = 'after';
                return $result;
            }
        };

        app()->middleware()->register('wrap', $mw);

        $router = new Router();
        $router->addGlobalMiddleware('*', 'wrap');
        $router->get('/hello', function () use (&$log): void {
            $log[] = 'handler';
        });

        $router->dispatch();

        $this->assertSame(['before', 'handler', 'after'], $log);
    }

    // ==========================================================
    //  Helpers
    // ==========================================================

    private function setUpRequest(string $method, string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        app()->provide('request', fn () => new \Http\Request());
    }
}

// ==========================================================
//  Test doubles
// ==========================================================

/**
 * View stub that records render() calls without touching the filesystem.
 */
class CapturingView extends \View
{
    public ?string $lastTemplate = null;
    public array $lastData = [];

    public function render($template, $data = [], $options = []): void
    {
        $this->lastTemplate = (string)$template;
        $this->lastData = (array)$data;
        http_response_code(http_response_code()); // no-op, keep current code
    }
}
