# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

This repository contains **Mantra CMS**, a small **flat‑file PHP CMS** (no Composer) with:

- JSON-file persistence under `content/`
- A **module system** (`modules/*`) and an **event/hook bus** (`core/HookManager.php`)
- A simple **router** (`core/Router.php`)
- Basic **theme templates** under `themes/*`
- Centralized **logging** (`core/Logger.php`, `core/helpers.php`) and a PHP **ErrorHandler** (`core/ErrorHandler.php`)

## Common commands

## Commit conventions

- Use **Conventional Commits**.
- Always include a **description/body** in commit messages (why the change was made + what it affects), not just the subject line.

Example:

```text
fix(url): normalize base_url slashes

Normalize both forward and back slashes when composing URLs.
Prevents "//admin" and "\\admin" when site_url is misconfigured.
```


### Run locally

This is a plain PHP app meant to be run behind a web server.

- Point your web server document root at the repository root (`index.php`).
- If not installed, the app redirects to `install.php` (see `index.php`).

If you have PHP available, a quick dev server is:

```bash
php -S 127.0.0.1:8000 index.php
```

Then open:

- `http://127.0.0.1:8000/` (site)
- `http://127.0.0.1:8000/admin` (admin)
- `http://127.0.0.1:8000/install.php` (installer)

### Tests / lint

No test runner or linter configuration is present in the repository (no `composer.json`, `phpunit.xml`, etc.).

If you add tooling, prefer documenting the exact command(s) here.

## High-level architecture

### Entry points

- `index.php` is the main web entrypoint.
  - It requires `core/bootstrap.php`.
  - It checks whether any users exist under `content/users/*.json` and redirects to `install.php` when missing.
  - It boots `Application::getInstance()->run()`.
- `install.php` is a standalone installer.
  - Creates directories under `content/`, `storage/`, `uploads/`.
  - Writes configuration to `content/settings/config.json` via `Config::buildInstallConfig()`.
  - Creates the first admin user via `Database` + `Auth`.

### Bootstrap & configuration

- `core/bootstrap.php` is **required for all entry points** (web, install, CLI). Do not call core classes directly without bootstrapping.
- It defines path constants (e.g. `MANTRA_ROOT`, `MANTRA_CORE`, `MANTRA_CONTENT`, ...), loads config early, registers an autoloader, loads helpers, and registers the `ErrorHandler`.
- **Single source of truth** for settings is `content/settings/config.json` (see `core/Config.php`).
  - `Config::bootstrap()` is used during early bootstrap to avoid needing `Application`.
  - `config()` helper returns a `Config` instance for runtime reads/writes.

### Application lifecycle

`core/Application.php` orchestrates the request lifecycle:

1. Loads config (prefer `$GLOBALS['MANTRA_CONFIG']` from bootstrap).
2. Sets environment (timezone, `display_errors`, session start).
3. Creates `HookManager`.
4. Creates `ModuleManager` and loads enabled modules (`modules.enabled` from config).
5. Fires hooks:
   - `system.init`
   - `routes.register` (modules attach routes to the router here)
6. Creates `Router` and registers routes:
   - Module routes (via `routes.register` hook) - specific routes like `/admin/*`
   - Core routes (via `registerCoreRoutes()`) - public content routes like `/`, `/{slug}` (pages), `/post/{slug}`
7. `dispatch()`es the request.
8. Fires `system.shutdown`.

### Hooks

- `core/HookManager.php` provides a simple priority-based pub/sub system.
  - `register($hookName, $callback, $priority=10)`
  - `fire($hookName, $data=null)` (callbacks may transform `$data`)

Modules commonly hook:

- `system.init`
- `routes.register`
- `view.render`
- `system.shutdown`

**Public theme integration:**
- `theme.head` - Add content to `<head>`
- `theme.body.start` - Add content after `<body>`
- `theme.footer` - Add scripts before `</body>`
- `theme.body.end` - Add content before `</body>`
- `theme.navigation` - Add items to main navigation menu
- `theme.footer.links` - Add links to footer

