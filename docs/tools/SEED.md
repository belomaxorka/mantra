# Seed — Test Data Generator

Generate fake posts, pages, and categories for development and testing. Seeded documents are tagged with `"_seed": true` so they can be removed without affecting real content.

## Quick Start

```bash
# Generate defaults: 50 posts, 5 pages, 5 categories
php tools/seed.php

# Custom amounts
php tools/seed.php --posts=100 --pages=10

# Remove all seeded data, then regenerate defaults
php tools/seed.php --clear

# Remove all seeded data without generating new
php tools/seed.php --clear-only
```

## CLI Options

| Option | Default | Description |
|--------|---------|-------------|
| `--posts=N` | `50` | Number of posts to generate |
| `--pages=N` | `5` | Number of pages to generate |
| `--clear` | off | Delete all seeded documents before generating new ones |
| `--clear-only` | off | Delete all seeded documents and exit (no generation) |

Options can be combined:

```bash
php tools/seed.php --clear --posts=200 --pages=0
```

## What Gets Generated

### Categories (always 5)

Fixed set of categories: **News**, **Tutorials**, **Reviews**, **Opinion**, **Guides**. Existing categories with the same slug are skipped.

### Posts

Each post gets:

| Field | Value |
|-------|-------|
| `title` | Random template-based title ("How to build APIs", "5 ways to improve your caching", etc.) |
| `slug` | Derived from title via `slugify()`, uniqueness enforced |
| `content` | 2-5 paragraphs of lorem ipsum wrapped in `<p>` tags |
| `excerpt` | One random sentence |
| `status` | 75% `published`, 25% `draft` |
| `category` | Random category slug (some posts have no category) |
| `author` / `author_id` | First user found in the database |
| `created_at` | Random date within the last 90 days |
| `updated_at` | Random date within the last 30 days (never before `created_at`) |
| `_seed` | `true` |

### Pages

Each page gets a title from a predefined list (About us, Contact, FAQ, etc.). First 5 pages are marked `show_in_navigation = true`.

## How Clearing Works

The `--clear` and `--clear-only` flags scan `content/posts/`, `content/pages/`, and `content/categories/` for JSON files containing `"_seed": true`. Only those files (and their `.lock` companions) are deleted. Documents without the `_seed` flag are never touched.

## Example Output

```
Mantra CMS — Data Seeder
----------------------------------------
Generating 5 categories... done.
Generating 50 posts... done.
Generating 5 pages... done.

Summary:
  Categories: 5 total
  Posts: 50 total (50 seeded)
  Pages: 5 total (5 seeded)
Done.
```

## Notes

- The seeder writes documents through `Database::write()`, so all schema validation, defaults, and timestamps are applied normally.
- Slugs are guaranteed unique within a single run and checked against existing documents in the database.
- Running the seeder multiple times without `--clear` adds more documents (slugs are deduplicated with numeric suffixes).
- `config.json` files inside collection directories are never touched during clearing.
