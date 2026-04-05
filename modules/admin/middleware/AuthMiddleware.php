<?php declare(strict_types=1);
/**
 * AuthMiddleware - Require authentication for admin routes
 *
 * Redirects unauthenticated users to the admin login page.
 */

class AuthMiddleware implements \Http\MiddlewareInterface
{
    public function handle(callable $next): bool
    {
        if (!app()->auth()->check()) {
            app()->response()->redirect(base_url('/admin/login'));
            return false;
        }

        return $next();
    }
}
