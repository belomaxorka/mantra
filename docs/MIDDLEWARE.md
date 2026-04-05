# Middleware — Developer Guide

Reference for the Mantra CMS middleware system.

## Architecture

```
Http\MiddlewareInterface    — Contract: handle($next)
Http\MiddlewarePipeline     — Execution engine: chains layers around a core handler
Http\MiddlewareRegistry     — Named middleware storage + groups
Module::registerMiddleware  — Auto-loads middleware classes from module manifest
Router::dispatch()          — Builds and runs the pipeline per request
```

Middleware sits between the router and the route handler. Each middleware can inspect or modify the request, decide whether to continue or halt, and optionally run code after the handler completes.

```
Request
  |
  +-- Global middleware (matched by URI pattern, sorted by priority)
  |     |
  |     +-- CsrfMiddleware::handle($next)
  |           |
  +-- Route middleware (attached to the matched route)
  |     |
  |     +-- AuthMiddleware::handle($next)
  |           |
  +-- Route handler (controller action)
  |           |
  |     +-- AuthMiddleware: code after $next() (if any)
  |           |
  |     +-- CsrfMiddleware: code after $next() (if any)
  |
  Response
```

## Quick Start

### 1. Create a middleware class

```
modules/my-module/middleware/RateLimitMiddleware.php
```

```php
<?php declare(strict_types=1);

class RateLimitMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        $ip = app()->request()->clientIp();

        if ($this->isRateLimited($ip)) {
            http_response_code(429);
            echo 'Too many requests';
            return false;  // halt the pipeline
        }

        return $next();  // continue to the next middleware / handler
    }

    private function isRateLimited($ip)
    {
        // ... your rate limiting logic
    }
}
```

### 2. Declare it in module.json

```json
{
    "id": "my-module",
    "middleware": {
        "rate-limit": "RateLimitMiddleware"
    }
}
```

### 3. Load middleware in init()

```php
class MyModuleModule extends \Module\Module
{
    public function init(): void
    {
        $this->registerMiddleware();  // loads from module.json + middleware/ dir

        // Apply globally to all /api/* routes
        $this->middleware('/api/*', 'rate-limit');
    }
}
```

### 4. Or attach to specific routes

```php
$this->hook('routes.register', function ($data) {
    $router = $data['router'];

    $router->get('/api/feed', [$this, 'feed'])
           ->middleware('rate-limit');

    return $data;
});
```

That's it. The middleware is automatically loaded from the file, registered by name, and resolved when the route is dispatched.

---

## MiddlewareInterface

```
core/classes/Http/MiddlewareInterface.php
```

The contract every class-based middleware must implement:

```php
namespace Http;

interface MiddlewareInterface
{
    /**
     * @param callable $next  Invoke to continue the pipeline
     * @return bool           true if completed, false if halted
     */
    public function handle($next);
}
```

**Rules:**

- Call `$next()` to pass control to the next middleware (or the route handler if this is the last one)
- Return `false` to halt the pipeline — the route handler will not run
- Return `$next()` (or `true`) to signal successful completion
- The current request is always available via `app()->request()` (not passed as argument, consistent with all other Mantra CMS code)

### Minimal middleware

```php
class LoggingMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        logger()->info('Request', ['path' => app()->request()->path()]);
        return $next();
    }
}
```

### Guard middleware (halt on failure)

```php
class ApiKeyMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        $key = app()->request()->header('X-API-Key');

        if ($key !== config('api.key')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid API key']);
            return false;
        }

        return $next();
    }
}
```

### Before + after middleware (wrapping)

The `$next` pattern allows running code both before and after the handler:

```php
class TimingMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        $start = microtime(true);

        $result = $next();  // run the rest of the pipeline + handler

        $elapsed = microtime(true) - $start;
        logger()->debug('Request timing', [
            'path' => app()->request()->path(),
            'ms'   => round($elapsed * 1000, 2),
        ]);

        return $result;
    }
}
```

---

## MiddlewarePipeline

```
core/classes/Http/MiddlewarePipeline.php
```

The execution engine. You rarely use this directly — the Router builds and runs the pipeline automatically. But if you need manual control:

```php
$pipeline = new \Http\MiddlewarePipeline();
$pipeline->pipe(new AuthMiddleware());
$pipeline->pipe(new CsrfMiddleware());

$halted = !$pipeline->run(function () {
    echo 'Handler executed';
});
```

| Method | Description |
|--------|-------------|
| `pipe($middleware)` | Add a `MiddlewareInterface` instance or callable. Returns `$this`. |
| `run($core)` | Execute the pipeline. Returns `true` if `$core` was reached, `false` if halted. |

