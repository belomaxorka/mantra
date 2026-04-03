# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

This repository contains **Mantra CMS v1.1.0**, a **flat-file PHP CMS** (no Composer in production, zero runtime dependencies) with:

- JSON/Markdown file persistence under `content/`
- A **module system** (`modules/*`) with types, capabilities, dependencies, and settings
- An **event/hook bus** (`core/classes/HookManager.php`) — priority-based pub/sub
- A **router** with global and per-route middleware (`core/classes/Router.php`)
- A **theme engine** with template hierarchy and partials (`themes/*`)
- An **admin panel framework** with CRUD scaffolding (`core/classes/Admin/ContentPanel.php`)
- **Role-based permissions** with ownership enforcement (`core/classes/PermissionRegistry.php`)
- **Lazy service container** in `Application` (`provide()` / `service()`)
- Centralized **logging** (`core/classes/Logger.php`) and a PHP **ErrorHandler** (`core/classes/ErrorHandler.php`)

**PHP requirement:** 8.1.0+ (enforced in `core/bootstrap.php`)

## Common commands

### Run locally

```bash
php -S 127.0.0.1:8000 index.php
```

- `http://127.0.0.1:8000/` — public site
- `http://127.0.0.1:8000/admin` — admin panel
- `http://127.0.0.1:8000/install.php` — installer (first run only)

### Tests / lint

```bash
composer install              # install dev dependencies (phpunit, php-cs-fixer)
composer test                 # run PHPUnit (phpunit.xml)
composer lint                 # check code style (php-cs-fixer, dry-run)
composer fix                  # auto-fix code style
```

**Test matrix (CI):** PHP 8.1, 8.2, 8.3, 8.4, 8.5 — see `.github/workflows/ci.yml`.

**Test files** live in `tests/`:
- `MantraTestCase.php` — base test class
- `DatabaseTest.php`, `DatabaseSchemaTest.php`, `SchemaMigrationTest.php`
- `FileIOTest.php`, `JsonCodecTest.php`, `JsonStorageDriverTest.php`, `MarkdownStorageDriverTest.php`
- `ConfigSettingsTest.php`, `ModuleSettingsTest.php`, `ViewTest.php`

### Seed test data

```bash
php tools/seed.php
```

## Commit conventions

- Use **Conventional Commits**.
- Always include a **description/body** in commit messages (why the change was made + what it affects), not just the subject line.

Example:

```text
fix(url): normalize base_url slashes

Normalize both forward and back slashes when composing URLs.
Prevents "//admin" and "\\admin" when site_url is misconfigured.
```

## High-level architecture

### Entry points

- `index.php` — main web entrypoint.
  - Requires `core/bootstrap.php`.
  - Checks whether any users exist under `content/users/*.json` and redirects to `install.php` when missing.
  - Boots `Application::getInstance()->run()`.
- `install.php` — standalone installer.
  - Creates directories under `content/`, `storage/`, `uploads/`.
  - Writes configuration to `content/settings/config.json` via `Config::buildInstallConfig()`.
  - Creates the first admin user via `Database` + `Auth::hashPasswordStatic()`.

### Bootstrap & constants

`core/bootstrap.php` is **required for all entry points** (web, install, CLI). Do not call core classes directly without bootstrapping.

**Boot sequence:**
1. PHP version check (>= 8.1.0)
2. Define `MANTRA_PROJECT_INFO` (name, version, github, release_date)
3. Define `MANTRA_CLI` (bool)
4. Define path constants: `MANTRA_ROOT`, `MANTRA_CORE`, `MANTRA_MODULES`, `MANTRA_CONTENT`, `MANTRA_STORAGE`, `MANTRA_THEMES`, `MANTRA_UPLOADS`
5. Pre-load PSR LoggerInterface, FileIO, JsonCodec, Config (before autoloader)
6. `Config::bootstrap()` → merged config into `$GLOBALS['MANTRA_CONFIG']`
7. Define `MANTRA_DEBUG` (from config)
8. Register PSR-4 autoloader (`core/classes/` → class name)
9. Load `core/helpers.php` (global functions)
10. Register `ErrorHandler` (logs to `php` channel)

**Single source of truth** for settings is `content/settings/config.json` (see `core/classes/Config.php`).

