<?php

namespace Http;

class Cookie {
    private function isHttps() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }

    public function get($name, $default = null) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
    }

    public function has($name) {
        return isset($_COOKIE[$name]);
    }

    public function set($name, $value, $options = array()) {
        if (defined('MANTRA_CLI') && MANTRA_CLI) {
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
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : '';
        $httponly = array_key_exists('httponly', $options) ? (bool)$options['httponly'] : true;
        $samesite = isset($options['samesite']) ? $options['samesite'] : 'Lax';

        $secure = null;
        if (array_key_exists('secure', $options)) {
            $secure = (bool)$options['secure'];
        } else {
            $secure = $this->isHttps();
        }

        // PHP 7.3+ supports options array with SameSite
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
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
