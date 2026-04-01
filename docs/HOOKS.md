# Hooks — Developer Guide

Reference for the Mantra CMS event/hook system.

## Architecture

```
HookManager         — Runtime: register listeners, fire hooks, pass data
HookRegistry        — Documentation: catalogue of all hooks with types and descriptions
/admin/hooks        — Admin UI: browse all hooks and active listener counts
```

Hooks are the primary extension mechanism. A hook is a named event that passes data through a chain of callbacks. Each callback can transform the data before it reaches the next one.

## Two Roles

### Firing a hook (you own the event)

```php
// In your module or controller:
$data = app()->hooks()->fire('mymodule.before_save', $data);
```

### Listening to a hook (you extend someone else's event)

```php
// In your module's init():
$this->hook('mymodule.before_save', array($this, 'onBeforeSave'));
```

## API Reference

### HookManager (runtime)

| Method | Description |
|--------|-------------|
| `register($name, $callback, $priority)` | Listen to a hook. Lower priority runs first (default: 10). Returns listener ID. |
| `unregister($name, $id)` | Remove a listener by ID |
| `fire($name, $data, $context)` | Fire a hook. Returns transformed `$data`. |
| `hasListeners($name)` | Check if anyone listens |
| `listenerCount($name)` | Count listeners |
| `getActiveHooks()` | List all hook names with listeners |
| `clear($name)` | Remove all listeners for a hook |

### HookRegistry (documentation)

| Method | Description |
|--------|-------------|
| `define($name, $description, $dataType, $returnType, $extra)` | Register a hook definition |
| `describe($name)` | Get hook definition (or null) |
| `all()` | Get all registered definitions |
| `isStandard($name)` | Check if hook is registered |

### Module helpers

Inside a module class (extends `Module`):

```php
// Listen — shorthand for app()->hooks()->register()
$this->hook('theme.head', array($this, 'addMeta'));
$this->hook('theme.head', array($this, 'addMeta'), 5); // higher priority

// Fire — shorthand for app()->hooks()->fire()
$result = $this->fireHook('mymodule.filter', $data);
$result = $this->fireHook('mymodule.filter', $data, $context);
```

## Data Flow

```
fire('hook.name', $data, $context)
  |
  +-- callback A ($data, $context) --> returns modified $data
  |
  +-- callback B ($data, $context) --> returns modified $data
  |
  +-- callback C ($data, $context) --> returns null (data unchanged)
  |
  = final $data returned
```

**Rules:**
- Callbacks are called in **priority order** (lower number = runs first, default 10)
- `$data` is the **transformable** value — each callback receives the output of the previous one
- `$context` is **read-only** — same value passed to every callback (e.g., the current post)
- If a callback returns `null`, `$data` is **not modified** (callback was side-effect only)
- If a callback returns any non-null value, it **replaces** `$data`

## Context Parameter

The `$context` parameter allows hooks to pass read-only information without polluting `$data`:

```php
// Firing with context:
echo app()->hooks()->fire('admin.posts.edit.sidebar', '', $post);
//                                                     ^    ^
//                                                   data  context

// Listening — second parameter is context:
public function addCategoryDropdown($html, $post) {
    $currentCategory = $post['category'];
    return $html . '<select>...</select>';
}
```

**When to use context:**
- The hook accumulates HTML (data = string) but callbacks need item data (context = array)
- The hook transforms a list but callbacks need configuration (context = config)

**Backward compatible:** existing callbacks that accept only `($data)` ignore the extra argument — PHP does not error on unused parameters.

## Hook Ownership

Every hook belongs to the component that **fires** it. That component registers the hook in `HookRegistry` via `define()`.

| Owner | Hooks | Where registered |
|-------|-------|------------------|
| Core (Application, View) | `system.init`, `system.shutdown`, `routes.register`, `view.render` | HookRegistry static array |
| Core (Theme layout) | `theme.head`, `theme.body.start`, `theme.navigation`, `theme.sidebar`, `theme.footer.links`, `theme.footer`, `theme.body.end` | HookRegistry static array |
| AdminModule | `admin.head`, `admin.footer`, `admin.sidebar`, `admin.quick_actions`, `permissions.register` | `AdminModule::init()` |
| PostsPanel | `page.home.*`, `page.blog.*`, `post.single.*`, `admin.posts.*` | `PostsPanel::init()` |
| PagesPanel | `page.single.*`, `admin.pages.*` | `PagesPanel::init()` |
| UsersPanel | `admin.users.*` | `UsersPanel::init()` |
| Any module | Custom hooks | Module's `init()` via `HookRegistry::define()` |

## Registering Your Own Hooks

If your module fires hooks that others should listen to, register them in `init()`:

```php
public function init() {
    parent::init();

    // Document your hooks so they appear in /admin/hooks
    \HookRegistry::define(
        'mymodule.before_save',           // hook name
        'Fired before saving an item',    // description
        'array',                          // data type
        'array',                          // return type
        array(
            'source'  => 'mymodule',      // groups in admin UI
            'context' => 'string (item ID)', // documents context param
        )
    );
}
```

Then fire the hook where appropriate:

```php
$data = $this->fireHook('mymodule.before_save', $data, $itemId);
```

## ContentPanel Auto-Registration

Panels extending `ContentPanel` get 5 standard hooks registered automatically via `registerPanelHooks()`:

| Hook pattern | Description |
|-------------|-------------|
| `admin.{collection}.edit.data` | Modify template data for edit form |
| `admin.{collection}.form_data` | Modify form data before save |
| `admin.{collection}.edit.sidebar` | Inject HTML into edit sidebar (context: item) |
| `admin.{collection}.list.columns.head` | Inject `<th>` into list header |
| `admin.{collection}.list.columns.body` | Inject `<td>` into list row (context: item) |

Call `$this->registerPanelHooks()` in your panel's `init()`:

```php
public function init($admin) {
    parent::init($admin);
    $this->registerPanelHooks();
}
```

## Core Hooks Reference

### System

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `system.init` | `null` | — | Application, after modules loaded |
| `system.shutdown` | `null` | — | Application, after response sent |
| `routes.register` | `array` (router) | — | Application, before dispatch |
| `view.render` | `string` (HTML) | — | View, after layout wrapping |

### Theme

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `theme.head` | `string` | — | layout.php `<head>` |
| `theme.body.start` | `string` | — | layout.php after `<body>` |
| `theme.navigation` | `array` (nav items) | — | layout.php nav section |
| `theme.sidebar` | `array` (widgets) | — | layout.php sidebar |
| `theme.footer.links` | `array` (links) | — | layout.php footer |
| `theme.footer` | `string` | — | layout.php before `</body>` |
| `theme.body.end` | `string` | — | layout.php before `</body>` |

### Admin

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `admin.head` | `string` | — | Admin layout `<head>` |
| `admin.footer` | `string` | — | Admin layout footer |
| `admin.sidebar` | `array` (items) | — | AdminModule sidebar builder |
| `admin.quick_actions` | `array` (actions) | — | DashboardPanel |
| `permissions.register` | `PermissionRegistry` | — | AdminModule (lazy service) |

### Content (Posts)

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `page.home.query` | `array` (query params) | — | PageController::home() |
| `page.home.posts` | `array` (posts) | — | PageController::home() |
| `page.home.data` | `array` (template data) | — | PageController::home() |
| `page.blog.query` | `array` (query params) | — | PageController::blog() |
| `page.blog.posts` | `array` (posts) | — | PageController::blog() |
| `page.blog.data` | `array` (template data) | — | PageController::blog() |
| `post.single.query` | `array` (query params) | — | PageController::post() |
| `post.single.loaded` | `array` (post doc) | — | PageController::post() |
| `post.single.data` | `array` (template data) | — | PageController::post() |

### Content (Pages)

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `page.single.query` | `array` (query params) | — | PageController::page() |
| `page.single.loaded` | `array` (page doc) | — | PageController::page() |
| `page.single.data` | `array` (template data) | — | PageController::page() |

### Admin Panel Extensibility

Available for every `ContentPanel`-based collection (posts, pages, users):

| Hook | Data | Context | Fired by |
|------|------|---------|----------|
| `admin.{coll}.edit.data` | `array` (template data) | — | ContentPanel::newItem/editItem |
| `admin.{coll}.form_data` | `array` (form fields) | — | ContentPanel::createItem/updateItem |
| `admin.{coll}.edit.sidebar` | `string` (HTML) | `array` (item) | Edit template |
| `admin.{coll}.list.columns.head` | `string` (HTML) | — | List template |
| `admin.{coll}.list.columns.body` | `string` (HTML) | `array` (item) | List template |

## Common Patterns

### Add meta tags to `<head>`

```php
$this->hook('theme.head', function($html) {
    return $html . '<meta name="author" content="My Site">';
});
```

### Add a navigation item

```php
$this->hook('theme.navigation', function($items) {
    $items[] = array(
        'id' => 'blog', 'title' => 'Blog',
        'url' => base_url('/blog'), 'order' => 10
    );
    return $items;
});
```

### Filter posts on home page

```php
$this->hook('page.home.query', function($params) {
    $params['filter']['category'] = 'featured';
    return $params;
});
```

### Inject a field into post edit form

```php
$this->hook('admin.posts.edit.sidebar', function($html, $post) {
    $value = isset($post['custom_field']) ? $post['custom_field'] : '';
    return $html . '<div class="card mb-4"><div class="card-body">
        <input name="custom_field" value="' . e($value) . '">
    </div></div>';
}, 10);

$this->hook('admin.posts.form_data', function($data) {
    $data['custom_field'] = app()->request()->post('custom_field', '');
    return $data;
});
```

### Register permissions

```php
$this->hook('permissions.register', function($registry) {
    $registry->registerPermissions(array(
        'mymodule.view'   => 'View items',
        'mymodule.edit'   => 'Edit items',
    ), 'My Module');
    return $registry;
});
```

## Admin Hooks Viewer

Browse all registered hooks at `/admin/hooks` (admin role required). The page shows:
- Core hooks grouped by type (System, Theme)
- Module hooks grouped by source (each module gets its own card)
- Live listener count per hook
- Data types and context documentation

## Debugging

```php
// Check if anyone listens to a hook:
app()->hooks()->hasListeners('theme.head'); // true/false

// Count listeners:
app()->hooks()->listenerCount('theme.head'); // int

// Get all active hook names:
app()->hooks()->getActiveHooks(); // array of strings

// Look up hook documentation:
HookRegistry::describe('theme.head');
// array('description' => '...', 'data_type' => 'string', ...)
```
