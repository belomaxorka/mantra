<?php
/**
 * Authentication helpers
 */

/**
 * Get auth instance
 */
function auth()
{
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

/**
 * Verify CSRF token from POST request
 * @return bool
 */
function verify_csrf()
{
    if (request()->method() !== 'POST') {
        return true;
    }

    $token = request()->post('csrf_token', '');
    if (!auth()->verifyCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        return false;
    }
    return true;
}
