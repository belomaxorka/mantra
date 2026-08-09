<?php declare(strict_types=1);

namespace Http;

/** Route-level authorization for permissions that do not require ownership. */
final class PermissionMiddleware implements MiddlewareInterface
{
    private string $permission;
    private $onDenied;

    public function __construct(string $permission, ?callable $onDenied = null)
    {
        $this->permission = $permission;
        $this->onDenied = $onDenied;
    }

    public function handle(callable $next): bool
    {
        $authorization = app()->service('authorization');
        $access = $authorization instanceof \Authorization
            ? $authorization->check(app()->auth()->user(), $this->permission)
            : false;

        // The "own" result needs a loaded resource, so it is intentionally
        // denied here and remains an action-level decision in ContentPanel.
        if ($access !== true) {
            http_response_code(403);
            if (is_callable($this->onDenied)) {
                ($this->onDenied)($this->permission);
            }
            return false;
        }

        return $next();
    }
}
