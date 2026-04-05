<?php declare(strict_types=1);
/**
 * UnknownMiddlewareException - Thrown when a middleware name cannot be resolved.
 *
 * MiddlewareRegistry fails closed when a route or group references a name
 * that has not been registered. This prevents fail-open security bugs where
 * a typo in a middleware name (e.g. 'authn' instead of 'auth') would silently
 * drop authentication from a protected route.
 */

namespace Http;

class UnknownMiddlewareException extends \RuntimeException
{
    public function __construct(string $name, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Middleware "%s" is not registered', $name),
            0,
            $previous,
        );
    }
}