**Execution order:** layers are piped in order, first-piped runs first. The pipeline is built inside-out: the core handler is the innermost callable, each `pipe()` wraps around it.

**Backward compatibility:** plain callables (closures, method arrays) are supported alongside class-based middleware. A callable is invoked without arguments; if it returns `false`, the pipeline halts. If it returns `true` or `null`, the next layer runs.

```php
$pipeline->pipe(new AuthMiddleware());       // class-based
$pipeline->pipe(function () {                // legacy callable
    return someCheck() ? true : false;
});
```

---

## MiddlewareRegistry

```
core/classes/Http/MiddlewareRegistry.php
```

Central storage for named middleware and groups. Accessible as a service:

```php
$registry = app()->middleware();
```

### API Reference

| Method | Description |
|--------|-------------|
| `register($name, $middleware)` | Register a named middleware (instance or callable) |
| `group($name, $middlewareNames)` | Register a named group (array of middleware names) |
| `resolve($name)` | Resolve a name to array of middleware. Groups are expanded recursively. Throws on unknown names or group cycles. |
| `resolveAll($items)` | Resolve a mixed list (strings, instances, callables) to a flat array |
| `has($name)` | Check if a middleware or group is registered |

**Fail-closed resolution.** `resolve()` and `resolveAll()` throw exceptions rather than silently skipping unresolvable names — this prevents fail-open security bugs where a typo in a middleware reference would drop authentication or CSRF protection from a route:

- `\Http\UnknownMiddlewareException` — name is not registered
- `\Http\CircularMiddlewareGroupException` — group references form a cycle (direct `a → a`, indirect `a → b → a`, or deeper)

Use `has($name)` first if you need to check existence without catching exceptions.

### Named middleware

```php
// Register
app()->middleware()->register('auth', new AuthMiddleware());
app()->middleware()->register('csrf', new CsrfMiddleware());

// Use by name in routes
$router->get('/admin/dashboard', $handler)->middleware('auth');

// Use by name in global middleware
$router->addGlobalMiddleware('/admin/*', 'csrf', 5);
```

### Groups

A group maps a single name to an ordered list of middleware names:

```php
app()->middleware()->group('admin', ['csrf', 'auth']);

// Now 'admin' expands to CsrfMiddleware + AuthMiddleware
$router->get('/admin/settings', $handler)->middleware('admin');
```

Groups can reference other groups (resolved recursively):

```php
app()->middleware()->group('base', ['logging', 'timing']);
app()->middleware()->group('api', ['base', 'api-key', 'rate-limit']);
// 'api' expands to: logging, timing, api-key, rate-limit
```

### Resolving middleware

The registry resolves strings to middleware instances. Non-string values pass through as-is:

```php
$resolved = app()->middleware()->resolveAll([
    'auth',                          // string — resolved from registry
    new RateLimitMiddleware(),       // instance — used as-is
    function () { return true; },    // callable — used as-is
]);
// Result: [AuthMiddleware, RateLimitMiddleware, Closure]
```

---

## Router Integration

### Global middleware

Runs on every request matching a URI pattern. Sorted by priority (lower = runs first).

```php
$router->addGlobalMiddleware($pattern, $middleware, $priority);
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$pattern` | `string` | `'*'` (all), `'/admin/*'` (prefix), `'/login'` (exact) |
| `$middleware` | `string\|callable\|MiddlewareInterface` | Middleware name, instance, or callable |
| `$priority` | `int` | Lower runs first (default: 10) |

```php
// By name (registered in the MiddlewareRegistry)
$router->addGlobalMiddleware('/admin/*', 'csrf', 5);

// Inline callable (backward compatible)
$router->addGlobalMiddleware('*', function () {
    header('X-Powered-By: Mantra CMS');
    return true;
});

// Instance
$router->addGlobalMiddleware('/api/*', new RateLimitMiddleware(), 1);
```

### Per-route middleware

Attached to a specific route. Runs after global middleware, in the order attached.

```php
$router->get($pattern, $handler)->middleware($middleware);
```

The `middleware()` method accepts:

| Type | Example |
|------|---------|
| String (name) | `->middleware('auth')` |
| Instance | `->middleware(new AuthMiddleware())` |
| Callable | `->middleware([$this, 'checkRole'])` |
| Array of any | `->middleware(['csrf', 'auth'])` |

Chaining is supported:

```php
$router->get('/admin/settings', $handler)
       ->middleware('auth')
       ->middleware('admin-only');
```

Or as array:

