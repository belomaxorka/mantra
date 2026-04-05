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
    public function register($name, $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    /**
     * Register a middleware group.
     *
     * @param string $name Group name (e.g. 'admin')
     * @param array $middlewareNames Ordered list of middleware names
     */
    public function group($name, $middlewareNames): void
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
     * Fails closed: an unknown name throws UnknownMiddlewareException rather
     * than silently returning an empty list, so a typo in a route's
     * middleware reference cannot drop authentication or CSRF protection.
     * Circular group references throw CircularMiddlewareGroupException.
     *
     * @param string $name Middleware or group name
     * @return array Flat array of MiddlewareInterface|callable
     * @throws UnknownMiddlewareException When the name is not registered
     * @throws CircularMiddlewareGroupException When group references form a cycle
     */
    public function resolve($name)
    {
        return $this->resolveWithChain($name, []);
    }

    /**
     * Internal resolver that tracks the group chain for cycle detection.
     *
     * @param string   $name  Middleware or group name to resolve
     * @param string[] $chain Ordered list of group names already being resolved
     * @return array Flat array of MiddlewareInterface|callable
     */
    private function resolveWithChain(string $name, array $chain): array
    {
        // Check groups first (a group can reference other groups)
        if (isset($this->groups[$name])) {
            if (in_array($name, $chain, true)) {
                throw new CircularMiddlewareGroupException([...$chain, $name]);
            }

            $chain[] = $name;
            $result = [];
            foreach ($this->groups[$name] as $entry) {
                $result = array_merge($result, $this->resolveWithChain($entry, $chain));
            }
            return $result;
        }

        if (isset($this->middleware[$name])) {
            return [$this->middleware[$name]];
        }

        throw new UnknownMiddlewareException($name);
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