### Application lifecycle

`core/classes/Application.php` is a singleton that orchestrates the request lifecycle:

1. `loadConfig()` — reads `$GLOBALS['MANTRA_CONFIG']` from bootstrap.
2. `registerCoreServices()` — registers lazy services: `request`, `session`, `response`, `db`, `view`, `translator`, `auth`, `clock`.
3. `setupEnvironment()` — timezone, `display_errors`, session start.
4. `run()`:
   1. Clean old logs if needed (daily).
   2. Start output compression (gzip via `zlib.output_compression` if enabled).
   3. Create `HookManager`.
   4. Create `ModuleManager`, load enabled modules.
   5. Fire `system.init`.
   6. Create `Router`.
   7. Fire `routes.register` (modules attach routes).
   8. Register core routes via `PageController` — `/`, `/blog`, `/post/{slug}`, `/{slug}`.
   9. `dispatch()` the request.
   10. Fire `system.shutdown`.

**Service container:**
```php
// Register (lazy — callable invoked on first access, then cached)
app()->provide('myservice', fn() => new MyService());

// Resolve
$svc = app()->service('myservice');

// Check existence
app()->hasService('myservice'); // bool

// Shortcut accessors for core services:
app()->request()     // Http\Request
app()->session()     // Http\Session
app()->response()    // Http\Response
app()->db()          // Database
app()->view()        // View
app()->translator()  // TranslationManager
app()->auth()        // Auth
app()->hooks()       // HookManager
app()->modules()     // ModuleManager
app()->router()      // Router
app()->config($key)  // Config value (dot-path)
```

### Global helper functions

Defined in `core/helpers.php`:

| Helper | Description |
|---|---|
| `app()` | Application singleton |
| `config($key, $default)` | Get config value (works before Application exists) |
| `logger($channel)` | Get Logger by channel (default `'app'`) |
| `clock()` | Clock service — date/time formatting |
| `e($value)` / `sanitize($value)` | XSS escaping (`htmlspecialchars` + `strip_tags`) |
| `t($key, $params)` | Translation shorthand |
| `base_url($path)` | Build URL relative to site base |
| `partial($name, $params)` | Render a template partial (without layout) |
| `abort($code, $message)` | Show error page and halt |
| `slugify($text)` | URL-friendly slug (with Cyrillic transliteration) |

### Hooks

See `docs/HOOKS.md` for complete hook system reference.

- `core/classes/HookManager.php` provides a priority-based pub/sub system.
  - `register($hookName, $callback, $priority=10)`
  - `fire($hookName, $data=null, $context=null)` (callbacks may transform `$data`, receive read-only `$context`)
- `core/classes/HookRegistry.php` documents hook contracts (50+ hooks defined). Modules register hooks via `HookRegistry::define()`.
- Browse all hooks at `/admin/hooks` (admin only).

**System hooks:**
- `system.init` — system initialized, modules loaded
- `system.shutdown` — before shutdown

**Routing hooks:**
- `routes.register` — register routes on the router

**Content hooks (PageController):**
- `page.home.query`, `page.home.posts`, `page.home.data`
- `page.blog.query`, `page.blog.posts`, `page.blog.data`
- `page.post.query`, `page.post.loaded`, `page.post.data` (aliased as `post.single.data`)
- `page.single.query`, `page.single.loaded`, `page.single.data`
- `content.saved` — after create/update
- `content.deleted` — after delete
- `view.render` — post-process rendered HTML

**Public theme hooks:**
- `theme.head` — add content to `<head>`
- `theme.body.start` — add content after `<body>`
- `theme.footer` — add scripts before `</body>`
- `theme.body.end` — add content before `</body>`
- `theme.navigation` — add items to main navigation menu
- `theme.footer.links` — add links to footer

**Admin hooks:**
- `admin.sidebar` — add items to admin sidebar menu
- `admin.quick_actions` — add quick action buttons to dashboard
- `admin.head` — add content to admin `<head>`
- `admin.footer` — add scripts to admin footer
- `admin.posts.edit.sidebar` — post edit form sidebar
- `admin.posts.list.columns.head` / `admin.posts.list.columns.body` — post list columns

**Permission hooks:**
- `permissions.register` — register module permissions

