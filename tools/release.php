<?php
/**
 * Release script for Mantra CMS
 *
 * Usage:
 *   php tools/release.php <version>
 *
 * Example:
 *   php tools/release.php 1.2.0
 *
 * What it does:
 *   1. Validates the version argument (semver)
 *   2. Updates version and release_date in core/bootstrap.php
 *   3. Generates/prepends changelog entry from conventional commits
 *   4. Commits the version bump
 *   5. Creates an annotated git tag (v<version>)
 *   6. Pushes commit and tag to origin
 *
 * The pushed tag triggers .github/workflows/release.yml which
 * creates a GitHub Release with the changelog.
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

// ── Helpers ──────────────────────────────────────────────────

function out(string $text): void
{
    echo $text . PHP_EOL;
}

function error(string $message): never
{
    fwrite(STDERR, "Error: {$message}" . PHP_EOL);
    exit(1);
}

function confirm(string $question): bool
{
    echo "{$question} [y/N] ";
    $answer = trim(fgets(STDIN));
    return in_array(strtolower($answer), array('y', 'yes'), true);
}

function git(string $args): string
{
    $output = array();
    $code = 0;
    exec("git {$args} 2>&1", $output, $code);
    return implode("\n", $output);
}

function gitOk(string $args): bool
{
    $code = 0;
    exec("git {$args} 2>&1", $output, $code);
    return $code === 0;
}

// ── Validate inputs ─────────────────────────────────────────

$dryRun = in_array('--dry-run', $argv, true);
$args = array_filter($argv, fn($a) => $a !== '--dry-run');
$version = $args[1] ?? '';

if ($version === '') {
    error("Usage: php tools/release.php <version> [--dry-run]  (e.g. 1.2.0)");
}

// Strip leading 'v' if provided
$version = ltrim($version, 'v');

if (!preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/', $version)) {
    error("Invalid version format: {$version} (expected semver, e.g. 1.2.0)");
}

$tag = "v{$version}";

// ── Pre-flight checks ───────────────────────────────────────

$repoRoot = trim(git('rev-parse --show-toplevel'));
if (!is_dir($repoRoot)) {
    error('Not inside a git repository');
}
chdir($repoRoot);

// Check we are on main branch
$branch = trim(git('branch --show-current'));
if ($branch !== 'main') {
    error("Must be on 'main' branch (currently on '{$branch}')");
}

// Check working tree is clean
if (!gitOk('diff --quiet') || !gitOk('diff --cached --quiet')) {
    error('Working tree is not clean. Commit or stash changes first.');
}

// Check tag doesn't already exist
if (gitOk("rev-parse {$tag}")) {
    error("Tag {$tag} already exists");
}

// Find previous tag for changelog range
$prevTag = trim(git('describe --tags --abbrev=0'));
if (!gitOk('describe --tags --abbrev=0')) {
    $prevTag = '';
}

$releaseDate = date('Y-m-d');

// ── Show plan ───────────────────────────────────────────────

out('');
out('  Mantra CMS Release' . ($dryRun ? '  [DRY RUN]' : ''));
out('  ─────────────────────');
out("  Version:      {$version}");
out("  Tag:          {$tag}");
out('  Previous tag: ' . ($prevTag ?: '(none, first release)'));
out("  Date:         {$releaseDate}");
out('');

if (!$dryRun && !confirm('Proceed with release?')) {
    out('Aborted.');
    exit(0);
}

// ── Detect GitHub repo URL (for PR links) ──────────────────

$repoUrl = '';
$remoteUrl = trim(git('remote get-url origin'));

if (preg_match('#github\.com[:/](.+?)(?:\.git)?$#', $remoteUrl, $m)) {
    $repoUrl = "https://github.com/{$m[1]}";
}

/**
 * Strip the conventional commit prefix and format for changelog.
 *
 * "feat(seo): add Open Graph"  → "(seo) Add Open Graph"
 * "feat!: drop PHP 8.0"        → "Drop PHP 8.0"
 * "chore: update deps"         → "Update deps"
 */
function formatEntry(string $text): string
{
    if (preg_match('/^[a-z]+\((.+?)\)!?:\s*(.+)$/', $text, $m)) {
        return "({$m[1]}) " . ucfirst($m[2]);
    }
    if (preg_match('/^[a-z]+!?:\s*(.+)$/', $text, $m)) {
        return ucfirst($m[1]);
    }
    return ucfirst($text);
}

