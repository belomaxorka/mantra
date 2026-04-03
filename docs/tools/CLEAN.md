# Clean — Content Directory Cleaner

Remove JSON and lock files from `content/` directories. Useful for resetting the CMS to a clean state during development, or for removing stale `.lock` files left after crashes.

## Quick Start

```bash
# Preview what will be deleted (safe — nothing is removed)
php tools/clean.php --dry-run

# Delete with confirmation prompt
php tools/clean.php

# Delete without confirmation
php tools/clean.php --force

# Remove only .lock files, keep all data
php tools/clean.php --lock-only --force
```

## CLI Options

| Option | Default | Description |
|--------|---------|-------------|
| `--dry-run` | off | Show what would be deleted without deleting |
| `--force` | off | Delete without confirmation prompt |
| `--collections=a,b` | all | Only clean specified collections (comma-separated) |
| `--keep=a,b` | `settings,users` | Skip specified collections |
| `--all` | off | Include `settings` and `users` (overrides `--keep`) |
| `--lock-only` | off | Remove only `.lock` files, keep `.json` data |
| `--help` | — | Show help message |

Options can be combined:

```bash
php tools/clean.php --collections=posts,pages --force
php tools/clean.php --lock-only --dry-run
php tools/clean.php --all --force
```

## Protected Collections

By default, `settings` and `users` are **excluded** from cleaning to prevent accidental data loss:

- **settings** — contains `config.json` (site configuration)
- **users** — contains user accounts required for login

To override this protection, use `--all` or explicitly target the collection with `--collections=settings`.

## How It Works

1. **Discover** — scans subdirectories of `content/` (or uses `--collections` list).
2. **Filter** — excludes protected collections unless `--all` is set.
3. **Scan** — finds `.json` and `.lock` files in each collection (or only `.lock` with `--lock-only`).
4. **Preview** — displays a summary: file counts per collection and total size.
5. **Confirm** — asks for confirmation (skipped with `--force` or `--dry-run`).
6. **Delete** — removes files and reports results.

Files like `.gitkeep` and non-JSON files are never touched.

## Example Output

### Dry run (default collections)

```
Mantra CMS — Content Cleaner
----------------------------------------
Mode: DRY RUN (no files will be deleted)

  categories/  5 json, 5 lock
  pages/  5 json, 5 lock
  posts/  50 json, 50 lock

Total: 120 files (93.8 KB)

Dry run complete. No files were deleted.
```

### Lock-only cleanup

```
Mantra CMS — Content Cleaner
----------------------------------------
Mode: Lock files only

  categories/  5 lock
  pages/  5 lock
  posts/  50 lock

Total: 60 files (0 B)

Deleted 60 files.
Done.
```

## Exit Codes

| Code | Meaning |
|------|---------|
| `0` | Success (or dry run / abort) |
| `1` | One or more files could not be deleted |

## Notes

- Always run `--dry-run` first on production data to verify what will be removed.
- After cleaning, the CMS will show empty content lists. Use `php tools/seed.php` to regenerate test data.
- If no users exist after cleaning with `--all`, the CMS will redirect to `install.php` on next visit.
- Lock files (`.json.lock`) are created by the atomic write system (`FileIO`). Removing them is safe when the CMS is not actively serving requests.