```php
$router->get('/admin/settings', $handler)
       ->middleware(['auth', 'admin-only']);
```

### Execution order

For each matched route, the Router builds a single MiddlewarePipeline:

```
1. Global middleware (sorted by priority, filtered by URI pattern)
2. Route middleware (in order of attachment)
3. Route handler (the core callback)
```

If any middleware returns `false`, the pipeline stops and the route handler is skipped.

---

## Module Integration

### Declaring middleware in module.json

Add a `middleware` key to your module manifest. Each entry maps a registry name to a class filename (without `.php`) in the `middleware/` directory:

```json
{
    "id": "my-module",
    "name": "My Module",
    "version": "1.0.0",
    "type": "feature",
    "capabilities": ["middleware", "routes"],
    "middleware": {
        "rate-limit": "RateLimitMiddleware",
        "api-key":    "ApiKeyMiddleware"
    }
}
```

File structure:

```
modules/my-module/
├── MyModuleModule.php
├── module.json
└── middleware/
    ├── RateLimitMiddleware.php
    └── ApiKeyMiddleware.php
```

### Loading middleware

Call `$this->registerMiddleware()` in your module's `init()`:

```php
class MyModuleModule extends \Module\Module
{
    public function init(): void
    {
        // Load classes from middleware/ and register in the global registry
        $this->registerMiddleware();

        // Optionally define groups
        app()->middleware()->group('api', ['rate-limit', 'api-key']);

        // Register routes, hooks, etc.
        $this->hook('routes.register', [$this, 'registerRoutes']);
    }
}
```

`registerMiddleware()` does the following for each entry in `manifest['middleware']`:

1. Requires `modules/{id}/middleware/{ClassName}.php`
2. Instantiates the class
3. Registers the instance in `app()->middleware()` under the given name

### Applying middleware to routes

**Globally (URI pattern):**

```php
// In init() — deferred until the router is ready
$this->middleware('/api/*', 'rate-limit');
$this->middleware('/api/*', 'api-key', 15);
```

**Per-route (in routes.register hook):**

```php
public function registerRoutes($data)
{
    $router = $data['router'];

    $router->get('/api/posts', [$this, 'listPosts'])
           ->middleware('api-key');

    $router->post('/api/posts', [$this, 'createPost'])
           ->middleware(['api-key', 'rate-limit']);

    return $data;
}
```

### Module helpers

| Method | Description |
|--------|-------------|
| `$this->registerMiddleware()` | Load middleware classes from `module.json` manifest |
| `$this->middleware($pattern, $callback, $priority)` | Register global middleware (deferred) |

---

## Built-in Middleware

### AuthMiddleware

```
modules/admin/middleware/AuthMiddleware.php
```

Registered as `'auth'` by the admin module.

Checks `app()->auth()->check()`. Redirects unauthenticated users to `/admin/login`. Used as per-route middleware on all admin routes via `adminRoute()`.

```php
// How the admin module uses it:
$router->get('/admin/pages', [$panel, 'listItems'])->middleware('auth');
```

### CsrfMiddleware

```
modules/admin/middleware/CsrfMiddleware.php
```

Registered as `'csrf'` by the admin module.

Verifies CSRF tokens on POST requests. Checks both `csrf_token` POST field and `X-CSRF-Token` header. Non-POST requests pass through. Returns 403 (JSON or plain text) on failure. Applied globally to `/admin/*` at priority 5.

```php
// How the admin module uses it:
$router->addGlobalMiddleware('/admin/*', 'csrf', 5);
```

### Admin group

The admin module registers a group combining both:

```php
app()->middleware()->group('admin', ['csrf', 'auth']);

// Apply the full admin stack to a route:
$router->get('/admin/custom', $handler)->middleware('admin');
```

---

## Common Patterns

### Role-based access

```php
class RequireRoleMiddleware implements \Http\MiddlewareInterface
{
    private $role;

    public function __construct($role)
    {
        $this->role = $role;
    }

    public function handle($next)
    {
        if (!app()->auth()->hasRole($this->role)) {
            http_response_code(403);
            echo 'Access denied';
            return false;
        }

        return $next();
    }
}
```

Register specific instances:

```php
app()->middleware()->register('require-admin', new RequireRoleMiddleware('admin'));
app()->middleware()->register('require-editor', new RequireRoleMiddleware('editor'));
```

### Maintenance mode

```php
class MaintenanceMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        if (config('site.maintenance', false)) {
            // Let admin routes through
            if (str_starts_with(app()->request()->path(), '/admin')) {
                return $next();
            }

            http_response_code(503);
            echo '<h1>Site is under maintenance</h1>';
            return false;
        }

        return $next();
    }
}
```

