# Release — Version & Changelog Automation

Automate version bumps, changelog generation, and GitHub Releases. The flow consists of a local PHP script that prepares and pushes the release, and a GitHub Actions workflow that creates the GitHub Release.

## Quick Start

```bash
php tools/release.php 1.2.0

# Preview changelog without making changes
php tools/release.php 1.2.0 --dry-run

# Pre-release (marked as pre-release on GitHub)
php tools/release.php 2.0.0-beta.1
```

The script is interactive — it asks for confirmation at each step and can be aborted at any point.

## What Happens

### 1. Local: `tools/release.php`

```
php tools/release.php <version>
```

The version argument accepts semver with an optional `v` prefix (`1.2.0` or `v1.2.0`).

Steps performed:

1. **Pre-flight checks** — verifies you are on `main`, working tree is clean, and the tag doesn't already exist.
2. **Changelog generation** — collects entries since the last tag using `--first-parent` traversal, which correctly handles all GitHub merge strategies:
   - **Squash merge** — the squashed commit appears directly (e.g. `feat(seo): add OG images (#42)`)
   - **Merge commit** — the PR title is extracted from the merge commit body, with the PR number appended
   - **Rebase merge / direct push** — individual commits appear as-is
   
   Entries are grouped by [Conventional Commits](https://www.conventionalcommits.org/) type:
   - Breaking changes (`!` or `BREAKING CHANGE` footer) → **⚠️ Breaking Changes**
   - `feat(...)` → **✨ Added**
   - `fix(...)` → **🐛 Fixed**
   - `refactor(...)` → **♻️ Refactored**
   - Everything else → **📦 Other**
   
   Conventional commit prefixes are stripped — the type is already clear from the section heading. Each entry includes a linked short commit hash, and for PRs the PR number is also linked:
   - Commit: `(scope) Description (hash)`
   - PR: `(scope) Description (#PR) - (hash)`
3. **Preview** — displays the release plan and generated changelog, asks for confirmation.
4. **Version bump** — updates `version` and `release_date` in `core/bootstrap.php`.
5. **CHANGELOG.md** — prepends the new entry (creates the file on first release).
6. **Commit & tag** — creates a `release: vX.Y.Z` commit and an annotated `vX.Y.Z` tag.
7. **Push** — pushes the commit and tag to `origin` (asks for confirmation).

### 2. Remote: GitHub Actions workflow

When a `v*` tag is pushed, `.github/workflows/release.yml` runs:

1. **Tests** — runs PHPUnit on PHP 8.1 to verify the tagged code passes.
2. **Changelog extraction** — extracts the section for the pushed tag from `CHANGELOG.md`.
3. **Archive** — creates a clean source zip via `git archive` (dev files excluded by `.gitattributes`).
4. **GitHub Release** — creates the release with changelog and archive. Versions with a hyphen (e.g. `2.0.0-beta.1`) are automatically marked as pre-release.

## Example Session

```
$ php tools/release.php 1.2.0

  Mantra CMS Release
  ─────────────────────
  Version:      1.2.0
  Tag:          v1.2.0
  Previous tag: v1.1.0
  Date:         2026-04-03

Proceed with release? [y/N] y

── Changelog ──────────────────────────────────
## [v1.2.0] - 2026-04-03

### ✨ Added

- (seo) Add Open Graph image support ([#42](https://github.com/belomaxorka/mantra/pull/42)) - ([a1b2c3d](https://github.com/belomaxorka/mantra/commit/a1b2c3d))
- (categories) Add category description field ([3f4e5d6](https://github.com/belomaxorka/mantra/commit/3f4e5d6))

### 🐛 Fixed

- (router) Normalize trailing slashes ([#41](https://github.com/belomaxorka/mantra/pull/41)) - ([7g8h9i0](https://github.com/belomaxorka/mantra/commit/7g8h9i0))

### 📦 Other

- Update dependencies ([b2c3d4e](https://github.com/belomaxorka/mantra/commit/b2c3d4e))
────────────────────────────────────────────────

Changelog looks good? [y/N] y
Updated core/bootstrap.php
Updated CHANGELOG.md
Created commit: release v1.2.0
Created tag: v1.2.0

Push commit and tag to origin? [y/N] y

Done! Tag v1.2.0 pushed. GitHub Actions will create the release.
```

## CLI Options

| Option | Description |
|--------|-------------|
| `<version>` | Version to release (semver, e.g. `1.2.0`, `2.0.0-beta.1`) |
| `--dry-run` | Show plan and changelog preview without making any changes |

## Files Modified by the Script

| File | Change |
|------|--------|
| `core/bootstrap.php` | `version` and `release_date` in `MANTRA_PROJECT_INFO` |
| `CHANGELOG.md` | New section prepended (created if missing) |

## Aborting a Release

You can answer `N` at any confirmation prompt to abort. If you abort after the commit/tag step but before push:

```bash
# Undo the release commit and tag
git tag -d v1.2.0
git reset --soft HEAD~1
```

## Requirements

- PHP 8.1+ (CLI)
- Git
- Clean working tree on `main` branch
- GitHub Actions enabled (for the remote release workflow)

## Changelog Format

The generated `CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/) conventions:

```markdown
# Changelog

## [v1.2.0] - 2026-04-03

### ⚠️ Breaking Changes

- (auth) Rewrite session storage (a1b2c3d) — Existing sessions will be invalidated

### ✨ Added

- (seo) Add Open Graph image support (#42) - (c3d4e5f)

### 🐛 Fixed

- (router) Normalize trailing slashes (d4e5f6g)

### ♻️ Refactored

- (database) Extract query builder (#40) - (e5f6g7h)

### 📦 Other

- Update dependencies (f6g7h8i)

## [v1.1.0] - 2026-03-17
...
```

## Release Archive

The GitHub Release includes a `mantra-X.Y.Z.zip` archive built with `git archive`. Files marked `export-ignore` in `.gitattributes` are excluded (tests, docs, dev tools, CI config).
