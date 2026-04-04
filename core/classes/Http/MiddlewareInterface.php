<?php declare(strict_types=1);
/**
 * MiddlewareInterface - Contract for class-based middleware
 *
 * Middleware inspects or guards the request before (and optionally after)
 * the route handler runs.  Call $next() to pass control down the pipeline;
 * return false to halt it.
 *
 * The current request is always available via app()->request().
 */

namespace Http;

interface MiddlewareInterface
{
    /**
     * Handle the request.
     *
     * @param callable $next Invoke to continue the pipeline
     * @return bool true if the pipeline completed, false if halted
     */
    public function handle($next);
}
