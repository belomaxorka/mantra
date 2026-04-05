<?php declare(strict_types=1);
/**
 * CsrfMiddleware - Verify CSRF token on state-changing requests.
 *
 * Safe methods per RFC 7231 (GET, HEAD, OPTIONS) pass through unconditionally.
 * All other methods (POST, PUT, PATCH, DELETE, ...) require a valid token.
 *
 * Token is read from the request body (JSON or form-encoded) or the
 * X-CSRF-Token header — see Auth::extractCsrfTokenFromRequest().
 */

class CsrfMiddleware implements \Http\MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param callable $next
     * @return bool
     */
    public function handle($next)
    {
        $request = app()->request();

        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next();
        }

        $auth = app()->auth();
        $token = $auth->extractCsrfTokenFromRequest($request);

        if (!$auth->verifyCsrfToken($token)) {
            http_response_code(403);
            if ($request->acceptsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            } else {
                echo 'Invalid CSRF token';
            }
            return false;
        }

        return $next();
    }
}
