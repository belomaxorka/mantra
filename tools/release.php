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

$version = $argv[1] ?? '';

if ($version === '') {
    error("Usage: php tools/release.php <version>  (e.g. 1.2.0)");
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
out('  Mantra CMS Release');
out('  ─────────────────────');
out("  Version:      {$version}");
out("  Tag:          {$tag}");
out('  Previous tag: ' . ($prevTag ?: '(none, first release)'));
out("  Date:         {$releaseDate}");
out('');

if (!confirm('Proceed with release?')) {
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
 * Convert (#123) references to markdown links.
 */
function linkifyPr(string $text, string $repoUrl): string
{
    if ($repoUrl === '') {
        return $text;
    }
    return preg_replace(
        '/\(#(\d+)\)/',
        "([#$1]({$repoUrl}/pull/$1))",
        $text
    );
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

    // Merge commit: "Merge pull request #123 from user/branch"
    // Use the PR title from the commit body instead of the merge message.
    if (preg_match('/^Merge pull request #(\d+) from/', $subject, $m)) {
        $prNumber = $m[1];
        $body = trim(git("log -1 --format=%b {$hash}"));
        $title = strtok($body, "\n");

        if ($title !== '' && $title !== false) {
            $entries[] = "{$title} (#{$prNumber})";
        }
        continue;
    }

    // Skip other merge commits (e.g. "Merge branch 'x' into y")
    if (str_starts_with($subject, 'Merge ')) {
        continue;
    }

    $entries[] = $subject;
}

$feats = array();
$fixes = array();
$others = array();

foreach ($entries as $entry) {
    $linked = linkifyPr($entry, $repoUrl);

    if (preg_match('/^feat(\(.*?\))?!?:/', $entry)) {
        $feats[] = $linked;
    } elseif (preg_match('/^fix(\(.*?\))?!?:/', $entry)) {
        $fixes[] = $linked;
    } else {
        $others[] = $linked;
    }
}

$changelog = "## [{$tag}] - {$releaseDate}" . PHP_EOL;

if (!empty($feats)) {
    $changelog .= PHP_EOL . '### Added' . PHP_EOL . PHP_EOL;
    foreach ($feats as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($fixes)) {
    $changelog .= PHP_EOL . '### Fixed' . PHP_EOL . PHP_EOL;
    foreach ($fixes as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

if (!empty($others)) {
    $changelog .= PHP_EOL . '### Other' . PHP_EOL . PHP_EOL;
    foreach ($others as $line) {
        $changelog .= "- {$line}" . PHP_EOL;
    }
}

out('');
out('── Changelog ──────────────────────────────────');
out($changelog);
out('────────────────────────────────────────────────');
out('');

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
    $content = implode("\n", $header) . "\n\n" . $changelog . implode("\n", $lines);
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