**Content panel hooks (per-collection):**
- `admin.{collection}.edit.data` — modify data before edit form
- `admin.{collection}.form_data` — modify submitted form data

### Modules

Modules live in `modules/<name>/` with a `module.json` manifest and a main class `<Name>Module.php`.

**Module system classes** (`core/classes/Module/`):
- `ModuleInterface` — contract: `init()`, `getManifest()`, `getId()`, `onEnable()`, `onDisable()`, `onUninstall()`
- `Module` — base class with helper methods (see below)
- `ModuleManager` — loads enabled modules, resolves dependencies, detects cycles
- `ModuleType` — constants: `CORE`, `FEATURE`, `ADMIN`, `INTEGRATION`, `THEME`, `UTILITY`, `CUSTOM`
- `ModuleCapability` — constants: `ROUTES`, `HOOKS`, `CONTENT_TYPE`, `ADMIN_UI`, `SETTINGS`, `PARTIALS`, `TEMPLATES`, `TRANSLATIONS`, `API`, `CLI`, `MIDDLEWARE`, `ASSETS`
- `ModuleSettings` — schema-driven per-module settings stored at `content/settings/<module>.json` with schema at `modules/<module>/settings.schema.php`
- `ModuleValidator` — validates module IDs, manifests, and directory structure
- `BaseAdminModule` — base for admin-focused modules (auto-registers sidebar items, quick actions)
- `ContentAdminModule` — CRUD scaffolding base (auto-registers 6 CRUD routes for a collection)

**Module base class API** (`Module`):

```php
// Hooks
$this->hook($hookName, $callback, $priority);
$this->fireHook($hookName, $data, $context);

// Services
$this->provide($name, $provider);    // register a service
$this->config($key, $default);       // read config
$this->view($template, $data);       // render template
$this->settings();                   // ModuleSettings instance
$this->log($level, $message, $ctx);  // logger with module context

// Middleware (deferred until router is ready)
$this->middleware($pattern, $callback, $priority);

// Routes (direct — only call during routes.register)
$this->route($method, $pattern, $callback);

// Assets
$this->asset($path);                 // asset URL with ?v=version
$this->enqueueStyle($path);          // CSS → theme.head hook
$this->enqueueScript($path);         // JS → theme.footer hook
$this->enqueueAdminStyle($path);     // CSS → admin.head hook
$this->enqueueAdminScript($path);    // JS → admin.footer hook
$this->addInlineStyle($css);         // inline CSS → theme.head
$this->addInlineScript($js);         // inline JS → theme.footer
$this->addAdminInlineStyle($css);    // inline CSS → admin.head
$this->addAdminInlineScript($js);    // inline JS → admin.footer

// File system
$this->getPath();                    // module directory path
$this->getUrl();                     // web-accessible path
$this->getBaseUrl();                 // full URL with base_url
$this->loadFile($filename);          // require_once from module dir
$this->hasSettings();                // has settings.schema.php?
$this->hasTranslations();            // has lang/ dir?
$this->getViewsPath();              // views/ dir path or null

// Metadata
$this->getId();
$this->getName();
$this->getVersion();
$this->getDescription();
$this->getType();                    // ModuleType constant
$this->getCapabilities();           // array of ModuleCapability
$this->hasCapability($cap);
$this->isDisableable();             // false for CORE type
$this->isDeletable();               // false for CORE type
```

**Included modules:**

| Module | Type | Default | Description |
|---|---|---|---|
| `admin` | core | enabled | Admin panel — cannot be disabled |
| `categories` | feature | disabled | Category CRUD, post associations, public category pages |
| `seo` | utility | disabled | Meta tags, Open Graph, Twitter Cards, breadcrumbs |
| `analytics` | integration | disabled | Google Analytics, Yandex Metrika |

### Admin panel system

Admin panels are auto-discovered from `modules/admin/panels/<name>/`. Each panel directory contains:
- `panel.json` — manifest (sidebar item, quick actions, `require_role`)
- `<Name>Panel.php` — panel class
- `views/` — panel templates
- `lang/` — panel translations

**Built-in panels:** `dashboard`, `pages`, `posts`, `users`, `uploads`, `permissions`, `settings`, `hooks`.

**Base classes** (`core/classes/Admin/`):

