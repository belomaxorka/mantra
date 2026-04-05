<?php declare(strict_types=1);
/**
 * CircularMiddlewareGroupException - Thrown when group resolution hits a cycle.
 *
 * Groups may reference other groups, but the reference graph must be acyclic.
 * MiddlewareRegistry tracks the resolution chain and throws this exception if
 * a group (directly or indirectly) references itself — which would otherwise
 * cause infinite recursion and a stack overflow.
 */

namespace Http;

class CircularMiddlewareGroupException extends \RuntimeException
{
    /**
     * @param string[] $chain Ordered list of group names that form the cycle
     *                        (e.g. ['a', 'b', 'a'] for a → b → a).
     */
    public function __construct(array $chain, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Circular middleware group reference: %s', implode(' -> ', $chain)),
            0,
            $previous,
        );
    }
}
