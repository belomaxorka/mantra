# Module system (Mantra CMS)

This document describes how the Mantra CMS module system works: how modules are loaded, how they hook into the lifecycle, how routing and views work, and how module settings + i18n are organized.

> Repo is intentionally “no Composer”: modules are plain PHP classes + a JSON manifest.

---

## 1) What is a module?

A **module** is a folder under:

- `modules/<moduleId>/`

A module is enabled via config:

- `content/settings/config.json` → `modules.enabled: ["admin", "pages", ...]`

Each module usually contains:

- `module.json` (manifest)
- `modules/<moduleId>/<ModuleIdCapitalized>Module.php` (main class)
- optional: `views/` (module templates)
- optional: `lang/` (translations)
- optional: `settings.schema.php` (schema-driven module settings)

---

## 2) Module manifest (`module.json`)

**Path**: `modules/<moduleId>/module.json`

Loaded by `core/ModuleManager.php`.

Minimal example:

```json
{
  "name": "editor",
  "version": "1.0.0",
  "description": "WYSIWYG editor integration",
  "dependencies": ["users"]
}
```

Notes:

- `name` is treated as the module id.
- `dependencies` is optional. If present, it must be an array of module IDs.
- `description` supports a “Variant A” shape: it may be an object keyed by locale.
  - The locale resolution is performed in `core/ModuleManager.php`.

---

## 3) Module class (`<Name>Module.php`)

**Path convention**:

- `modules/<moduleId>/<Ucfirst(moduleId)>Module.php`
- class name must be: `<Ucfirst(moduleId)>Module`

Base class:

- `core/Module.php`

A module typically overrides:

- `init()` — register hooks and/or routes

Example skeleton:

```php
class ExampleModule extends Module {
  public function init() {
    $this->hook('routes.register', array($this, 'registerRoutes'));
  }

  public function registerRoutes($data) {
    $router = $data['router'];
    $router->get('/hello', array($this, 'hello'));
    return $data;
  }

  public function hello() {
    echo 'Hello from ExampleModule';
  }
}
```

The base `Module` class exposes helpers:

- `$this->hook($name, $callback, $priority = 10)`
- `$this->fireHook($name, $data = null)`
- `$this->route($method, $pattern, $callback)`
- `$this->view($template, $data = [])`

---

## 4) Lifecycle: when modules are loaded

Main application lifecycle is orchestrated in:

- `core/Application.php`

High-level flow (`Application::run()`):

1) `HookManager` created (`core/HookManager.php`)
2) `ModuleManager` created and loads enabled modules
3) Hook fired: `system.init`
4) Router created (`core/Router.php`)
5) Hook fired: `routes.register` (modules register routes here)
6) Router dispatches the request
7) Hook fired: `system.shutdown`

Module loading occurs here:

- `core/ModuleManager.php::loadModules()`
- `core/ModuleManager.php::loadModule($moduleId)`

Important behavior in ModuleManager:

- Validates module IDs (prevents path traversal): only `[a-z0-9_-]`
- Loads dependencies first
- Detects cyclic dependencies and throws an exception

---

## 5) Hooks / event bus

Hook bus:

- `core/HookManager.php`

API:

- `register($hookName, $callback, $priority = 10)`
- `fire($hookName, $data = null)`

Behavior:

- Hooks are stored per name.
- Lower priority runs first.
- `fire()` passes `$data` through callbacks: if callback returns non-null, it becomes the next `$data`.

Common hooks used by modules:

- `system.init`
- `routes.register`
- `view.render` (filter the final HTML)
- `system.shutdown`

Admin-specific hooks:

- `admin.sidebar` (build sidebar tree)

---

## 6) Routing

Router implementation:

- `core/Router.php`

Supported methods:

- `$router->get($pattern, $callback)`
- `$router->post($pattern, $callback)`
- `$router->any($pattern, $callback)`

Route patterns:

- Supports `{param}` named parameters, e.g. `/admin/{module}`

Callbacks:

- PHP callable (e.g. `array($this, 'method')`)
- String in the form: `"module:method"`
  - Resolved via `Router::executeControllerAction()` which calls the module instance method.

Middleware:

- `->middleware($callable)` attaches middleware to the last added route.

---

## 7) Views / templates

View engine:

- `core/View.php`

Template resolution order:

1) Theme template: `themes/<active_theme>/templates/<template>.php`
2) Module template: when using `"module:tpl"` syntax, resolves to `modules/<module>/views/<tpl>.php`

Rendering:

- `View::render()` echoes output.
- `View::fetch()` returns rendered output as a string.

After rendering, content is filtered through:

- `view.render` hook

---

## 8) Module settings (schema-driven)

Module settings storage:

- JSON file per module: `content/settings/<module>.json`

Implementation:

- `core/ModuleSettings.php`
- helper: `module_settings($module)` in `core/helpers.php`

Schema:

- `modules/<module>/settings.schema.php` returning an array:
  - `version` (int)
  - optional `migrate($data, $from, $to)` callable
  - `tabs` array, each with `fields`

Field example:

```php
return array(
  'version' => 1,
  'tabs' => array(
    array(
      'id' => 'general',
      'title' => array('key' => 'editor.settings.general', 'fallback' => 'General'),
      'fields' => array(
        array(
          'path' => 'enabled',
          'type' => 'toggle',
          'title' => array('key' => 'editor.settings.enabled', 'fallback' => 'Enabled'),
          'default' => true,
        )
      )
    )
  )
);
```

Behavior (`ModuleSettings::load()`):

- reads JSON via `JsonFile`
- applies migrations if `schema_version` < `schema['version']`
- applies defaults for fields that specify `default`
- if changes were applied, writes file back (atomic + backups)

Nested paths:

- Schema uses dot-paths like `editor.toolbar.mode`
- `ModuleSettings` provides nested getters/setters.

---

## 9) i18n / translations

Translator:

- `core/Language.php`
- helper `t()` in `core/helpers.php`

Lookup rules:

- Keys are namespaced: `<domain>.<key>`
- Domain determines where translations are loaded from:
  - Module domain: `modules/<module>/lang/<locale>.php`
  - Theme domain: `themes/<active_theme>/lang/<locale>.php`

Example translation file:

```php
<?php
return array(
  'editor.admin.title' => 'Editor',
  'editor.settings.enabled' => 'Enabled'
);
```

Admin uses i18n specs in many places:

- `['key' => '...', 'fallback' => '...']`

This is resolved in:

- `modules/admin/AdminModule.php` (see `resolveAdminString()`)

---

## 10) Admin integration (sidebar + unified settings)

Admin module:

- `modules/admin/AdminModule.php`

Sidebar:

- Built from hook `admin.sidebar` (modules or admin-submodules register items).

Unified settings:

- `/admin/settings?tab=general` → global config editing
- `/admin/settings?tab=<module>` → module settings based on `settings.schema.php`

Legacy module settings routes:

- `/admin/<module>/settings` redirects into the unified settings page.

---

## 11) Best practices for module authors

- Keep module IDs lowercase and alphanumeric with `_`/`-`.
- Register routes only through `routes.register`.
- Use hooks to integrate with core/other modules (avoid tight coupling).
- Prefer schema-driven settings (`settings.schema.php`) + `module_settings()`.
- Put translations in `modules/<module>/lang/<locale>.php` and use namespaced keys.

---

## References (code)

- Module loading: `core/ModuleManager.php`
- Module base class: `core/Module.php`
- Hooks: `core/HookManager.php`
- App lifecycle: `core/Application.php`
- Router: `core/Router.php`
- Views: `core/View.php`
- Module settings: `core/ModuleSettings.php`, `core/helpers.php::module_settings()`
- i18n: `core/Language.php`, `core/helpers.php::t()`
