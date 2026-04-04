<?php declare(strict_types=1);
/**
 * MiddlewareRegistry - Named middleware storage and groups
 *
 * Modules register middleware by name so that routes and other modules
 * can reference them as simple strings instead of concrete instances.
 *
 * Groups map a single name to an ordered list of middleware names,
 * allowing common stacks (e.g. 'admin' => ['csrf', 'auth']) to be
 * applied in one call.
 */

namespace Http;

class MiddlewareRegistry
{
    /** @var array<string, MiddlewareInterface|callable> name => middleware */
    private $middleware = [];

    /** @var array<string, string[]> name => list of middleware names */
    private $groups = [];

    /**
     * Register a named middleware.
     *
     * @param string $name Unique name (e.g. 'auth', 'csrf')
     * @param MiddlewareInterface|callable $middleware Instance or callable
     */
    public function register($name, $middleware)
    {
        $this->middleware[$name] = $middleware;
    }

    /**
     * Register a middleware group.
     *
     * @param string $name Group name (e.g. 'admin')
     * @param array $middlewareNames Ordered list of middleware names
     */
    public function group($name, $middlewareNames)
    {
        $this->groups[$name] = $middlewareNames;
    }

    /**
     * Resolve a single name to an array of middleware instances/callables.
     *
     * If the name matches a group, the group's entries are resolved
     * recursively. Otherwise the individually registered middleware
     * is returned in a single-element array.
     *
     * @param string $name Middleware or group name
     * @return array Flat array of MiddlewareInterface|callable
     */
    public function resolve($name)
    {
        // Check groups first (a group can reference other groups)
        if (isset($this->groups[$name])) {
            $result = [];
            foreach ($this->groups[$name] as $entry) {
                $result = array_merge($result, $this->resolve($entry));
            }
            return $result;
        }

        if (isset($this->middleware[$name])) {
            return [$this->middleware[$name]];
        }

        logger()->warning('Middleware not found in registry', ['name' => $name]);
        return [];
    }

    /**
     * Resolve a mixed list of middleware references.
     *
     * Each item can be:
     *   - string         — resolved via the registry (name or group)
     *   - MiddlewareInterface — used as-is
     *   - callable        — used as-is (backward compat guard)
     *
     * @param array $items Mixed list of middleware references
     * @return array Flat array of MiddlewareInterface|callable
     */
    public function resolveAll($items)
    {
        $result = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $result = array_merge($result, $this->resolve($item));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Check if a middleware or group is registered.
     *
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return isset($this->middleware[$name]) || isset($this->groups[$name]);
    }
}
