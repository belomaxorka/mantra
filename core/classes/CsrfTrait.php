<?php declare(strict_types=1);
/**
 * CsrfTrait - Shared CSRF verification for admin controllers
 *
 * Reads the token from POST field (csrf_token) or the X-CSRF-Token header
 * and verifies it against the session. Returns JSON or plain-text 403 on failure.
 */

trait CsrfTrait
{
    protected function verifyCsrf()
    {
        if (app()->request()->method() !== 'POST') {
            return true;
        }

        $token = app()->request()->post('csrf_token', '')
              ?: app()->request()->header('X-CSRF-Token', '');

        if (!app()->auth()->verifyCsrfToken($token)) {
            if (app()->request()->acceptsJson()) {
                app()->response()->json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
                return false;
            }
            http_response_code(403);
            echo 'Invalid CSRF token';
            return false;
        }
        return true;
    }
}
