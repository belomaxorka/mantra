# Admin Panels — Developer Guide

This guide covers creating new panels for the Mantra CMS admin area.

## Quick Start

The fastest way to add a new admin section is to create a ContentPanel — it gives you a full CRUD interface (list, create, edit, delete) with 4 abstract methods.

### Minimal Example: BookmarksPanel

```
modules/admin/panels/bookmarks/
├── panel.json
├── BookmarksPanel.php
├── views/
│   ├── list.php
│   └── edit.php
└── lang/
    ├── en.php
    └── ru.php
```

**BookmarksPanel.php:**

```php
<?php

namespace Admin;

class BookmarksPanel extends ContentPanel {

    public function id() {
        return 'bookmarks';
    }

    protected function getContentType() {
        return 'Bookmark';
    }

    protected function getCollectionName() {
        return 'bookmarks';
    }

    protected function getDefaultItem() {
        return array(
            'title' => '',
            'slug'  => '',
            'url'   => '',
            'status' => 'draft',
        );
    }

    protected function extractFormData() {
        return array(
            'title'  => post_trimmed('title'),
            'slug'   => post_trimmed('slug'),
            'url'    => post_trimmed('url'),
            'status' => request()->post('status', 'draft'),
        );
    }
}
```

That's it. You get 6 routes, breadcrumbs, permission checks, and CRUD logic for free.

---

## Architecture

```
AdminPanelInterface          <-- contract
    │
AdminPanel                   <-- base class (rendering, helpers, access control)
    │
    ├── ContentPanel         <-- CRUD scaffolding (routes, list/edit, permissions)
    │   ├── PostsPanel
    │   ├── PagesPanel
    │   ├── UsersPanel
    │   └── (your panel)
    │
    ├── DashboardPanel       <-- standalone (custom routes)
    └── SettingsPanel        <-- standalone (custom routes)
```

**When to extend what:**

| Base Class | Use When |
|---|---|
| `ContentPanel` | You need list + create + edit + delete for a JSON collection |
| `AdminPanel` | You need custom routes and views (dashboard, settings, reports) |

---

## Panel Discovery

Panels are auto-discovered from `modules/admin/panels/`. For each subdirectory:

1. Reads `panel.json` for metadata
2. Derives class name from directory: `bookmarks` → `BookmarksPanel`, `my-reports` → `MyReportsPanel`
3. Requires `{ClassName}.php`, instantiates `Admin\{ClassName}`
4. Calls `init($admin)` then `registerRoutes($admin)`
5. Registers translation domain `admin-{id}` from `lang/` directory

No configuration needed — drop the directory and it works.

---

## panel.json

```json
{
  "id": "bookmarks",
  "version": "1.0.0",
  "sidebar": {
    "title": "admin-bookmarks.title",
    "icon": "bi-bookmark-star",
    "group": "admin.sidebar.group.content",
    "order": 20,
    "url": "/admin/bookmarks",
    "require_role": "admin"
  },
  "quick_actions": [
    {
      "id": "new-bookmark",
      "title": "admin-bookmarks.new",
      "icon": "bi-bookmark-plus",
      "url": "/admin/bookmarks/new",
      "order": 30
    }
  ]
}
```

### Sidebar Fields

