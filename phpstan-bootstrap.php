<?php declare(strict_types=1);
/**
 * PHPStan bootstrap — defines constants and globals for static analysis.
 *
 * The real core/bootstrap.php reads files from disk and registers an autoloader,
 * which can fail in CI where content directories may not exist.
 * This minimal stub provides only what PHPStan needs to resolve symbols.
 */

define('MANTRA_ROOT', '');
define('MANTRA_CORE', '');
define('MANTRA_MODULES', '');
define('MANTRA_CONTENT', '');
define('MANTRA_STORAGE', '');
define('MANTRA_THEMES', '');
define('MANTRA_UPLOADS', '');
define('MANTRA_DEBUG', false);
define('MANTRA_CLI', false);
define('MANTRA_PROJECT_INFO', [
    'name' => '',
    'version' => '',
    'github' => '',
    'release_date' => '',
]);

$GLOBALS['MANTRA_CONFIG'] = [];
