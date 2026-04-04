<?php declare(strict_types=1);
/**
 * MiddlewarePipeline - Chains middleware layers around a core handler
 *
 * Supports two kinds of layers:
 *   - MiddlewareInterface instances — receive a $next callable
 *   - Plain callables (backward compat) — called without arguments;
 *     return false to halt, true/null to continue
 *
 * The pipeline is built inside-out: the last-piped layer runs closest
 * to the core handler, the first-piped layer runs first overall.
 */

namespace Http;

class MiddlewarePipeline
{
    /** @var array Ordered list of middleware layers */
    private $layers = [];

    /**
     * Add a middleware layer to the pipeline.
     *
     * @param MiddlewareInterface|callable $middleware
     * @return self
     */
    public function pipe($middleware)
    {
        $this->layers[] = $middleware;
        return $this;
    }

    /**
     * Execute the pipeline, then run $core if all middleware pass.
     *
     * @param callable $core The final handler (route callback)
     * @return bool true if $core was reached, false if halted
     */
    public function run($core)
    {
        // Build the chain inside-out: start with the core handler,
        // then wrap each layer around it in reverse order.
        $chain = static function () use ($core) {
            $core();
            return true;
        };

        foreach (array_reverse($this->layers) as $layer) {
            $chain = $this->wrapLayer($layer, $chain);
        }

        return $chain();
    }

    /**
     * Wrap a single middleware layer around the next callable.
     *
     * @param MiddlewareInterface|callable $layer
     * @param callable $next
     * @return callable
     */
    private function wrapLayer($layer, $next)
    {
        if ($layer instanceof MiddlewareInterface) {
            return static function () use ($layer, $next) {
                return $layer->handle($next);
            };
        }

        // Backward-compatible callable (guard pattern):
        // call without arguments, halt on false, continue otherwise.
        return static function () use ($layer, $next) {
            if ($layer() === false) {
                return false;
            }
            return $next();
        };
    }
}
