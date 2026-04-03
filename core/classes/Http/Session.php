<?php declare(strict_types=1);

namespace Http;

class Session
{
    public function status()
    {
        return session_status();
    }

    public function start($options = []): void
    {
        if (MANTRA_CLI) {
            return;
        }

        if ($this->status() !== PHP_SESSION_NONE) {
            return;
        }

        if (headers_sent($file, $line)) {
            logger()->warning('Cannot start session: headers already sent', [
                'file' => $file,
                'line' => $line,
            ]);
            return;
        }

        $sessionName = config('session.name', 'mantra_session');
        session_name($sessionName);

        // Configure session cookie params before start
        $lifetime = (int)config('session.lifetime', 7200);
        $path = config('session.cookie_path', '/');
        $domain = config('session.cookie_domain', '');
        $httponly = (bool)config('session.cookie_httponly', true);

        // Determine secure flag
        $secureConfig = config('session.cookie_secure', 'auto');
        if ($secureConfig === 'true' || $secureConfig === true) {
            $secure = true;
        } elseif ($secureConfig === 'false' || $secureConfig === false) {
            $secure = false;
        } else {
            // auto
            $secure = \Http\Request::isHttps();
        }

        $samesite = config('session.cookie_samesite', 'Lax');

        // Validate SameSite value
        $validSameSite = ['Lax', 'Strict', 'None'];
        if (!in_array($samesite, $validSameSite, true)) {
            $samesite = 'Lax';
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        @ini_set('session.gc_maxlifetime', (string)$lifetime);

        if (!empty($options)) {
            @session_start($options);
        } else {
            @session_start();
        }
    }

    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public function delete($key): void
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function regenerate($deleteOldSession = true)
    {
        if (MANTRA_CLI) {
            return false;
        }
        return session_regenerate_id($deleteOldSession);
    }

    public function destroy()
    {
        if (MANTRA_CLI) {
            return false;
        }
        $_SESSION = [];
        return session_destroy();
    }

    /**
     * Add a flash message (survives one redirect).
     *
     * @param string $type    Message type: success, danger, warning, info
     * @param string $message The message text
     */
    public function flash($type, $message): void
    {
        $flashes = $this->get('_flashes', []);
        $flashes[] = ['type' => $type, 'message' => $message];
        $this->set('_flashes', $flashes);
    }

    /**
     * Retrieve and clear all flash messages.
     *
     * @return array<array{type: string, message: string}>
     */
    public function getFlashes(): array
    {
        $flashes = $this->get('_flashes', []);
        $this->delete('_flashes');
        return $flashes;
    }
}