**Admin integration:**
- `admin.sidebar` - Add items to admin sidebar menu
- `admin.quick_actions` - Add quick action buttons to dashboard
- `admin.head` - Add content to admin `<head>`
- `admin.footer` - Add scripts to admin footer

### Modules

- Modules live in `modules/<name>/`.
- Each module has a `module.json` manifest and a main class file:
  - `modules/<name>/<Name>Module.php` where class name is `<Name>Module`
- `core/ModuleManager.php`:
  - Loads enabled modules and their dependencies (from `module.json`).
  - Instantiates the module and calls `init()`.
- `core/Module.php` is the base class.
  - Provides helpers to register hooks and add routes.

Included modules (enabled by default in `core/Config.php`):

- `admin` - Admin panel for managing content and settings

**Example modules** (not enabled by default):
- `seo` - SEO optimization with meta tags, Open Graph, breadcrumbs (see `modules/seo/`)
- `analytics` - Google Analytics and Yandex Metrika integration (see `modules/analytics/`)
- `products` - Custom content type example with products (see `modules/products/`)
- `example-integration` - Demonstrates integration points (navigation, footer, admin sidebar) (see `modules/example-integration/`)

**Creating modules:**
See `EXTENSIBILITY.md` for complete guide on hooks, widgets, and custom content types.
See `docs/INTEGRATION_POINTS.md` for guide on integration points (navigation, footer, admin).

### Routing

- `core/Router.php` supports `get()`, `post()`, `any()`.
- Route callbacks can be:
  - callables
  - strings in the form `"module:method"` which dispatches to the module instance.
- Patterns support `{param}` path parameters.

**Route registration order:**
1. Module routes are registered first via the `routes.register` hook (e.g., `/admin/*`)
2. Core public routes are registered last via `PageController` (e.g., `/`, `/{slug}` for pages, `/post/{slug}` for posts)

This ensures modules can override default behavior when needed.

### Views / themes

- `core/View.php` renders templates with smart fallback logic.
  - **Theme templates** (first priority): `themes/<active_theme>/templates/<template>.php`
  - **Module templates** (fallback): `modules/<module>/views/<template>.php`
  - Content templates are automatically wrapped in `layout.php` (unless using module template syntax)
  - After rendering, output is filtered through the `view.render` hook.

**Template resolution order:**
1. Theme template (allows themes to override module defaults)
2. Module template via `"module:template"` syntax (explicit)
3. Module template via `_module` data parameter (automatic fallback)

**Architecture:**
- **Core** (`core/PageController.php`) handles routing and business logic for public pages
- **Themes** (`themes/*/templates/`) handle presentation and can override module templates
- **Modules** (`modules/*/views/`) provide default templates and are self-contained

**Template Hierarchy:**

Templates are resolved in order of specificity:

Pages: `page-{template}.php` > `page-{slug}.php` > `page.php`
Posts: `post-{template}.php` > `post-{category}.php` > `post-{slug}.php` > `post.php`

**Widget System:**

Widgets are reusable components embedded in templates:
- Theme widgets: `themes/{theme}/widgets/{name}.php`
- Module widgets: `modules/{module}/widgets/{name}.php`
- Usage: `<?php echo widget('sidebar'); ?>` or `<?php echo widget('module:widget', $params); ?>`
- Modules can provide widgets via `widget.render` hook

See `EXTENSIBILITY.md` for complete guide.

### Persistence (flat-file DB)

- `core/Database.php` stores JSON documents at:
  - `content/<collection>/<id>.json`
- Provides CRUD + `query()` over the in-memory collection read.

#### JSON storage safety

- Low-level JSON file I/O goes through `core/JsonFile.php`.
  - Uses per-document lock files (`<file>.lock`) to support parallel reads/writes.
  - Writes are atomic (tmp + rename) and create rotating backups (`.bak.1..bak.N`).
  - On corrupted JSON reads, it will try to recover from backups and log a warning.

#### Document schemas & migrations (`core/schemas/*.php`)

This CMS intentionally stores *dynamic* JSON documents. "Schemas" here are **compatibility rules** used to:

- add missing fields with defaults when new options are introduced
- migrate older document formats forward without deleting user data

Schemas are stored per-collection in:

- `core/schemas/<collection>.php` (e.g. `core/schemas/users.php`, `core/schemas/posts.php`)

Each schema file returns an array:

- `version` (int): current document schema version
- `defaults` (array): missing keys are added on read
- `migrate` (callable, optional): `function(array $doc, int $from, int $to): array`

**How upgrades work (lazy migration):**

- When `Database` reads a document (`read()` or `query()`), it loads the schema for that collection.
- If the document is missing keys from `defaults`, they are added.
- If `schema_version` is missing or less than `version`, `migrate()` is called (if present) and `schema_version` is bumped.
- If any changes were made, the document is written back using `JsonFile` (atomic + backup).

**How to change JSON structure safely:**

1. Update code to support both the old and new fields temporarily (if needed).
2. Edit `core/schemas/<collection>.php`:
   - bump `version`
   - update `defaults`
   - add/extend `migrate()` to transform old docs into the new structure
3. Once deployed, documents will be upgraded automatically the next time they are read.

**Guidelines:**

- Prefer **additive changes**:
  - Adding a new optional field: usually only update code.
  - Adding a new required field with a safe default: add it to `defaults`.
- Only **bump `version`** when you need to transform existing documents (rename/move/change meaning).
  - If you only add defaults and do not need transformations, you *can* keep the same version.
- Keep `defaults` backward-compatible:
  - choose defaults that won’t break existing behavior
  - avoid expensive computed defaults during read (do that at write time instead)
- Migrations should be **idempotent** (safe if run more than once).
- Never delete unknown keys in migrations (modules/user extensions may store extra fields).
- Keep migrations small and targeted: rename keys, fill defaults, move nested fields.
- If a migration cannot safely proceed, throw an exception to surface the problem.

**Recommended versioning pattern:**

- Start schemas at `version: 1`.
- When introducing a structural change, bump to `version: N+1` and implement a `migrate()` that upgrades from older versions.
- `migrate()` should handle *all* prior versions (`$from < N`) or step through versions internally.
- Do not rely on manual scripts: upgrades happen automatically when documents are read.

**When to use `defaults` vs `migrate`:**

- Use `defaults` for: new fields with a simple default (e.g. `status: 'draft'`).
- Use `migrate` for: renames (`login`->`username`), moving nested fields, splitting/merging fields, changing types.
- If a field can’t be safely defaulted (needs computed data), consider making it optional and populating it on next write.

**Pre-alpha note:** until the first public release, prefer `defaults` (additive) and avoid breaking migrations unless necessary.

**Troubleshooting:**

- If a JSON file is corrupted, `JsonFile` will try to recover from `.bak.*` and log a warning.
- If recovery and migration both fail, an exception will be logged by `ErrorHandler`.


Example (rename `login` -> `username`):

```php
<?php
return array(
  'version' => 2,
  'defaults' => array('status' => 'active'),
  'migrate' => function ($doc, $from, $to) {
    if ($from < 2 && isset($doc['login']) && !isset($doc['username'])) {
      $doc['username'] = $doc['login'];
      unset($doc['login']);
    }
    $doc['schema_version'] = 2;
    return $doc;
  }
);
```

> Note: the CMS is pre-alpha; schemas are still evolving. Prefer additive changes (defaults) until the first stable release.


### Auth

- `core/Auth.php` implements session-based login/logout.
- Users are stored in the `users` collection (`content/users/*.json`).
- CSRF token helpers exist (`generateCsrfToken()`, `verifyCsrfToken()`).

### Logging & error handling

- `core/helpers.php` exposes `logger($channel = 'app')` plus `log_message()` / `log_debug()`.
- Logs are written under `storage/logs/` with daily rotation (see `README.md` and `LOGGING_EXAMPLES.md`).
- `core/ErrorHandler.php` registers PHP error/exception/shutdown handlers early in `core/bootstrap.php` and logs to channel `php`.
