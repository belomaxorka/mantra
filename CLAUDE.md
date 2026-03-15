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
4. Creates `ModuleManager` and loads enabled modules (`enabled_modules` from config).
5. Fires hooks:
   - `system.init`
   - `routes.register` (modules attach routes to the router here)
6. Creates `Router` and `dispatch()`es the request.
7. Fires `system.shutdown`.

### Hooks

- `core/HookManager.php` provides a simple priority-based pub/sub system.
  - `register($hookName, $callback, $priority=10)`
  - `fire($hookName, $data=null)` (callbacks may transform `$data`)

Modules commonly hook:

- `system.init`
- `routes.register`
- `view.render`
- `system.shutdown`

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

- `admin` (depends on `users`)
- `pages`
- `media`
- `users`
- `editor`

### Routing

- `core/Router.php` supports `get()`, `post()`, `any()`.
- Route callbacks can be:
  - callables
  - strings in the form `"module:method"` which dispatches to the module instance.
- Patterns support `{param}` path parameters.

### Views / themes

- `core/View.php` renders templates.
  - Theme templates: `themes/<active_theme>/templates/<template>.php`
  - Module templates fallback: `modules/<module>/views/<tpl>.php` via `"module:tpl"` template naming.
  - After rendering, output is filtered through the `view.render` hook.

### Persistence (flat-file DB)

- `core/Database.php` stores JSON documents at:
  - `content/<collection>/<id>.json`
- Provides CRUD + `query()` over the in-memory collection read.

### Auth

- `core/Auth.php` implements session-based login/logout.
- Users are stored in the `users` collection (`content/users/*.json`).
- CSRF token helpers exist (`generateCsrfToken()`, `verifyCsrfToken()`).

### Logging & error handling

- `core/helpers.php` exposes `logger($channel = 'app')` plus `log_message()` / `log_debug()`.
- Logs are written under `storage/logs/` with daily rotation (see `README.md` and `LOGGING_EXAMPLES.md`).
- `core/ErrorHandler.php` registers PHP error/exception/shutdown handlers early in `core/bootstrap.php` and logs to channel `php`.