| Field | Required | Description |
|---|---|---|
| `title` | yes | Translation key for sidebar label |
| `icon` | yes | [Bootstrap Icon](https://icons.getbootstrap.com) class (e.g. `bi-bookmark-star`) |
| `group` | yes | Translation key: `admin.sidebar.group.general`, `.content`, or `.system` |
| `order` | yes | Sort order within group (0 = top). Existing: dashboard=0, pages=10, posts=15, users=20, settings=50 |
| `url` | yes | Panel entry URL (prefixed with `/admin/`) |
| `require_role` | no | If set, sidebar item and quick actions hidden from users without this role |

### Quick Actions

Shown on the dashboard. Each action needs `id`, `title`, `icon`, `url`, `order`.

---

## ContentPanel Reference

### Abstract Methods (must implement)

```php
// Unique panel identifier. Must match directory name.
public function id(): string

// Singular name: 'Post', 'Page', 'Bookmark'
protected function getContentType(): string

// Database collection: 'posts', 'pages', 'bookmarks'
protected function getCollectionName(): string

// Default empty item for the "new" form
protected function getDefaultItem(): array

// Extract POST data into array for create/update
protected function extractFormData(): array
```

### Optional Overrides

```php
// URL segment (default: collection name)
protected function getAdminPath(): string

// Template names (default: 'list', 'edit')
protected function getListTemplate(): string
protected function getEditTemplate(): string

// Translation domain (default: 'admin-{id}')
protected function getDomain(): string

// Permission prefix (default: collection name)
protected function getPermissionPrefix(): string

// Slug processing (default: slugify from title)
protected function ensureSlug($data): array
```

### Auto-Registered Routes

| Method | URL | Handler | Permission |
|---|---|---|---|
| GET | `/admin/{path}` | `listItems()` | `{collection}.view` |
| GET | `/admin/{path}/new` | `newItem()` | `{collection}.create` |
| POST | `/admin/{path}/new` | `createItem()` | `{collection}.create` |
| GET | `/admin/{path}/edit/{id}` | `editItem($params)` | `{collection}.edit` |
| POST | `/admin/{path}/edit/{id}` | `updateItem($params)` | `{collection}.edit` |
| POST | `/admin/{path}/delete/{id}` | `deleteItem($params)` | `{collection}.delete` |

All routes include authentication middleware automatically.

### What ContentPanel Does Automatically

- Registers 6 CRUD routes
- Checks permissions on every action
- Passes `canCreate`, `canEdit`, `canDelete` flags to list template
- Generates breadcrumbs (Dashboard / Collection / Item)
- Sets `author`, `created_at`, `updated_at` on create
- Preserves `author`, `created_at` on update
- Auto-generates slug from title if empty
- Generates unique ID from slug

### Overriding CRUD Methods

Override when you need custom behavior (e.g., UsersPanel overrides `createItem` for password hashing):

```php
public function createItem() {
    if (!$this->requirePermission($this->getPermissionPrefix() . '.create')) return;

    // Your custom logic here

    $this->redirectAdmin($this->getAdminPath());
}
```

Always call `requirePermission()` first. CSRF verification is handled automatically by the global `csrf` middleware on all `/admin/*` POST requests (see [MIDDLEWARE.md](MIDDLEWARE.md)).

---

## AdminPanel Reference (base class)

### Access Control

```php
// Check specific permission (returns false + renders 403 if denied)
$this->requirePermission('bookmarks.create');

// Check admin role
$this->requireAdmin();
```

### Rendering

```php
// Render template from views/ directory
$html = $this->renderView('list', array('items' => $items));

// Wrap in admin layout with sidebar
return $this->renderAdmin($title, $html, array(
    'breadcrumbs' => array(
        array('title' => 'Dashboard', 'url' => base_url('/admin')),
        array('title' => 'Bookmarks'),
    ),
));
```

### Helpers

```php
$this->db()                              // Database instance
$this->auth()                            // Auth instance
$this->getUser()                         // Current user array
$this->redirectAdmin('bookmarks')        // Redirect to /admin/bookmarks
$this->hook('system.init', $callback)    // Register hook
$this->fireHook('my.hook', $data)        // Fire hook
```

### Custom Route Registration

For standalone panels (extends AdminPanel, not ContentPanel):

```php
public function registerRoutes($admin) {
    $admin->adminRoute('GET',  'reports',           array($this, 'index'));
    $admin->adminRoute('GET',  'reports/export',    array($this, 'export'));
    $admin->adminRoute('POST', 'reports/generate',  array($this, 'generate'));
}
```

`adminRoute($method, $pattern, $callback)` automatically adds `/admin/` prefix and auth middleware.

---

## Translations

File: `lang/en.php` — returns flat associative array. Domain: `admin-{panelId}`.

### Key Naming Convention

```php
return array(
    // Panel name (used in module management)
    'admin-bookmarks.name' => 'Bookmarks',

    // Page titles
    'admin-bookmarks.title' => 'Bookmarks',
    'admin-bookmarks.new' => 'New Bookmark',
    'admin-bookmarks.edit_bookmark' => 'Edit Bookmark',

    // Field labels
    'admin-bookmarks.field.title' => 'Title',
    'admin-bookmarks.field.url' => 'URL',
    'admin-bookmarks.field.status' => 'Status',
    'admin-bookmarks.field.updated' => 'Updated',
    'admin-bookmarks.field.actions' => 'Actions',

    // Status values
    'admin-bookmarks.status.draft' => 'Draft',
    'admin-bookmarks.status.published' => 'Published',

    // Form
    'admin-bookmarks.slug_help' => 'Auto-generated from title if empty.',
    'admin-bookmarks.create' => 'Create Bookmark',
    'admin-bookmarks.update' => 'Update Bookmark',

    // List
    'admin-bookmarks.no_bookmarks' => 'No bookmarks yet.',
    'admin-bookmarks.delete_confirm' => 'Are you sure you want to delete this bookmark?',
);
```

**Important:** ContentPanel constructs keys automatically:
- List title: `admin-{id}.title`
- New item title: `admin-{id}.new`
- Edit title: `admin-{id}.edit_{contentType}` (e.g., `admin-bookmarks.edit_bookmark`)

Usage in templates: `<?php echo t('admin-bookmarks.field.title'); ?>`

Placeholders: `t('key', array('count' => 5))` replaces `{count}` in the string.

---

## View Templates

### List Template (`views/list.php`)

Key variables available:
- `${collection}` — array of items (e.g., `$bookmarks`)
- `$canCreate` — boolean, whether user can create
- `$canEdit` — boolean, whether user can edit
- `$canDelete` — boolean, whether user can delete

```php
<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo t('admin-bookmarks.title'); ?></h1>
        <?php if (!empty($canCreate)): ?>
            <a href="<?php echo base_url('/admin/bookmarks/new'); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i><?php echo t('admin-bookmarks.new'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($bookmarks)): ?>
                        <div class="admin-empty-state">
                            <i class="bi bi-bookmark"></i>
                            <p><?php echo t('admin-bookmarks.no_bookmarks'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo t('admin-bookmarks.field.title'); ?></th>
                                        <th><?php echo t('admin-bookmarks.field.status'); ?></th>
                                        <th class="text-end"><?php echo t('admin-bookmarks.field.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookmarks as $item): ?>
                                        <tr>
                                            <td><strong><?php echo e($item['title']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $item['status'] === 'published' ? 'success' : 'secondary'; ?>">
                                                    <?php echo t('admin-bookmarks.status.' . $item['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!empty($canEdit)): ?>
                                                        <a href="<?php echo base_url('/admin/bookmarks/edit/' . $item['_id']); ?>"
                                                           class="btn btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($canDelete)): ?>
                                                        <button type="button"
                                                                class="btn btn-outline-danger"
                                                                onclick="adminConfirmDelete('<?php echo e(base_url('/admin/bookmarks/delete/' . $item['_id'])); ?>', '<?php echo e(t('admin-bookmarks.delete_confirm')); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Edit Template (`views/edit.php`)

Key variables:
- `${contentType}` — item data (e.g., `$bookmark`)
- `$isNew` — boolean
- `$csrf_token` — CSRF token string

```php
<div class="container-fluid">
    <div class="admin-page-header mb-4">
        <h1 class="h3"><?php echo $isNew ? t('admin-bookmarks.new') : t('admin-bookmarks.edit_bookmark'); ?></h1>
        <a href="<?php echo base_url('/admin/bookmarks'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i><?php echo t('admin.common.back'); ?>
        </a>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <?php echo t('admin-bookmarks.field.title'); ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                   value="<?php echo e($bookmark['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="url" class="form-label"><?php echo t('admin-bookmarks.field.url'); ?></label>
                            <input type="url" class="form-control" id="url" name="url"
                                   value="<?php echo e($bookmark['url']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><?php echo t('admin-bookmarks.field.status'); ?></div>
                    <div class="card-body">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="status" id="status_draft"
                                   value="draft" <?php echo ($bookmark['status'] === 'draft') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="status_draft">
                                <i class="bi bi-file-earmark me-1"></i><?php echo t('admin-bookmarks.status.draft'); ?>
                            </label>
                            <input type="radio" class="btn-check" name="status" id="status_published"
                                   value="published" <?php echo ($bookmark['status'] === 'published') ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success" for="status_published">
                                <i class="bi bi-check-circle me-1"></i><?php echo t('admin-bookmarks.status.published'); ?>
                            </label>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo $isNew ? t('admin-bookmarks.create') : t('admin-bookmarks.update'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
```

---

## Permissions

### How Permissions Work

Permissions use the format `{collection}.{action}` (e.g. `bookmarks.view`, `bookmarks.edit`). There is also a special `.own` suffix for ownership-gated actions (e.g. `bookmarks.edit.own` — user can only edit content they created).

The central authority is `PermissionRegistry` (`core/classes/PermissionRegistry.php`). It stores all registered permissions, default role mappings, and loads custom overrides from `config.json`. Admins can configure per-role permissions in the admin UI at `/admin/permissions`.

**Roles:** `admin` (all permissions, always), `editor`, `viewer`.

### Registering Module Permissions

Modules register their permissions via the `permissions.register` hook. This should be done in `init()`:

```php
<?php

use Module\Module;

class CommentsModule extends Module
{
    public function init()
    {
        // Register permissions for this module
        $this->hook('permissions.register', array($this, 'registerPermissions'));

        // Register routes, other hooks, etc.
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    /**
     * Register permissions with the central registry.
     *
     * @param PermissionRegistry $registry
     * @return PermissionRegistry
     */
    public function registerPermissions($registry)
    {
        // Register permissions with human-readable labels, grouped for the admin UI
        $registry->registerPermissions(array(
            'comments.view'     => 'View comments',
            'comments.create'   => 'Post comments',
            'comments.moderate' => 'Moderate comments',
            'comments.delete'   => 'Delete comments',
        ), 'Comments');

        // Set which roles get these permissions by default
        $registry->addRoleDefaults('editor', array(
            'comments.view', 'comments.create', 'comments.moderate', 'comments.delete',
        ));
        $registry->addRoleDefaults('viewer', array(
            'comments.view',
        ));

        return $registry;
    }
}
```

Once registered, the permissions automatically appear in the admin Permissions panel (`/admin/permissions`) where admins can customize them per role.

### PermissionRegistry API

```php
// Register permissions (associative: permission => label, or numeric: just strings)
$registry->registerPermissions(array(
    'bookmarks.view'   => 'View bookmarks',
    'bookmarks.create' => 'Create bookmarks',
    'bookmarks.edit'   => 'Edit all bookmarks',
    'bookmarks.edit.own' => 'Edit own bookmarks',
    'bookmarks.delete' => 'Delete bookmarks',
), 'Bookmarks');

// Add defaults for a role (merged with existing defaults)
$registry->addRoleDefaults('editor', array('bookmarks.view', 'bookmarks.create', 'bookmarks.edit'));

// Query
$registry->getAll();                          // All registered permission strings
$registry->getGrouped();                      // Grouped for UI: group => permissions[]
$registry->getLabel('bookmarks.view');        // 'View bookmarks'
$registry->getPermissionsForRole('editor');   // Effective permissions (override or defaults)
$registry->hasPermission('editor', 'bookmarks.view');  // true, 'own', or false
$registry->hasOverride('editor');             // true if admin customized this role

// Persistence (used by admin panel, not modules)
$registry->setRolePermissions('editor', $permissions);  // Save override to config
$registry->resetRole('editor');                          // Remove override, revert to defaults
```

### Ownership Permissions (`.own` suffix)

The `.own` suffix enables ownership-based access control. When a role has `posts.edit.own` but not `posts.edit`:

1. `ContentPanel` calls `requirePermission('posts.edit')`
2. `PermissionRegistry::hasPermission()` finds `posts.edit.own` and returns the string `'own'`
3. `ContentPanel` calls `User::canEdit($user, $item)` to verify the current user is the content author
4. If not the owner, a 403 is rendered

This is handled automatically by `ContentPanel`. If you override CRUD methods, check the return value:

```php
public function editItem($params) {
    $access = $this->requirePermission('bookmarks.edit');
    if ($access === false) return;    // No access at all

    $item = db()->read('bookmarks', $params['id']);

    // If access is 'own', verify ownership
    if ($access === 'own' && !$this->checkOwnership($item)) {
        return;  // Not the owner — 403 already rendered
    }

    // ... proceed with edit
}
```

### ContentPanel and Permissions

`ContentPanel` handles permissions automatically for standard CRUD. The permission prefix defaults to the collection name. To customize:

```php
protected function getPermissionPrefix() {
    return 'bookmarks';  // default: $this->getCollectionName()
}
```

Auto-checked permissions: `{prefix}.view`, `{prefix}.create`, `{prefix}.edit`, `{prefix}.delete`.

Permission flags (`canCreate`, `canEdit`, `canDelete`) are passed to the list template for conditional UI rendering.

### Restricting Sidebar Visibility

Use `require_role` in `panel.json` to hide the sidebar item from specific roles. Supports a single role or an array:

```json
{
  "sidebar": {
    "require_role": "admin"
  }
}
```

```json
{
  "sidebar": {
    "require_role": ["admin", "editor"]
  }
}
```

This controls sidebar/quick-action **visibility** only. Actual access is enforced by `requirePermission()` in the panel code. If a role has no permissions registered for a collection, they will get a 403 even if they access the URL directly.

### Helper Function

```php
// Get the PermissionRegistry instance
$registry = permissions();

// Check permission for a user (typically done via requirePermission in panels)
$userManager = new User();
$result = $userManager->hasPermission($user, 'bookmarks.edit');
// $result: true (full access), 'own' (ownership check needed), or false
```

---

## CSS Classes

The admin theme provides these ready-to-use classes:

| Class | Usage |
|---|---|
| `.admin-page-header` | Flex container: title left, buttons right |
| `.admin-empty-state` | Centered icon + message for empty lists |
| `.admin-breadcrumb` | Breadcrumb navigation |
| `.stat-card` | Dashboard stat cards with hover lift |
| `.card`, `.card-header`, `.card-body` | Standard content cards |
| `.table.table-hover` | Styled tables with hover |
| `.badge.bg-success/secondary/danger/primary` | Soft-colored status badges |
| `.btn-outline-primary/secondary/danger/success` | Themed outline buttons |
| `.btn-group.btn-group-sm` | Action button groups in tables |
| `.form-control`, `.form-select`, `.form-check` | Styled form inputs |
| `.btn-check` + `.btn-outline-*` | Radio button groups (status toggle) |

### Delete Confirmation

Use the global modal instead of `confirm()`:

```js
adminConfirmDelete(url, message)
```

The modal and form are already in `layout.php`. No extra markup needed.

---

## Hooks

Panels can hook into the system lifecycle:

```php
public function init($admin) {
    parent::init($admin);
    $this->hook('theme.footer', array($this, 'addFooterWidget'));
}

public function addFooterWidget($html) {
    return $html . '<div>My widget</div>';
}
```

Available admin hooks: `admin.sidebar`, `admin.quick_actions`, `admin.head`, `admin.footer`.

Available theme hooks: `theme.head`, `theme.body.start`, `theme.footer`, `theme.body.end`, `theme.navigation`, `theme.footer.links`.

---

## Checklist

Before shipping a new panel:

- [ ] `panel.json` has correct `id`, `sidebar`, `quick_actions`
- [ ] Class extends `ContentPanel` (CRUD) or `AdminPanel` (custom)
- [ ] Class is in `namespace Admin`
- [ ] `id()` matches directory name
- [ ] Permissions registered via `permissions.register` hook with labels and role defaults
- [ ] `lang/en.php` has all translation keys
- [ ] `lang/ru.php` has all translation keys
- [ ] List template checks `$canCreate`, `$canEdit`, `$canDelete`
- [ ] Delete buttons use `adminConfirmDelete()`, not `confirm()`
- [ ] All user input escaped with `e()` in templates
- [ ] CSRF token included in all forms
- [ ] `php -l` passes on all PHP files
