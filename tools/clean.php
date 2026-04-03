<?php
/**
 * Clean — remove JSON and lock files from content directories.
 *
 * Usage:
 *   php tools/clean.php                    # interactive: preview + confirm
 *   php tools/clean.php --dry-run          # show what would be deleted, don't delete
 *   php tools/clean.php --force            # delete without confirmation
 *   php tools/clean.php --collections=posts,pages   # only clean specific collections
 *   php tools/clean.php --keep=settings,users       # skip these collections (default)
 *   php tools/clean.php --all              # include settings and users (dangerous)
 *   php tools/clean.php --lock-only        # remove only .lock files, keep .json
 *
 * By default, `settings` and `users` are excluded to prevent data loss.
 * Use --all or --collections=settings to override this protection.
 *
 * WARNING: This permanently deletes content files. Use --dry-run first.
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────

chdir(dirname(__DIR__));
require 'core/bootstrap.php';

// ── CLI arguments ───────────────────────────────────────────────────────────

$opts = getopt('', array(
    'dry-run',
    'force',
    'all',
    'lock-only',
    'collections:',
    'keep:',
    'help',
));

if (isset($opts['help'])) {
    echo <<<'HELP'
Mantra CMS — Content Cleaner

Usage:
  php tools/clean.php [options]

Options:
  --dry-run                 Show what would be deleted without deleting
  --force                   Delete without confirmation prompt
  --collections=a,b         Only clean specified collections (comma-separated)
  --keep=a,b                Skip specified collections (default: settings,users)
  --all                     Include settings and users (overrides --keep)
  --lock-only               Remove only .lock files, keep .json data
  --help                    Show this help message

Examples:
  php tools/clean.php --dry-run
  php tools/clean.php --lock-only --force
  php tools/clean.php --collections=posts,pages --force
  php tools/clean.php --all --force

HELP;
    exit(0);
}

$dryRun   = isset($opts['dry-run']);
$force    = isset($opts['force']);
$includeAll = isset($opts['all']);
$lockOnly = isset($opts['lock-only']);

$defaultKeep = array('settings', 'users');

// Determine which collections to process
if (isset($opts['collections']) && $opts['collections'] !== false) {
    $collections = array_filter(array_map('trim', explode(',', $opts['collections'])));
} else {
    // Auto-discover from content directory
    $collections = array();
    $contentDir = MANTRA_CONTENT;
    foreach (scandir($contentDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir($contentDir . '/' . $entry)) {
            $collections[] = $entry;
        }
    }
    sort($collections);
}

// Apply --keep exclusions (unless --all is set)
if (!$includeAll) {
    $keep = isset($opts['keep']) && $opts['keep'] !== false
        ? array_filter(array_map('trim', explode(',', $opts['keep'])))
        : $defaultKeep;
    $collections = array_diff($collections, $keep);
}

if (empty($collections)) {
    echo "Nothing to clean: no collections selected.\n";
    exit(0);
}

// ── Scan files ──────────────────────────────────────────────────────────────

echo "Mantra CMS — Content Cleaner\n";
echo str_repeat('-', 40) . "\n";

if ($dryRun) {
    echo "Mode: DRY RUN (no files will be deleted)\n\n";
} elseif ($lockOnly) {
    echo "Mode: Lock files only\n\n";
}

$plan = array();       // collection => array of file paths to delete
$totalFiles = 0;
$totalSize  = 0;

foreach ($collections as $collection) {
    $dir = MANTRA_CONTENT . '/' . $collection;
    if (!is_dir($dir)) {
        continue;
    }

    $files = array();
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;

        $path = $dir . '/' . $entry;
        if (!is_file($path)) continue;

        $isLock = str_ends_with($entry, '.lock');
        $isJson = str_ends_with($entry, '.json');

        if ($lockOnly) {
            if ($isLock) {
                $files[] = $path;
            }
        } else {
            if ($isJson || $isLock) {
                $files[] = $path;
            }
        }
    }

    if (!empty($files)) {
        $plan[$collection] = $files;
        $totalFiles += count($files);
        foreach ($files as $f) {
            $totalSize += filesize($f);
        }
    }
}

if ($totalFiles === 0) {
    echo "No files to clean.\n";
    exit(0);
}

// ── Preview ─────────────────────────────────────────────────────────────────

foreach ($plan as $collection => $files) {
    $jsonCount = 0;
    $lockCount = 0;
    foreach ($files as $f) {
        if (str_ends_with($f, '.lock')) {
            $lockCount++;
        } else {
            $jsonCount++;
        }
    }
    $parts = array();
    if ($jsonCount > 0) $parts[] = "{$jsonCount} json";
    if ($lockCount > 0) $parts[] = "{$lockCount} lock";
    echo "  {$collection}/  " . implode(', ', $parts) . "\n";
}

$sizeFormatted = $totalSize < 1024
    ? $totalSize . ' B'
    : ($totalSize < 1048576
        ? round($totalSize / 1024, 1) . ' KB'
        : round($totalSize / 1048576, 1) . ' MB');

echo "\nTotal: {$totalFiles} files ({$sizeFormatted})\n";

if ($dryRun) {
    echo "\nDry run complete. No files were deleted.\n";
    exit(0);
}

// ── Confirm ─────────────────────────────────────────────────────────────────

if (!$force) {
    echo "\nDelete these files? This cannot be undone. [y/N] ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        echo "Aborted.\n";
        exit(0);
    }
}

// ── Delete ──────────────────────────────────────────────────────────────────

$deleted = 0;
$errors  = 0;

foreach ($plan as $collection => $files) {
    foreach ($files as $path) {
        if (@unlink($path)) {
            $deleted++;
        } else {
            $errors++;
            echo "  ERROR: could not delete " . basename($path) . "\n";
        }
    }
}

echo "\nDeleted {$deleted} files.";
if ($errors > 0) {
    echo " ({$errors} errors)";
}
echo "\nDone.\n";

exit($errors > 0 ? 1 : 0);
