# Modules (Mantra CMS)

This guide explains how to create a new **front-end module** that integrates with the **Admin panel** (sidebar + settings), and how module settings and routing work.

> This repo is intentionally **no Composer**: modules are plain PHP files + a `module.json` manifest.

---

## 0) Quick map (where things live)

- Modules: `modules/<moduleId>/...`
- Module manifest: `modules/<moduleId>/module.json`
- Module main class: `modules/<moduleId>/<Ucfirst(moduleId)>Module.php`
- Module views (optional): `modules/<moduleId>/views/*.php`
- Module translations (optional): `modules/<moduleId>/lang/<locale>.php`
- Module settings schema (optional): `modules/<moduleId>/settings.schema.php`
- Stored module settings (runtime): `content/settings/<moduleId>.json`

Admin panel module:

- `modules/admin/` (core admin module)
- Admin sidebar is built via hook: `admin.sidebar`
- Unified settings page: `/admin/settings?tab=...`

---

## 1) Choose a module id

Rules:

- lowercase
- allowed chars: `a-z0-9_-`
- examples: `blog`, `my-module`, `my_module`

The module id is also the folder name under `modules/`.

---

## 2) Create the module folder + manifest

Create:

`modules/<moduleId>/module.json`

Minimal manifest example:

```json
{
  "name": "example",
  "version": "1.0.0",
  "description": "Example module",
  "author": "You",
  "dependencies": []
}
```

Notes:

- `name` is treated as the module id.
- `dependencies` is optional; if present it must be an array of module ids.
- Some built-in modules use an optional `admin` policy block (e.g. non-disableable). If you omit it, the admin UI treats the module as disableable/deletable.

---

## 3) Create the module class

Create:

`modules/<moduleId>/<Ucfirst(moduleId)>Module.php`

The class name must be:

`<Ucfirst(moduleId)>Module`

Skeleton:

```php
<?php

class ExampleModule extends Module
{
    public function init()
    {
        // Register routes during app boot.
        $this->hook('routes.register', array($this, 'registerRoutes'));

        // Integrate into admin sidebar (optional).
        $this->hook('admin.sidebar', array($this, 'registerAdminSidebar'));
    }

    public function registerRoutes($data)
    {
        // Application fires routes.register with: array('router' => $router)
        $router = $data['router'];

        $router->get('/example', array($this, 'index'));
        return $data;
    }

    public function index()
    {
        echo 'Hello from ExampleModule';
    }

    public function registerAdminSidebar($items)
    {
        if (!is_array($items)) {
            $items = array();
        }

        $items[] = array(
            'id' => 'example',
            'title' => 'Example',
            'group' => 'Content',
            'url' => base_url('/admin/example'),
            'order' => 50,
        );

        return $items;
    }
}
```

Important behavior:

- In `routes.register`, the `$data` payload is an array with key `router`.
- Hook handlers should return the payload (or the modified value) so later handlers see the changes.

---

## 4) Enable the module

Enabled modules are configured in:

- `content/settings/config.json` → `modules.enabled`

Example:

```json
{
  "modules": {
    "enabled": ["admin", "users", "example"]
  },
  "schema_version": 1
}
```

Notes:

- `config.json` is stored as **overrides-only** (diff from `Config::defaults()`). It’s normal for it to be very small.

---

## 5) Add an Admin page for your module (optional)

Admin routes are dispatched by the Admin module using these routes (see `modules/admin/AdminModule.php`):

- `GET /admin/{module}`
- `ANY /admin/{module}/settings`

How dispatch works:

- When you visit `/admin/example`, AdminModule will look for a module instance named `example` and will try to call a conventional method (exact method names depend on the dispatcher implementation).

Practical recommendation in this codebase:

- Prefer using `admin.sidebar` to link to `/admin/<moduleId>`.
- In your module, implement an index handler method that the dispatcher expects.

If you need to see the exact method name contract, check:

- `modules/admin/AdminModule.php` methods: `dispatchModuleIndex()` and `dispatchModuleSettings()`.

---

## 6) Add module settings (optional)

If your module has settings, create:

`modules/<moduleId>/settings.schema.php`

Schema format (UI-driven):

- `version` (int)
- optional `migrate($data, $from, $to)` callable
- `tabs[]` containing `fields[]`
- each field can define:
  - `path` (dot-path)
  - `type` (input type used by admin UI)
  - `title` (string or i18n spec)
  - `default` (optional)

Example:

```php
<?php

return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => array('key' => 'example.settings.general', 'fallback' => 'General'),
            'fields' => array(
                array(
                    'path' => 'enabled',
                    'type' => 'toggle',
                    'title' => array('key' => 'example.settings.enabled', 'fallback' => 'Enabled'),
                    'default' => true,
                ),
            ),
        ),
    ),
);
```

Where values are stored:

- `content/settings/<moduleId>.json`

Storage semantics:

- The on-disk JSON is treated as **overrides-only** relative to schema field defaults.
- On load, settings are produced as: `deepMerge(defaultsFromSchema, storedOverrides)`.
- Unknown keys present in the current settings document are preserved on save.

Accessing settings in code:

```php
$enabled = module_settings('example', 'enabled', true);
// or
$store = module_settings('example');
$enabled = $store->get('enabled', true);
```

Migrations:

- The settings store tracks `schema_version` inside the module settings JSON.
- If `schema_version` < `schema['version']`, `migrate()` runs and the file is written back.

---

## 7) Views + templates (optional)

You can ship module-local templates under:

- `modules/<moduleId>/views/<tpl>.php`

Render them using the View system:

- Theme templates: `themes/<active_theme>/templates/<template>.php`
- Module templates: use `"module:tpl"` naming (resolved to `modules/<module>/views/<tpl>.php`)

(See `core/View.php`.)

---

## 8) Translations (optional)

Create:

- `modules/<moduleId>/lang/en.php`

Example:

```php
<?php
return array(
  'example.settings.general' => 'General',
  'example.settings.enabled' => 'Enabled'
);
```

In PHP, use:

- `t('example.settings.enabled')`

Admin UI often accepts i18n specs:

- `array('key' => '...', 'fallback' => '...')`

---

## 9) Reference (code)

- Module loading: `core/ModuleManager.php`
- Base module class: `core/Module.php`
- App lifecycle & route hook: `core/Application.php`
- Hooks: `core/HookManager.php`
- Admin integration:
  - sidebar hook: `admin.sidebar`
  - unified settings: `modules/admin/AdminModule.php`
- Module settings store: `core/ModuleSettings.php`, helper `core/helpers.php::module_settings()`