/**
 * Format the trailing reference: PR link + short hash, or just short hash.
 *
 * PR:     (#42) - (66b6dcf)
 * Commit: (66b6dcf)
 */
function formatRef(array $entry, string $repoUrl): string
{
    $shortHash = substr($entry['hash'], 0, 7);

    if ($repoUrl !== '') {
        $hashLink = "[{$shortHash}]({$repoUrl}/commit/{$entry['hash']})";
        if (!empty($entry['pr'])) {
            $prLink = "[#{$entry['pr']}]({$repoUrl}/pull/{$entry['pr']})";
            return "({$prLink}) - ({$hashLink})";
        }
        return "({$hashLink})";
    }

    if (!empty($entry['pr'])) {
        return "(#{$entry['pr']}) - ({$shortHash})";
    }
    return "({$shortHash})";
}

// ── Generate changelog ──────────────────────────────────────

$range = $prevTag !== '' ? "{$prevTag}..HEAD" : 'HEAD';

// --first-parent: only commits on the main line (merge commits for PRs,
// direct commits for squash merges and direct pushes — no individual
// commits from inside merged branches).
$hashOutput = git("log {$range} --first-parent --format=%H");
$hashes = array_filter(explode("\n", $hashOutput), 'strlen');

$entries = array();

foreach ($hashes as $hash) {
    $subject = trim(git("log -1 --format=%s {$hash}"));
    $body = trim(git("log -1 --format=%b {$hash}"));

    // Merge commit: "Merge pull request #123 from user/branch"
    // Use the PR title from the commit body instead of the merge message.
    if (preg_match('/^Merge pull request #(\d+) from/', $subject, $m)) {
        $prNumber = $m[1];
        $title = strtok($body, "\n");

        if ($title !== '' && $title !== false) {
            $entries[] = array(
                'text' => $title,
                'body' => $body,
                'hash' => $hash,
                'pr' => $prNumber,
            );
        }
        continue;
    }

    // Skip other merge commits (e.g. "Merge branch 'x' into y")
    if (str_starts_with($subject, 'Merge ')) {
        continue;
    }

    // Squash-merged PR: subject ends with (#N)
    $pr = '';
    $text = $subject;
    if (preg_match('/^(.+?)\s*\(#(\d+)\)$/', $subject, $m)) {
        $text = $m[1];
        $pr = $m[2];
    }

    $entries[] = array(
        'text' => $text,
        'body' => $body,
        'hash' => $hash,
        'pr' => $pr,
    );
}

// ── Filter out reverted commits ─────────────────────────────
// If "Revert "X"" and the original "X" both appear in the same range,
// neither belongs in the changelog — the net effect is zero.
// But if only the revert is present (original was in a previous release),
// the revert MUST stay — it's a real change for this release.

// Strip trailing (#N) for matching: revert subjects may quote the original
// with or without the PR number suffix.
$normalize = fn(string $s): string => trim(preg_replace('/\s*\(#\d+\)$/', '', $s));

// Collect normalized subjects that were reverted
$revertedNormalized = array();
foreach ($entries as $entry) {
    if (preg_match('/^Revert "(.+)"/', $entry['text'], $m)) {
        $revertedNormalized[] = $normalize($m[1]);
    }
}

if (!empty($revertedNormalized)) {
    // Find which reverted subjects have a matching original in this range
    $matchedOriginals = array();
    foreach ($entries as $entry) {
        if (!preg_match('/^Revert "/', $entry['text'])) {
            if (in_array($normalize($entry['text']), $revertedNormalized, true)) {
                $matchedOriginals[] = $normalize($entry['text']);
            }
        }
    }

    // Only filter pairs where both original and revert are in this range
    if (!empty($matchedOriginals)) {
        $entries = array_values(array_filter($entries, function ($entry) use ($matchedOriginals, $normalize) {
            if (preg_match('/^Revert "(.+)"/', $entry['text'], $m)) {
                return !in_array($normalize($m[1]), $matchedOriginals, true);
            }
            return !in_array($normalize($entry['text']), $matchedOriginals, true);
        }));
    }
}

/**
 * Check whether a commit is a breaking change.
 *
 * Detected via:
 * - "!" before ":" in subject (e.g. feat!:, feat(scope)!:)
 * - "BREAKING CHANGE:" or "BREAKING-CHANGE:" in commit body
 *
 * Returns the breaking change note, empty string if breaking but no note,
 * or null if not a breaking change.
 */
