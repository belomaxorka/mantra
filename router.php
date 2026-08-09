<?php declare(strict_types=1);

/**
 * Safe router for PHP's built-in development server.
 * Production web servers must use the rules from docs/server-configs.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

$staticPatterns = [
    '#^/themes/[^/]+/assets/.+\.(?:css|js|map|ico|gif|jpe?g|png|svg|webp|woff2?|ttf|eot)$#i',
    '#^/modules/(?:[^/]+/assets|admin/panels/[^/]+/assets)/.+\.(?:css|js|map|ico|gif|jpe?g|png|webp|woff2?|ttf|eot)$#i',
    '#^/uploads/.+\.(?:gif|jpe?g|png|webp|pdf|txt|zip)$#i',
];

$serveDirectly = $path === '/install.php';
foreach ($staticPatterns as $pattern) {
    if (preg_match($pattern, $path) === 1) {
        $serveDirectly = true;
        break;
    }
}

if ($serveDirectly) {
    $root = realpath(__DIR__);
    $file = realpath(__DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path));
    $prefix = $root !== false ? rtrim($root, '/\\') . DIRECTORY_SEPARATOR : '';
    if ($file !== false && $prefix !== '' && str_starts_with($file, $prefix) && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
