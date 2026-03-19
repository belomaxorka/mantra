<?php
/**
 * Router - Simple but powerful routing system
 * Supports dynamic routes, parameters, and middleware
 */

class Router {
    private $routes = array();
    private $middleware = array();
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
    private function addRoute($method, $pattern, $callback) {
        $this->routes[] = array(
            'method' => $method,
            'pattern' => $pattern,
            'callback' => $callback,
            'middleware' => array()
        );
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
     * Dispatch current request
     */
    public function dispatch() {
        $method = request()->method();
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
            
            // Run middleware
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
        $uri = request()->uri();
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remove base path if in subdirectory
        $scriptName = dirname((string)request()->server('SCRIPT_NAME', ''));
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        
        return '/' . trim($uri, '/');
    }
    
    /**
     * Match pattern against URI
     */
    private function matchPattern($pattern, $uri) {
        // Convert pattern to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            // Extract named parameters
            $params = array();
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
    private function executeControllerAction($action, $params) {
        $parts = explode(':', $action);
        if (count($parts) !== 2) {
            throw new Exception('Invalid controller action format');
        }
        
        list($moduleName, $method) = $parts;
        
        $app = Application::getInstance();
        $module = $app->modules()->getModule($moduleName);
        
        if ($module && method_exists($module, $method)) {
            if (empty($params)) {
                call_user_func(array($module, $method));
            } else {
                call_user_func(array($module, $method), $params);
            }
        } else {
            throw new Exception('Controller action not found: ' . $action);
        }
    }
    
    /**
     * 404 handler
     */
    private function notFound() {
        not_found('public');
    }
    
    /**
     * Redirect helper
     */
    public function redirect($url, $code = 302) {
        redirect($url, $code);
    }
}