| Class | Purpose |
|---|---|
| `AdminPanelInterface` | Contract: `init()`, `registerRoutes()`, `getSidebarItem()`, `getQuickActions()` |
| `AdminPanel` | Abstract base — `renderView()`, `renderAdmin()`, `requirePermission()`, `requireAdmin()`, `verifyCsrf()`, `getUser()`, `auth()`, `db()`, `redirectAdmin()`, `hook()`, `fireHook()`, `asset()` |
| `ContentPanel` | CRUD scaffolding — extends `AdminPanel`, provides `listItems()`, `newItem()`, `createItem()`, `editItem()`, `updateItem()`, `deleteItem()`, automatic pagination (25/page), ownership checks, schema registration |

**ContentPanel abstract methods** (must implement):
```php
abstract public function getContentType();     // e.g. 'pages'
abstract public function getCollectionName();   // e.g. 'pages'
abstract public function getDefaultItem();      // array of defaults for new item
abstract public function extractFormData($request); // Request → data array
```

**ContentPanel optional overrides:** `getAdminPath()`, `getListTemplate()`, `getEditTemplate()`, `getDomain()`, `getPermissionPrefix()`, `getPermissionFlags()`, `generateId()`, `ensureSlug()`, `checkOwnership()`.

### Routing

`core/classes/Router.php`:

```php
$router->get($pattern, $callback);    // GET route
$router->post($pattern, $callback);   // POST route
$router->any($pattern, $callback);    // Any method

// Per-route middleware (chained after last added route)
$router->get('/admin/settings', $handler)->middleware($authCheck);

// Global middleware (pattern-based, priority-sorted)
$router->addGlobalMiddleware('/admin/*', $authCheck, $priority);
// Patterns: '*' (all), '/admin/*' (prefix), '/login' (exact)
// Return false from callback to halt the request.
```

Route callbacks: callables or `"ModuleName:method"` strings.
Patterns support `{param}` path parameters → passed as array to callback.

**Route registration order:**
1. Module routes via `routes.register` hook (e.g., `/admin/*`)
2. Core public routes via `PageController` (e.g., `/`, `/{slug}`, `/post/{slug}`, `/blog`)

### Views / themes

See `docs/VIEWS.md` for complete API reference.

`core/classes/View.php` renders templates with smart fallback logic:

1. **Theme template** (first priority): `themes/<active_theme>/templates/<template>.php`
2. **Module template** via `"module:template"` syntax (explicit)
3. **Module template** via `_module` data parameter (automatic fallback)

Content templates are automatically wrapped in `layout.php` (unless using module template syntax).
Module templates with `array('layout' => true)` option are wrapped in site layout.
After rendering, output is filtered through the `view.render` hook.

**Template hierarchy** (resolved in order of specificity):

- Pages: `page-{template}.php` > `page-{slug}.php` > `page.php`
- Posts: `post-{template}.php` > `post-{category}.php` > `post-{slug}.php` > `post.php`

**Partials** (rendered without layout wrapping):
- Theme: `themes/{theme}/templates/partials/{name}.php`
- Theme override of module partial: `themes/{theme}/templates/partials/{module}/{partial}.php`
- Module: `modules/{module}/views/partials/{partial}.php`
- Usage: `<?php echo partial('sidebar'); ?>` or `<?php echo partial('seo:breadcrumbs', $params); ?>`

### HTTP layer

Classes in `core/classes/Http/`:

**Request** (`Http\Request`):
- `method()`, `uri()`, `path()` — normalized request path (strips script base)
- `query($key, $default)`, `post($key, $default)` — supports dot-path keys (e.g. `'site.url'`)
- `postTrimmed($key, $default)` — trimmed POST value
- `header($name, $default)` — HTTP header by name
- `input($key, $default)` — unified: reads JSON body or POST depending on content type
- `json($key, $default)`, `jsonBody()` — parsed JSON request body
- `file($key)` — uploaded file (`$_FILES`)
- `ip()` / `clientIp()` — real client IP with trusted proxy support
- `isJson()`, `acceptsJson()`, `contentType()`
- `isHttps()` (static) — HTTPS detection with proxy/config fallback
- `server($key, $default)` — `$_SERVER` access

**Response** (`Http\Response`): `redirect($url, $code)`.

