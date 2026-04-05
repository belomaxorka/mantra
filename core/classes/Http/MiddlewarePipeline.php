<?php declare(strict_types=1);
/**
 * MiddlewarePipeline - Chains middleware layers around a core handler
 *
 * Supports two kinds of layers:
 *   - MiddlewareInterface instances — receive a callable $next and can
 *     wrap logic around it (PSR-15-style: before/$next/after).
 *   - Plain callable guards (backward compat) — invoked WITHOUT arguments;
 *     return false to halt, true/null to continue. Guards cannot run code
 *     after $next — use MiddlewareInterface when wrap semantics are needed.
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
     * Callable layers are validated at pipe time: they must not declare any
     * parameters, because the pipeline invokes them without arguments.
     * Writing `function ($next) { ... }` expecting PSR-15-style semantics
     * is a contract mismatch — implement MiddlewareInterface instead.
     *
     * @param MiddlewareInterface|callable $middleware
     * @return self
     * @throws \InvalidArgumentException If $middleware is neither a
     *   MiddlewareInterface nor a zero-argument callable.
     */
    public function pipe($middleware)
    {
        if (!$middleware instanceof MiddlewareInterface) {
            if (!is_callable($middleware)) {
                throw new \InvalidArgumentException(
                    'Middleware must implement ' . MiddlewareInterface::class
                    . ' or be a callable guard',
                );
            }
            $this->assertGuardCallable($middleware);
        }

        $this->layers[] = $middleware;
        return $this;
    }

    /**
     * Assert that a legacy callable layer matches the guard contract:
     * it must be invokable with zero arguments.
     */
    private function assertGuardCallable(callable $callable): void
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($callable));
        if ($reflection->getNumberOfParameters() > 0) {
            throw new \InvalidArgumentException(
                'Callable middleware must take no parameters — the pipeline '
                . 'invokes legacy callables without arguments. To wrap logic '
                . 'around $next, implement ' . MiddlewareInterface::class . '.',
            );
        }
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
            return static fn () => $layer->handle($next);
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
