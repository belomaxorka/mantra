<?php declare(strict_types=1);

/**
 * Router - Simple but powerful routing system
 * Supports dynamic routes, parameters, and middleware
 */
class Router
{
    private $routes = [];
    private $globalMiddleware = [];
    private $currentRoute = null;

    /**
     * Add GET route
     */
    public function get($pattern, $callback)
    {
        $this->addRoute('GET', $pattern, $callback);
        return $this;
    }

    /**
     * Add POST route
     */
    public function post($pattern, $callback)
    {
        $this->addRoute('POST', $pattern, $callback);
        return $this;
    }

    /**
     * Add route for any method
     */
    public function any($pattern, $callback)
    {
        $this->addRoute('ANY', $pattern, $callback);
        return $this;
    }

    /**
     * Add route
     */
    private function addRoute($method, $pattern, $callback): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'callback' => $callback,
            'middleware' => [],
        ];
    }

    /**
     * Add middleware to last route.
     *
     * Accepts a callable, a MiddlewareInterface instance, a string
     * (middleware name from the registry), or an array of any of these.
     *
     * @param string|callable|\Http\MiddlewareInterface|array $middleware
     * @return self
     */
    public function middleware($middleware)
    {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            if (is_array($middleware) && !is_callable($middleware)) {
                foreach ($middleware as $mw) {
                    $this->routes[$lastIndex]['middleware'][] = $mw;
                }
            } else {
                $this->routes[$lastIndex]['middleware'][] = $middleware;
            }
        }
        return $this;
    }

    /**
     * Register global middleware that runs on matching URI patterns.
     *
     * Patterns:
     *   '*'          — every request
     *   '/admin/*'   — URIs starting with /admin/
     *   '/api/*'     — URIs starting with /api/
     *   '/login'     — exact match
     *
     * @param string $pattern URI pattern (* suffix for prefix match)
     * @param string|callable|\Http\MiddlewareInterface $callback Middleware
     * @param int $priority Lower = runs first (default 10)
     */
    public function addGlobalMiddleware($pattern, $callback, $priority = 10): void
    {
        $this->globalMiddleware[] = [
            'pattern' => $pattern,
            'callback' => $callback,
            'priority' => $priority,
        ];

        usort($this->globalMiddleware, fn($a, $b) => $a['priority'] - $b['priority']);
    }

    /**
     * Dispatch current request
     */
    public function dispatch(): void
    {
        $method = app()->request()->method();
        $uri = $this->getUri();

        foreach ($this->routes as $route) {
            // Check method
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            // Check pattern
            $params = $this->matchPattern($route['pattern'], $uri);
            if ($params === false) {
                continue;
            }

            // Route matched
            $this->currentRoute = $route;

            $pipeline = $this->buildPipeline($uri, $route['middleware']);
            $pipeline->run($this->buildRouteHandler($route['callback'], $params));

            return;
        }

        // No route matched — still run matching global middleware so that
        // cross-cutting concerns (rate limiting, IP blocking, audit logging,
        // security headers) apply to 404 responses too. Without this, an
        // attacker flooding non-existent URLs would bypass rate-limit
        // middleware entirely.
        $pipeline = $this->buildPipeline($uri, []);
        $pipeline->run(fn () => $this->notFound());
    }

    /**
     * Build a MiddlewarePipeline for the given URI.
     *
     * Collects all global middleware whose pattern matches $uri, then
     * appends any route-specific middleware. Order matters: global layers
     * are outermost, route layers are closest to the core handler.
     *
     * @param string $uri The normalized request URI
     * @param array  $routeMiddleware Route-specific middleware references
     * @return \Http\MiddlewarePipeline
     */
    private function buildPipeline(string $uri, array $routeMiddleware): \Http\MiddlewarePipeline
    {
        $pipeline = new \Http\MiddlewarePipeline();
        $registry = app()->middleware();

        // Collect matching global middleware
        foreach ($this->globalMiddleware as $mw) {
            if ($this->middlewareMatches($mw['pattern'], $uri)) {
                $resolved = $registry->resolveAll([$mw['callback']]);
                foreach ($resolved as $layer) {
                    $pipeline->pipe($layer);
                }
            }
        }

        // Collect route-specific middleware
        $resolved = $registry->resolveAll($routeMiddleware);
        foreach ($resolved as $layer) {
            $pipeline->pipe($layer);
        }

        return $pipeline;
    }

    /**
     * Build a callable that executes the route handler.
     *
     * @param callable|string $callback Route callback or "Module:method" string
     * @param array $params Matched route parameters
     * @return callable
     */
    private function buildRouteHandler($callback, $params)
    {
        return function () use ($callback, $params): void {
            if (is_callable($callback)) {
                if (empty($params)) {
                    $callback();
                } else {
                    $callback($params);
                }
            } elseif (is_string($callback)) {
                $this->executeControllerAction($callback, $params);
            }
        };
    }

    /**
     * Get clean URI
     */
    private function getUri()
    {
        $uri = app()->request()->uri();

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path if in subdirectory
        $scriptName = dirname((string)app()->request()->server('SCRIPT_NAME', ''));
        $scriptName = Config::normalizeScriptPath($scriptName);
        if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Match pattern against URI
     */
    private function matchPattern($pattern, $uri)
    {
        // Convert pattern to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[a-zA-Z0-9_-]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            // Extract named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return false;
    }

    /**
     * Execute controller action
     */
    private function executeControllerAction($action, $params): void
    {
        $parts = explode(':', $action);
        if (count($parts) !== 2) {
            throw new Exception('Invalid controller action format');
        }

        [$moduleName, $method] = $parts;

        $app = Application::getInstance();
        $module = $app->modules()->getModule($moduleName);

        if ($module && method_exists($module, $method)) {
            if (empty($params)) {
                $module->$method();
            } else {
                $module->$method($params);
            }
        } else {
            throw new Exception('Controller action not found: ' . $action);
        }
    }

    /**
     * Check if a middleware pattern matches the given URI.
     */
    private function middlewareMatches($pattern, $uri)
    {
        if ($pattern === '*') {
            return true;
        }

        // Prefix match: "/admin/*" matches "/admin" and "/admin/pages"
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -2);
            return $uri === $prefix || str_starts_with($uri, $prefix . '/');
        }

        // Exact match
        return $uri === $pattern;
    }

    /**
     * 404 handler
     */
    private function notFound(): void
    {
        abort(404);
    }

    /**
     * Redirect helper
     */
    public function redirect($url, $code = 302): void
    {
        app()->response()->redirect($url, $code);
    }
}
