<?php declare(strict_types=1);
/**
 * MiddlewareInterface - Contract for class-based middleware
 *
 * Middleware inspects or guards the request before (and optionally after)
 * the route handler runs. Call $next() to pass control down the pipeline;
 * return false to halt it. Code written after $next() returns runs during
 * the unwind phase, which is guaranteed to execute even if inner layers halt.
 *
 * The current request is always available via app()->request().
 */

namespace Http;

interface MiddlewareInterface
{
    /**
     * Handle the request.
     *
     * @param callable $next Invoke to continue the pipeline (returns bool)
     * @return bool true if the pipeline reached the core handler, false if halted
     */
    public function handle(callable $next): bool;
}