**Session** (`Http\Session`): `start()`, `get()`, `set()`, `has()`, `delete()`, `destroy()`, `regenerate()`, `all()`.

**Cookie** (`Http\Cookie`): Cookie management.

### Pagination

`core/classes/Paginator.php` is a standalone value object:

```php
$paginator = new Paginator($totalItems, $perPage, $currentPage);
$paginator->offset();        // for Database::query() offset
$paginator->perPage();       // items per page
$paginator->totalPages();    // total pages
$paginator->hasPages();      // more than 1 page?
$paginator->hasPrevious();   // has previous page?
$paginator->hasNext();       // has next page?
$paginator->pages($window);  // page numbers for rendering
```

- Public pages: `config('content.posts_per_page')` (default 10).
- Admin panels (ContentPanel): 25 items per page.
- `Database::count($collection, $filters)` counts docs without loading full results.
- Pagination partial renders Bootstrap 5 nav.

See `docs/PAGINATION.md` for full API reference.

### Persistence (flat-file DB)

`core/classes/Database.php` stores documents at `content/<collection>/<id>.json` (or `.md`).

**API:**
```php
$db = app()->db();

// CRUD
$db->read($collection, $id);                   // single document (null if missing)
$db->read($collection);                        // all documents in collection
$db->write($collection, $id, $data);           // create or update (validates, adds timestamps)
$db->delete($collection, $id);                 // delete
$db->exists($collection, $id);                 // bool

// Query
$db->query($collection, $filters, $options);   // filters: ['status' => 'active']
                                                 // options: sort, order, limit, offset
$db->count($collection, $filters);              // count (fast path if no filters)
$db->listIds($collection);                      // IDs without reading contents
$db->generateId();                               // crypto-secure unique ID

// Schemas
$db->registerSchema($collection, $schemaPath);  // register schema for collection
```

**Automatic behaviors on `write()`:**
- Sanitizes input via `SchemaValidator::sanitize()`
- Applies schema defaults for missing fields
- Validates against schema fields (if defined) — throws `SchemaValidationException`
- Preserves `created_at` on updates (immutable); sets on creates
- Sets `updated_at` to current timestamp
- Sets `schema_version`

**Storage drivers:**
- `Storage\JsonStorageDriver` — JSON files
- `Storage\MarkdownStorageDriver` — Markdown with frontmatter (pages/posts only, when `content.format` = `'markdown'`)
- Both use `Storage\FileIO` for atomic writes: exclusive locking + temp-file + rename

**JSON storage safety:**
- `JsonCodec` (`core/classes/JsonCodec.php`) — encoding/decoding with `JsonCodecException`
- `FileIO` (`core/classes/Storage/FileIO.php`) — `readLocked()` (shared lock), `writeAtomic()` (exclusive lock), per-document `.lock` files

#### Document schemas & migrations

Schemas are defined **per-panel or per-module** (not in `core/schemas/` — that directory is for core collections if needed):
- `modules/admin/panels/pages/schema.php`
- `modules/admin/panels/posts/schema.php`
- `modules/admin/panels/users/schema.php`
- `modules/admin/panels/uploads/schema.php`
- `modules/categories/schema.php`

Panels register schemas in their constructors via `app()->db()->registerSchema($collection, $path)`.

Each schema file returns an array:

```php
return array(
    'version' => 1,                          // schema version
    'defaults' => array('status' => 'draft'), // missing keys added on read
    'fields' => array(                       // validation rules (optional)
        'title' => array('type' => 'string', 'required' => true),
    ),
    'migrate' => function($doc, $from, $to) { ... },  // optional
);
```

**Lazy migration on read:** when `Database` reads a document, it:
1. Runs `migrate()` if `schema_version` < current `version`
2. Applies `defaults` for any still-missing fields
3. Writes back if changes were made (atomic + locked)

**Guidelines:**
- Prefer **additive changes** (add `defaults`) over breaking migrations.
- Only bump `version` when you need to transform existing documents.
- Migrations must be **idempotent** and never delete unknown keys.
- Use `defaults` for simple new fields; use `migrate` for renames, type changes, field moves.

### Auth & Permissions

