<?php

namespace Http;

class Session {
    public function status() {
        return session_status();
    }

    public function start($options = array()) {
        if (defined('MANTRA_CLI') && MANTRA_CLI) {
            return;
        }

        if ($this->status() !== PHP_SESSION_NONE) {
            return;
        }

        if (headers_sent($file, $line)) {
            logger()->warning('Cannot start session: headers already sent', array(
                'file' => $file,
                'line' => $line
            ));
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
            $secure = is_https();
        }

        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            $samesite = config('session.cookie_samesite', 'Lax');

            // Validate SameSite value
            $validSameSite = array('Lax', 'Strict', 'None');
            if (!in_array($samesite, $validSameSite, true)) {
                $samesite = 'Lax';
            }

            session_set_cookie_params(array(
                'lifetime' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ));
        } else {
            session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        }

        @ini_set('session.gc_maxlifetime', (string)$lifetime);

        if (!empty($options)) {
            @session_start($options);
        } else {
            @session_start();
        }
    }

    public function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function has($key) {
        return isset($_SESSION[$key]);
    }

    public function delete($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    public function regenerate($deleteOldSession = true) {
        if (defined('MANTRA_CLI') && MANTRA_CLI) {
            return false;
        }
        return session_regenerate_id($deleteOldSession);
    }

    public function destroy() {
        if (defined('MANTRA_CLI') && MANTRA_CLI) {
            return false;
        }
        $_SESSION = array();
        return session_destroy();
    }
}
