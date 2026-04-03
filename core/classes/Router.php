<?php declare(strict_types=1);
/**
 * Router - Simple but powerful routing system
 * Supports dynamic routes, parameters, and middleware
 */

class Router {
    private $routes = [];
    private $globalMiddleware = [];
    private $currentRoute = null;

    /**
     * Add GET route
     */
    public function get($pattern, $callback) {
        $this->addRoute('GET', $pattern, $callback);
        return $this;
    }

    /**
     * Add POST route
     */
    public function post($pattern, $callback) {
        $this->addRoute('POST', $pattern, $callback);
        return $this;
    }

    /**
     * Add route for any method
     */
    public function any($pattern, $callback) {
        $this->addRoute('ANY', $pattern, $callback);
        return $this;
    }

    /**
     * Add route
     */
    private function addRoute($method, $pattern, $callback): void {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'callback' => $callback,
            'middleware' => [],
        ];
    }

    /**
     * Add middleware to last route
     */
    public function middleware($middleware) {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['middleware'][] = $middleware;
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
     * @param string   $pattern  URI pattern (* suffix for prefix match)
     * @param callable $callback Middleware callable; return false to halt
     * @param int      $priority Lower = runs first (default 10)
     */
    public function addGlobalMiddleware($pattern, $callback, $priority = 10): void {
        $this->globalMiddleware[] = [
            'pattern' => $pattern,
            'callback' => $callback,
            'priority' => $priority,
        ];

        usort($this->globalMiddleware, fn ($a, $b) => $a['priority'] - $b['priority']);
    }

    /**
     * Dispatch current request
     */
    public function dispatch(): void {
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

            // Run global middleware that matches this URI
            foreach ($this->globalMiddleware as $mw) {
                if ($this->middlewareMatches($mw['pattern'], $uri) && is_callable($mw['callback'])) {
                    if (call_user_func($mw['callback']) === false) {
                        return;
                    }
                }
            }

            // Run route-specific middleware
            foreach ($route['middleware'] as $mw) {
                if (is_callable($mw)) {
                    $result = call_user_func($mw);
                    if ($result === false) {
                        return; // Middleware stopped execution
                    }
                }
            }

            // Execute callback
            // NOTE: $params is an associative array (named params). Passing it to call_user_func_array()
            // would be treated as PHP 8 named arguments and can break handlers expecting a single $params array.
            if (is_callable($route['callback'])) {
                if (empty($params)) {
                    call_user_func($route['callback']);
                } else {
                    call_user_func($route['callback'], $params);
                }
            } elseif (is_string($route['callback'])) {
                // Format: "ModuleName:method"
                $this->executeControllerAction($route['callback'], $params);
            }

            return;
        }

        // No route found - 404
        $this->notFound();
    }

    /**
     * Get clean URI
     */
    private function getUri() {
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
    private function matchPattern($pattern, $uri) {
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
    private function executeControllerAction($action, $params): void {
        $parts = explode(':', $action);
        if (count($parts) !== 2) {
            throw new Exception('Invalid controller action format');
        }

        [$moduleName, $method] = $parts;

        $app = Application::getInstance();
        $module = $app->modules()->getModule($moduleName);

        if ($module && method_exists($module, $method)) {
            if (empty($params)) {
                call_user_func([$module, $method]);
            } else {
                call_user_func([$module, $method], $params);
            }
        } else {
            throw new Exception('Controller action not found: ' . $action);
        }
    }

    /**
     * Check if a middleware pattern matches the given URI.
     */
    private function middlewareMatches($pattern, $uri) {
        if ($pattern === '*') {
            return true;
        }

        // Prefix match: "/admin/*" matches "/admin" and "/admin/pages"
        if (substr($pattern, -2) === '/*') {
            $prefix = substr($pattern, 0, -2);
            return $uri === $prefix || str_starts_with($uri, $prefix . '/')  ;
        }

        // Exact match
        return $uri === $pattern;
    }

    /**
     * 404 handler
     */
    private function notFound(): void {
        abort(404);
    }

    /**
     * Redirect helper
     */
    public function redirect($url, $code = 302): void {
        app()->response()->redirect($url, $code);
    }
}
