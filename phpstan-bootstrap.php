<?php declare(strict_types=1);
/**
 * PHPStan bootstrap — defines constants and globals for static analysis.
 *
 * The real core/bootstrap.php reads files from disk and registers an autoloader,
 * which can fail in CI where content directories may not exist.
 * This minimal stub provides only what PHPStan needs to resolve symbols.
 */

define('MANTRA_ROOT', __DIR__);
define('MANTRA_CORE', __DIR__ . '/core');
define('MANTRA_MODULES', __DIR__ . '/modules');
define('MANTRA_CONTENT', __DIR__ . '/content');
define('MANTRA_STORAGE', __DIR__ . '/storage');
define('MANTRA_THEMES', __DIR__ . '/themes');
define('MANTRA_UPLOADS', __DIR__ . '/uploads');
define('MANTRA_DEBUG', false);
define('MANTRA_CLI', false);
define('MANTRA_PROJECT_INFO', [
    'name' => '',
    'version' => '',
    'github' => '',
    'release_date' => '',
]);

$GLOBALS['MANTRA_CONFIG'] = [];
