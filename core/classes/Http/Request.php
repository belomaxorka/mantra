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
        $scriptName = \Config::normalizeScriptPath($scriptName);
        if ($scriptName && $scriptName !== '/' && str_starts_with($uri, $scriptName)) {
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
        if (str_contains($key, '.')) {
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
        return $this->clientIp();
    }

    /**
     * Get trimmed POST value
     */
    public function postTrimmed($key, $default = '') {
        return trim((string)$this->post($key, $default));
    }

    /**
     * Determine whether the current request is HTTPS.
     */
    public static function isHttps() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $trusted = config('proxy.trusted_proxies', array());
            $trusted = self::parseCsv($trusted);
            $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

            if (!empty($trusted) && self::ipMatchesAny($remoteAddr, $trusted)) {
                $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
                if ($proto === 'https') {
                    return true;
                }
            }
        }

        $siteUrl = config('site.url');
        if ($siteUrl) {
            $scheme = parse_url($siteUrl, PHP_URL_SCHEME);
            return strtolower((string)$scheme) === 'https';
        }

        return false;
    }

    /**
     * Get client IP address, considering trusted proxy headers.
     */
    public function clientIp() {
        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        if (!$remoteAddr || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return null;
        }

        $trusted = config('proxy.trusted_proxies', array());
        $trusted = self::parseCsv($trusted);

        if (empty($trusted) || !self::ipMatchesAny($remoteAddr, $trusted)) {
            return $remoteAddr;
        }

        $candidates = array(
            isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : null,
            isset($_SERVER['HTTP_FASTLY_CLIENT_IP']) ? $_SERVER['HTTP_FASTLY_CLIENT_IP'] : null,
            isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : null,
        );

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $candidates[] = $part;
                }
            }
        }

        foreach ($candidates as $ip) {
            if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }

    /**
     * Parse comma-separated string into array.
     */
    public static function parseCsv($value) {
        if (is_array($value)) {
            return array_filter(array_map('trim', $value), 'strlen');
        }
        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)), 'strlen');
        }
        return array();
    }

    /**
     * Check whether an IP matches any entry in a list of IPs/CIDRs.
     */
    public static function ipMatchesAny($ip, $entries) {
        foreach ($entries as $entry) {
            if (self::ipMatches($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether an IP matches a single entry (IP or CIDR).
     */
    public static function ipMatches($ip, $entry) {
        $entry = trim((string)$entry);
        if ($entry === '') {
            return false;
        }

        if (strpos($entry, '/') === false) {
            return $ip === $entry;
        }

        list($subnet, $bits) = array_pad(explode('/', $entry, 2), 2, null);
        if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        $bits = (int)$bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0) {
            $bits = 0;
        }
        if ($bits > $maxBits) {
            $bits = $maxBits;
        }

        $bytes = (int)($bits / 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