`core/classes/Auth.php` — session-based authentication:
- `login($username, $password)` — verifies password, checks status, auto-rehashes if algorithm changed, regenerates session ID
- `logout()` — clears session, destroys
- `check()` — is user logged in?
- `user()` — current user array (with `_id`)
- `hasRole($role)` — check current user's role
- `hashPassword($password)` / `hashPasswordStatic($password)` — hash with configured algorithm
- `generateCsrfToken()` — generate/reuse CSRF token
- `verifyCsrfToken($token)` — verify with `hash_equals`

**Roles:** `admin`, `editor`, `viewer`.

**PermissionRegistry** (`core/classes/PermissionRegistry.php`):
- Registered as lazy service: `app()->service('permissions')`
- Core permissions registered in constructor; modules register via `permissions.register` hook
- Custom per-role overrides stored in `config('permissions.roles')`, managed at `/admin/permissions`
- `hasPermission($role, $permission)` returns `true` (full access), `'own'` (ownership check needed), or `false`

**Module permission registration:**
```php
$this->hook('permissions.register', function($registry) {
    $registry->registerPermissions(array(
        'comments.view'   => 'View comments',
        'comments.delete' => 'Delete comments',
    ), 'Comments');
    $registry->addRoleDefaults('editor', array('comments.view', 'comments.delete'));
    return $registry;
});
```

**Ownership enforcement:** `.own` suffix permissions (e.g. `posts.edit.own`) — `ContentPanel` automatically calls `User::canEdit()` to verify the current user is the content author.

### Logging & error handling

- `logger($channel)` helper creates `Logger` instances (cached per channel).
- Logs written to `storage/logs/{channel}-YYYY-MM-DD.log` with daily rotation.
- Log level controlled by `config('logging.level')` (PSR-3 levels).
- Auto-cleanup: logs older than `logging.retention_days` (default 30) deleted daily.
- `ErrorHandler` (`core/classes/ErrorHandler.php`) — registered in bootstrap, logs to `php` channel, handles errors/exceptions/shutdown, cleans ob buffers, debug vs production output.

### Translation system

`core/classes/TranslationManager.php` — multi-language i18n:
- Built-in languages: English (`en`), Russian (`ru`)
- Language files: `core/lang/{lang}.php`, `modules/{module}/lang/{lang}.php`, `themes/{theme}/lang/{lang}.php`
- `t($key, $params)` — translate with `{placeholder}` interpolation
- Modules register translation domains: `registerDomain($domain, $path)`
- Fallback chain: current locale → `fallback_locale` → key itself

### Other core classes

| Class | Path | Purpose |
|---|---|---|
| `Clock` | `core/classes/Clock.php` | Date/time service — `now()`, `timestamp()`, `formatDate()`, `formatTime()`, `formatDatetime()`, `ago()` |
| `SchemaValidator` | `core/classes/SchemaValidator.php` | Data validation against schema rules, `validate()`, `validateOrThrow()`, `sanitize()` |
| `MarkdownConverter` | `core/classes/MarkdownConverter.php` | Markdown → HTML conversion |
| `ContentTypeRegistry` | `core/classes/ContentTypeRegistry.php` | Registry for custom content types |
| `Language` | `core/classes/Language.php` | Language metadata |
| `User` | `core/classes/User.php` | User model with `canEdit()` ownership check |
| `ConfigSettings` | `core/classes/ConfigSettings.php` | Settings UI management (uses `core/config.settings.schema.php`) |
| `Psr\Log\LoggerInterface` | `core/classes/Psr/Log/` | PSR-3 logger interface (bundled, no Composer) |

## Documentation index

| Document | Description |
|---|---|
| [docs/HOOKS.md](docs/HOOKS.md) | Hook system architecture and API reference |
| [docs/ADMIN_PANELS.md](docs/ADMIN_PANELS.md) | Admin panel creation guide and ContentPanel API |
| [docs/VIEWS.md](docs/VIEWS.md) | Template rendering, partials, and theme override system |
| [docs/PAGINATION.md](docs/PAGINATION.md) | Paginator API and integration examples |

## CI pipeline

`.github/workflows/ci.yml`:
1. **Code Style** job — runs `php-cs-fixer`, auto-commits fixes on push
2. **Tests** job — matrix of PHP 8.1–8.5, installs Composer deps, runs PHPUnit