Apply globally:

```php
$this->middleware('*', 'maintenance', 1);  // priority 1 = runs first
```

### Security headers

```php
class SecurityHeadersMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        $result = $next();

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        return $result;
    }
}
```

### JSON API wrapper

```php
class JsonApiMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        // Verify Accept header
        if (!app()->request()->acceptsJson()) {
            http_response_code(406);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'JSON required']);
            return false;
        }

        // Set content type for all responses in this pipeline
        header('Content-Type: application/json');

        return $next();
    }
}
```

### Composing middleware in admin panels

Admin panels use `adminRoute()` which automatically applies the `'auth'` middleware. You can add extra middleware for specific panel routes:

```php
class MyPanel extends \Admin\AdminPanel
{
    public function registerRoutes($admin): void
    {
        // Standard admin route (auth middleware auto-applied)
        $admin->adminRoute('GET', 'my-panel', [$this, 'index']);

        // Route with extra middleware
        $admin->adminRoute('POST', 'my-panel/import', [$this, 'import'])
              ->middleware('rate-limit');
    }
}
```

---

## Lifecycle

### Initialization order

```
Application::__construct()
  └── registerCoreServices()
        └── MiddlewareRegistry registered as lazy service

Application::run()
  └── ModuleManager::loadModules()
        └── Module::init()
              ├── registerMiddleware()     ← classes loaded, instances registered
              └── middleware(pattern, ..)  ← deferred global middleware
  └── HookManager::fire('routes.register')
        ├── Module deferred middleware    ← addGlobalMiddleware() called
        └── Module route registration    ← per-route middleware attached
  └── Router::dispatch()
        └── MiddlewarePipeline::run()    ← pipeline built and executed
```

### Request flow

```
Router::dispatch()
  |
  +-- Match route by method + URI pattern
  |
  +-- Build MiddlewarePipeline
  |     +-- Add matching global middleware (by URI pattern, sorted by priority)
  |     +-- Add route middleware (in order of attachment)
  |
  +-- MiddlewarePipeline::run($routeHandler)
  |     +-- Layer 1: CsrfMiddleware::handle($next)
  |     |     +-- POST? verify token; GET? call $next()
  |     +-- Layer 2: AuthMiddleware::handle($next)
  |     |     +-- Logged in? call $next(); else redirect, return false
  |     +-- Core: route handler executes
  |
  +-- Return (or halt if middleware returned false)
```

---

## Backward Compatibility

### Old-style callables still work

Pre-existing closures and method references that return `true`/`false` continue to work without changes. The pipeline wraps them automatically:

```php
// Still valid — backward compatible
$router->addGlobalMiddleware('*', function () {
    if (banned()) {
        return false;  // halt
    }
    return true;  // continue
});

$router->get('/secret', $handler)
       ->middleware([$this, 'checkAccess']);
```

Old-style callables are invoked without arguments (no `$next`). If they return `false`, the pipeline halts. If they return `true` or `null`, the next layer runs.

### Migration path

To convert an old-style callable to a class-based middleware:

**Before:**

```php
$router->addGlobalMiddleware('/api/*', function () {
    if (!validApiKey()) {
        http_response_code(401);
        echo 'Unauthorized';
        return false;
    }
    return true;
});
```

**After:**

```php
// modules/my-module/middleware/ApiKeyMiddleware.php
class ApiKeyMiddleware implements \Http\MiddlewareInterface
{
    public function handle($next)
    {
        if (!validApiKey()) {
            http_response_code(401);
            echo 'Unauthorized';
            return false;
        }
        return $next();
    }
}
```

```json
// module.json
{ "middleware": { "api-key": "ApiKeyMiddleware" } }
```

```php
// Module init()
$this->registerMiddleware();
$this->middleware('/api/*', 'api-key');
```

Key difference: replace `return true;` with `return $next();`. This gives the middleware the ability to run code after the handler (wrapping), not just before (guarding).

---

## Debugging

```php
// Check if a middleware is registered:
app()->middleware()->has('auth');       // true
app()->middleware()->has('admin');      // true (group)
app()->middleware()->has('unknown');    // false

// Resolve a name to see what it expands to:
app()->middleware()->resolve('admin');
// [CsrfMiddleware instance, AuthMiddleware instance]

// Unknown names throw UnknownMiddlewareException (fail-closed):
try {
    app()->middleware()->resolve('typo');
} catch (\Http\UnknownMiddlewareException $e) {
    // Message: 'Middleware "typo" is not registered'
}
```