function detectBreaking(array $entry): ?string
{
    // Check "!" in subject: type(scope)!: description
    if (preg_match('/^[a-z]+(\(.*?\))?!:/', $entry['text'])) {
        // Try to extract note from body, fall back to empty
        if (preg_match('/^BREAKING[ -]CHANGE:\s*(.+)/m', $entry['body'], $m)) {
            return trim($m[1]);
        }
        return '';
    }

    // Check BREAKING CHANGE footer in body
    if (preg_match('/^BREAKING[ -]CHANGE:\s*(.+)/m', $entry['body'], $m)) {
        return trim($m[1]);
    }

    return null;
}

$breaking = array();
$feats = array();
$fixes = array();
$refactors = array();
$others = array();

foreach ($entries as $entry) {
    $line = formatEntry($entry['text']) . ' ' . formatRef($entry, $repoUrl);
    $breakingNote = detectBreaking($entry);

    // Breaking changes go to their own section
    if ($breakingNote !== null) {
        if ($breakingNote !== '') {
            $line .= " — {$breakingNote}";
        }
        $breaking[] = $line;
        continue;
    }

    if (preg_match('/^feat(\(.*?\))?!?:/', $entry['text'])) {
        $feats[] = $line;
    } elseif (preg_match('/^fix(\(.*?\))?!?:/', $entry['text'])) {
        $fixes[] = $line;
    } elseif (preg_match('/^refactor(\(.*?\))?!?:/', $entry['text'])) {
        $refactors[] = $line;
    } else {
        $others[] = $line;
    }
}

$changelog = "## [{$tag}] - {$releaseDate}" . PHP_EOL;

if (!empty($breaking)) {
    $changelog .= PHP_EOL . '### ⚠️ Breaking Changes' . PHP_EOL . PHP_EOL;
    foreach ($breaking as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($feats)) {
    $changelog .= PHP_EOL . '### ✨ Added' . PHP_EOL . PHP_EOL;
    foreach ($feats as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($fixes)) {
    $changelog .= PHP_EOL . '### 🐛 Fixed' . PHP_EOL . PHP_EOL;
    foreach ($fixes as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($refactors)) {
    $changelog .= PHP_EOL . '### ♻️ Refactored' . PHP_EOL . PHP_EOL;
    foreach ($refactors as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($others)) {
    $changelog .= PHP_EOL . '### 📦 Other' . PHP_EOL . PHP_EOL;
    foreach ($others as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

out('');
out('── Changelog ──────────────────────────────────');
out($changelog);
out('────────────────────────────────────────────────');
out('');

if ($dryRun) {
    out('Dry run complete. No changes were made.');
    exit(0);
}

if (!confirm('Changelog looks good?')) {
    out('Aborted.');
    exit(0);
}

// ── Update bootstrap.php version ────────────────────────────

$bootstrapFile = 'core/bootstrap.php';
$bootstrap = file_get_contents($bootstrapFile);

$bootstrap = preg_replace(
    "/'version' => '[^']*'/",
    "'version' => '{$version}'",
    $bootstrap
);
$bootstrap = preg_replace(
    "/'release_date' => '[^']*'/",
    "'release_date' => '{$releaseDate}'",
    $bootstrap
);

file_put_contents($bootstrapFile, $bootstrap);
out("Updated {$bootstrapFile}");

// ── Write CHANGELOG.md ──────────────────────────────────────

$changelogFile = 'CHANGELOG.md';

if (file_exists($changelogFile)) {
    $existing = file_get_contents($changelogFile);
    // Insert new entry after the "# Changelog\n" header (first two lines)
    $lines = explode("\n", $existing);
    $header = array_splice($lines, 0, 2);
    $content = implode("\n", $header) . "\n" . $changelog . "\n" . implode("\n", $lines);
} else {
    $content = "# Changelog\n\n" . $changelog;
}

file_put_contents($changelogFile, $content);
out("Updated {$changelogFile}");

// ── Commit, tag, push ───────────────────────────────────────

git("add {$bootstrapFile} {$changelogFile}");
git("commit -m \"release: v{$version}\n\nBump version to {$version} and generate changelog.\"");
out("Created commit: release v{$version}");

git("tag -a {$tag} -m \"Release {$tag}\"");
out("Created tag: {$tag}");

out('');

if (!confirm('Push commit and tag to origin?')) {
    out('Commit and tag created locally. Push manually with:');
    out("  git push origin main {$tag}");
    exit(0);
}

git("push origin main {$tag}");

out('');
out("Done! Tag {$tag} pushed. GitHub Actions will create the release.");
