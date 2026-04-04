<?php declare(strict_types=1);
/**
 * CsrfMiddleware - Verify CSRF token on POST requests
 *
 * Checks both the POST field (csrf_token) and the X-CSRF-Token header.
 * Non-POST requests pass through unconditionally.
 */

class CsrfMiddleware implements \Http\MiddlewareInterface
{
    /**
     * @param callable $next
     * @return bool
     */
    public function handle($next)
    {
        $request = app()->request();

        if ($request->method() !== 'POST') {
            return $next();
        }

        $token = $request->post('csrf_token', '')
              ?: $request->header('X-CSRF-Token', '');

        if (!app()->auth()->verifyCsrfToken($token)) {
            if ($request->acceptsJson()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            } else {
                http_response_code(403);
                echo 'Invalid CSRF token';
            }
            return false;
        }

        return $next();
    }
}
