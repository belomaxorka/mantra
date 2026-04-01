<?php

namespace Http;

class Cookie {

    public function get($name, $default = null) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
    }

    public function has($name) {
        return isset($_COOKIE[$name]);
    }

    public function set($name, $value, $options = array()) {
        if (MANTRA_CLI) {
            return false;
        }

        if (headers_sent($file, $line)) {
            logger()->warning('Cannot set cookie: headers already sent', array(
                'cookie' => $name,
                'file' => $file,
                'line' => $line
            ));
            return false;
        }

        $expires = isset($options['expires']) ? (int)$options['expires'] : 0;
        $path = isset($options['path']) ? $options['path'] : config('session.cookie_path', '/');
        $domain = isset($options['domain']) ? $options['domain'] : config('session.cookie_domain', '');
        $httponly = array_key_exists('httponly', $options) ? (bool)$options['httponly'] : (bool)config('session.cookie_httponly', true);

        // Determine SameSite
        if (isset($options['samesite'])) {
            $samesite = $options['samesite'];
        } else {
            $samesite = config('session.cookie_samesite', 'Lax');
        }

        // Determine secure flag
        $secure = null;
        if (array_key_exists('secure', $options)) {
            $secure = (bool)$options['secure'];
        } else {
            $secureConfig = config('session.cookie_secure', 'auto');
            if ($secureConfig === 'true' || $secureConfig === true) {
                $secure = true;
            } elseif ($secureConfig === 'false' || $secureConfig === false) {
                $secure = false;
            } else {
                // auto
                $secure = is_https();
            }
        }

        // PHP 7.3+ supports options array with SameSite
        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, $value, array(
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ));
        }

        // Legacy signature (no SameSite support)
        return setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
    }

    public function delete($name, $options = array()) {
        $options['expires'] = time() - 3600;
        return $this->set($name, '', $options);
    }
}
