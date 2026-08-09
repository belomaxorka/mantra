<?php declare(strict_types=1);

namespace Http;

/** Baseline browser hardening applied to every response, including 404s. */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): bool
    {
        self::apply(app()->request()->path());
        return $next();
    }

    public static function apply(string $path): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            header('Cache-Control: no-store, private');
            header('Pragma: no-cache');
        }

    }
}
