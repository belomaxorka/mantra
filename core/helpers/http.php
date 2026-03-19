<?php
/**
 * HTTP helpers
 */

/**
 * Get session wrapper instance
 */
function session()
{
    static $session = null;
    if ($session === null) {
        $session = new Http\Session();
    }
    return $session;
}

/**
 * Get cookie wrapper instance
 */
function cookie()
{
    static $cookie = null;
    if ($cookie === null) {
        $cookie = new Http\Cookie();
    }
    return $cookie;
}

/**
 * Get response wrapper instance
 */
function response()
{
    static $response = null;
    if ($response === null) {
        $response = new Http\Response();
    }
    return $response;
}

/**
 * Get request wrapper instance
 */
function request()
{
    static $request = null;
    if ($request === null) {
        $request = new Http\Request();
    }
    return $request;
}

/**
 * Redirect helper
 */
function redirect($url, $code = 302)
{
    response()->redirect($url, $code);
}

/**
 * JSON response helper
 */
function json_response($data, $code = 200)
{
    response()->json($data, $code);
}

/**
 * Determine whether the current request is HTTPS.
 *
 * Uses request headers first (HTTPS / X-Forwarded-Proto), with a fallback to site.url scheme.
 */
function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') {
            return true;
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
 * Get client IP address.
 *
 * By default returns REMOTE_ADDR.
 *
 * If config('proxy.trusted_proxies') is set and the current REMOTE_ADDR matches one of them
 * (IP or CIDR), this function will also consider common proxy/CDN headers.
 */
function client_ip()
{
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    if (!$remoteAddr || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return null;
    }

    $trusted = config('proxy.trusted_proxies', array());
    if (is_string($trusted)) {
        $trusted = array_filter(array_map('trim', explode(',', $trusted)), 'strlen');
    }
    if (!is_array($trusted)) {
        $trusted = array();
    }

    if (empty($trusted) || !ip_matches_any($remoteAddr, $trusted)) {
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

        // Prefer public IPs when present.
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
 * Check whether an IP matches any entry in a list of IPs/CIDRs.
 */
function ip_matches_any($ip, $entries)
{
    foreach ($entries as $entry) {
        if (ip_matches($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/**
 * Check whether an IP matches a single entry (IP or CIDR).
 */
function ip_matches($ip, $entry)
{
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

    $bytes = intdiv($bits, 8);
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
