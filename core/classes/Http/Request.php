<?php

namespace Http;

class Request {
    private $jsonBodyLoaded = false;
    private $jsonBody = null;

    public function server($key = null, $default = null) {
        if ($key === null) {
            return $_SERVER;
        }
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    public function method() {
        return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    }

    public function uri() {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    }

    public function path() {
        $uri = $this->uri();
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
        // Normalize backslashes to forward slashes (Windows compatibility)
        $scriptName = str_replace('\\', '/', $scriptName);
        if ($scriptName && $scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        return '/' . trim($uri, '/');
    }

    public function query($key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    public function post($key = null, $default = null) {
        if ($key === null) {
            return $_POST;
        }

        // Support dot-path keys in HTML form names (e.g. "site.url") by reading
        // nested array shapes produced by PHP when dots are present.
        // Example: name="site.url" becomes $_POST['site']['url'].
        $key = (string)$key;
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $cur = $_POST;
            foreach ($parts as $part) {
                if ($part === '' || !is_array($cur) || !array_key_exists($part, $cur)) {
                    return $default;
                }
                $cur = $cur[$part];
            }
            return $cur;
        }

        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    public function header($name, $default = null) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        // Content-Type and Content-Length are not prefixed with HTTP_ in PHP.
        if ($key === 'HTTP_CONTENT_TYPE') {
            $key = 'CONTENT_TYPE';
        } elseif ($key === 'HTTP_CONTENT_LENGTH') {
            $key = 'CONTENT_LENGTH';
        }

        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    public function acceptsJson() {
        $accept = (string)$this->header('Accept', '');
        return stripos($accept, 'application/json') !== false;
    }

    public function contentType() {
        return (string)$this->header('Content-Type', '');
    }

    public function isJson() {
        return stripos($this->contentType(), 'application/json') !== false;
    }

    public function json($key = null, $default = null) {
        $data = $this->jsonBody();
        if (!is_array($data)) {
            return $key === null ? array() : $default;
        }

        if ($key === null) {
            return $data;
        }

        return isset($data[$key]) ? $data[$key] : $default;
    }

    public function jsonBody() {
        if ($this->jsonBodyLoaded) {
            return $this->jsonBody;
        }

        $this->jsonBodyLoaded = true;

        if (!$this->isJson()) {
            $this->jsonBody = null;
            return null;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $this->jsonBody = null;
            return null;
        }

        $decoded = json_decode($raw, true);
        $this->jsonBody = is_array($decoded) ? $decoded : null;
        return $this->jsonBody;
    }

    /**
     * Unified input accessor.
     *
     * - For JSON requests: returns JSON body fields.
     * - Otherwise: returns POST fields.
     */
    public function input($key = null, $default = null) {
        if ($this->isJson()) {
            return $this->json($key, $default);
        }
        return $this->post($key, $default);
    }

    public function file($key) {
        return isset($_FILES[$key]) ? $_FILES[$key] : null;
    }

    public function ip() {
        $ip = client_ip();
        return $ip ? $ip : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
    }
}
