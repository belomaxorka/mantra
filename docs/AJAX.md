# AJAX — Developer Guide

Reference for the Mantra CMS unified AJAX system.

## Architecture

```
AjaxDispatcher          — Core: register actions, dispatch requests, JSON responses
AjaxException           — Controlled error responses from handlers
admin-ajax.js           — JS helper: Mantra.ajax() with auto CSRF and toast
```

```
Browser                        Server
  |                              |
  |  POST /admin/ajax            |
  |  ?action=uploads.upload      |
  |  X-CSRF-Token: abc123       |
  |  Body: FormData / JSON       |
  | ---------------------------> |
  |                              |  AjaxDispatcher::dispatch()
  |                              |    1. Find action by name
  |                              |    2. Check HTTP method
  |                              |    3. Check auth
  |                              |    4. Verify CSRF token
  |                              |    5. Check permissions
  |                              |    6. Fire ajax.before hook
  |                              |    7. Call handler
  |                              |    8. Fire ajax.after hook
  |  {"ok": true, "data": {...}} |
  | <--------------------------- |
```

Two endpoints serve all AJAX requests:

| Endpoint | Middleware | Use case |
|----------|-----------|----------|
| `POST\|GET /admin/ajax` | `auth` middleware | Admin panel actions |
| `POST\|GET /ajax` | None (auth per-action) | Public site actions |

The action name is always in the query string: `?action=module.action`.

## Response Format

Every response uses the same JSON envelope:

```json
// Success
{"ok": true,  "data": {"url": "/uploads/photo.jpg"}}

// Error
{"ok": false, "error": "File too large"}
```

HTTP status codes are set correctly (200, 400, 401, 403, 404, 405, 500).

## Quick Start

### 1. Register an action (PHP)

In your module's `init()` or panel's `init()`:

```php
$this->ajaxAction('bookmarks.delete', [$this, 'handleDelete'], [
    'permission' => 'bookmarks.delete',
]);
```

### 2. Write the handler (PHP)

```php
public function handleDelete($request, $access)
{
    $id = $request->input('id');

    if (!$id) {
        throw new \Ajax\AjaxException('Missing ID', 400);
    }

    app()->db()->delete('bookmarks', $id);

    return ['deleted' => $id];
}
```

### 3. Call from JavaScript

```javascript
Mantra.ajax('bookmarks.delete', { id: 'abc123' })
    .done(function(data) {
        adminToast('Deleted', 'success');
        $('#row-' + data.deleted).fadeOut();
    });
```

That's it. CSRF token, error handling, and toast notifications are automatic.

---

## PHP API

### AjaxDispatcher

Registered as a lazy service. Access via `app()->ajax()`.

| Method | Description |
|--------|-------------|
| `register($name, $handler, $options)` | Register an action |
| `dispatch()` | Dispatch current request (called by route handlers) |
| `has($name)` | Check if action exists |
| `getRegistered()` | Get all registered action names |

### register() Options

```php
app()->ajax()->register('myaction', $callback, [
    'method'     => 'POST',    // 'POST', 'GET', or 'ANY'
    'auth'       => true,      // require logged-in user
    'permission' => null,      // permission string or null
    'csrf'       => null,      // null = auto (true for POST, false for GET)
]);
```

| Option | Default | Description |
|--------|---------|-------------|
| `method` | `'POST'` | HTTP method. `'GET'` for read-only, `'POST'` for mutations, `'ANY'` for both |
| `auth` | `true` | If `true`, unauthenticated requests get 401 |
| `permission` | `null` | If set, checked via `User::hasPermission()`. Denied requests get 403. The `'own'` sentinel is passed to the handler as `$access` |
| `csrf` | `null` (auto) | If `null`: `true` for POST, `false` for GET. Token read from `X-CSRF-Token` header |

### Module / AdminPanel Helper

Both `Module` and `AdminPanel` provide the same convenience method:

```php
// Inside a Module subclass
protected function ajaxAction(string $name, callable $handler, array $options = []): void

// Equivalent to:
app()->ajax()->register($name, $handler, $options);
```

### Handler Signature

```php
/**
 * @param \Http\Request $request  The current request
 * @param bool|string   $access   true = full access, 'own' = ownership check needed
 * @return mixed                  Becomes the "data" field in the response
 */
public function myHandler($request, $access)
{
    // Read input — works with both JSON body and form POST
    $id    = $request->input('id');
    $title = $request->input('title');

    // Read uploaded file
    $file = $request->file('upload');

    // Ownership check when $access === 'own'
    if ($access === 'own') {
        $item = app()->db()->read('bookmarks', $id);
        $user = new \User();
        if (!$user->canEdit(app()->auth()->user(), $item)) {
            throw new \Ajax\AjaxException('Cannot edit another user\'s item', 403);
        }
    }

    // Return data — automatically wrapped in {"ok": true, "data": ...}
    return ['id' => $id, 'title' => $title];
}
```

### AjaxException

Throw from any handler to return a controlled error:

```php
throw new \Ajax\AjaxException('File too large', 413);
throw new \Ajax\AjaxException('Item not found', 404);
throw new \Ajax\AjaxException('Invalid format');        // defaults to 400
```

The exception code becomes the HTTP status code. The message becomes the `error` field.

Unhandled exceptions are caught by the dispatcher and returned as 500 errors. In debug mode (`MANTRA_DEBUG = true`), the exception message is included; in production, a generic "Internal error" is returned.

---

## JavaScript API

### Mantra.ajax()

```javascript
/**
 * @param {string}           action   Action name
 * @param {object|FormData}  data     Payload
 * @param {object}           options  Optional overrides
 * @returns {jQuery.Deferred}         Resolves with data, rejects with error string
 */
Mantra.ajax(action, data, options)
```

| Option | Default | Description |
|--------|---------|-------------|
| `method` | `'POST'` | HTTP method |
| `admin` | `true` | `true` = `/admin/ajax`, `false` = `/ajax` |
| `toast` | `true` | Auto-show error toast via `adminToast()` |

The CSRF token is read from `<meta name="csrf-token">` and sent as `X-CSRF-Token` header automatically.

### Mantra.csrfToken()

Returns the current CSRF token from the meta tag:

```javascript
var token = Mantra.csrfToken(); // "a1b2c3d4..."
```

### Mantra.baseUrl()

Returns the site base URL from the meta tag:

```javascript
var base = Mantra.baseUrl(); // "http://example.com" or ""
```

---

## Examples

### POST with JSON data

```javascript
Mantra.ajax('settings.save', {
    site_title: 'My Blog',
    posts_per_page: 10
}).done(function(data) {
    adminToast('Settings saved', 'success');
});
```

Sends:
```
POST /admin/ajax?action=settings.save
Content-Type: application/json
X-CSRF-Token: abc123

{"site_title":"My Blog","posts_per_page":10}
```

### File upload with FormData

```javascript
var fd = new FormData();
fd.append('file', fileInput.files[0]);

Mantra.ajax('uploads.upload', fd)
    .done(function(data) {
        // data.url = "/uploads/2026/04/photo.jpg"
        $('img.preview').attr('src', data.url);
    });
```

Sends:
```
POST /admin/ajax?action=uploads.upload
Content-Type: multipart/form-data
X-CSRF-Token: abc123

[binary data]
```

### GET request (read-only, no CSRF)

```javascript
Mantra.ajax('search.suggest', { q: 'mantra' }, {
    admin: false,
    method: 'GET'
}).done(function(data) {
    renderSuggestions(data.results);
});
```

Sends:
```
GET /ajax?action=search.suggest&q=mantra
```

### Silent request (no toast on error)

```javascript
Mantra.ajax('editor.autosave', { id: id, content: content }, { toast: false })
    .done(function() { showSaveIndicator(); })
    .fail(function(err) { console.warn('Autosave failed:', err); });
```

### Error handling

```javascript
Mantra.ajax('posts.publish', { id: id })
    .done(function(data) {
        // Success — toast is NOT auto-shown for success, only for errors
        adminToast('Published!', 'success');
        window.location.reload();
    })
    .fail(function(errorMessage) {
        // Error toast is shown automatically (toast: true by default)
        // errorMessage is the string from {"error": "..."}
        console.error(errorMessage);
    });
```

---

## Module Examples

### Admin-only action (authenticated, permission-checked)

```php
class CategoriesModule extends Module
{
    public function init(): void
    {
        parent::init();

        $this->ajaxAction('categories.reorder', [$this, 'handleReorder'], [
            'permission' => 'categories.edit',
        ]);
    }

    public function handleReorder($request, $access)
    {
        $order = $request->input('order'); // [id => position, ...]

        if (!is_array($order)) {
            throw new \Ajax\AjaxException('Invalid order data', 400);
        }

        foreach ($order as $id => $position) {
            $cat = app()->db()->read('categories', $id);
            if ($cat) {
                $cat['position'] = (int)$position;
                app()->db()->write('categories', $id, $cat);
            }
        }

        return ['updated' => count($order)];
    }
}
```

### Public action (no auth, no CSRF)

```php
class SearchModule extends Module
{
    public function init(): void
    {
        parent::init();

        $this->ajaxAction('search.suggest', [$this, 'handleSuggest'], [
            'method' => 'GET',
            'auth'   => false,
        ]);
        // csrf defaults to false for GET — no token needed
    }

    public function handleSuggest($request, $access)
    {
        $query = trim((string)$request->query('q', ''));

        if (strlen($query) < 2) {
            return ['results' => []];
        }

        $posts = app()->db()->query('posts', ['status' => 'published']);

        $results = [];
        foreach ($posts as $post) {
            if (stripos($post['title'], $query) !== false) {
                $results[] = [
                    'title' => $post['title'],
                    'url'   => base_url('/post/' . $post['slug']),
                ];
            }
        }

        return ['results' => array_slice($results, 0, 5)];
    }
}
```

### Admin panel action with ownership check

```php
class CommentsPanel extends AdminPanel
{
    public function init($admin): void
    {
        parent::init($admin);

        $this->ajaxAction('comments.delete', [$this, 'handleDelete'], [
            'permission' => 'comments.delete',
        ]);
    }

    public function handleDelete($request, $access)
    {
        $id = $request->input('id');
        $comment = app()->db()->read('comments', $id);

        if (!$comment) {
            throw new \Ajax\AjaxException('Comment not found', 404);
        }

        // 'own' means the user can only delete their own comments
        if ($access === 'own') {
            $user = new \User();
            if (!$user->canEdit(app()->auth()->user(), $comment)) {
                throw new \Ajax\AjaxException('Permission denied', 403);
            }
        }

        app()->db()->delete('comments', $id);

        return ['deleted' => $id];
    }
}
```

---

## CSRF Protection

CSRF tokens are session-scoped (one token per session, not per request). This is intentional — per-request tokens would break parallel AJAX calls.

### How it works

1. PHP generates a token via `app()->auth()->generateCsrfToken()` and stores it in the session
2. The token is embedded in `<meta name="csrf-token">` in both admin and public themes
3. `Mantra.ajax()` reads the meta tag and sends the token as `X-CSRF-Token` header
4. `AjaxDispatcher` verifies the header via `Auth::verifyCsrfToken()` (timing-safe `hash_equals`)

### When CSRF is checked

| Method | Default | Override |
|--------|---------|----------|
| POST | Checked | `'csrf' => false` to skip |
| GET | Not checked | `'csrf' => true` to enforce |

GET requests skip CSRF by default because they should be read-only (no state changes).

### Admin forms (non-AJAX)

Traditional form POSTs still use the hidden field `csrf_token`. The global `csrf` middleware (see [MIDDLEWARE.md](MIDDLEWARE.md)) checks both sources:
1. POST body field `csrf_token`
2. Header `X-CSRF-Token`

If the request accepts JSON (`Accept: application/json`), the error response is JSON instead of plain text.

---

## Hooks

### ajax.before

Fired before the action handler runs. Allows interception.

| | |
|---|---|
| **Data** | `array {action, access, definition}` |
| **Return** | `array` — set `halt => true` to block |

```php
// Rate limiting example
$this->hook('ajax.before', function ($context) {
    $action = $context['action'];
    $ip = app()->request()->clientIp();

    if ($this->isRateLimited($ip, $action)) {
        $context['halt'] = true;
        $context['error'] = 'Too many requests';
        $context['code'] = 429;
    }

    return $context;
});
```

### ajax.after

Fired after the handler, before the response is sent. Allows response transformation.

| | |
|---|---|
| **Data** | `array {ok, data}` |
| **Context** | `array {action, access, definition}` |
| **Return** | `array` |

```php
// Logging example
$this->hook('ajax.after', function ($response, $context) {
    logger('ajax')->info('Action completed', [
        'action' => $context['action'],
        'ok'     => $response['ok'],
    ]);
    return $response;
});
```

---

## Public Theme Integration

When any module registers an AJAX action, the dispatcher automatically injects into public themes via hooks:

- `theme.head` — `<meta name="csrf-token">` and `<meta name="base-url">`
- `theme.footer` — `<script src="admin-ajax.js">`

This is lazy: if no module uses AJAX, nothing is injected.

In your theme's JavaScript:

```javascript
// Calls the public endpoint /ajax
Mantra.ajax('search.suggest', { q: query }, { admin: false, method: 'GET' })
    .done(function(data) { /* ... */ });
```

If your theme does not load jQuery, you can make raw requests:

```javascript
fetch('/ajax?action=search.suggest&q=' + encodeURIComponent(query))
    .then(function(r) { return r.json(); })
    .then(function(response) {
        if (response.ok) {
            renderResults(response.data.results);
        }
    });
```

---

## Dispatch Flow

```
1. Extract action name from ?action= query parameter

2. Look up registered action
   → 404 "Unknown action" if not found

3. Check HTTP method matches
   → 405 "Method not allowed" if wrong

4. Check authentication (if auth: true)
   → 401 "Authentication required" if not logged in

5. Verify CSRF token (auto: true for POST, false for GET; overridable via csrf option)
   → 403 "Invalid CSRF token" if missing or wrong
   Token read from X-CSRF-Token header

6. Check permission (if permission is set)
   → 403 "Permission denied" if denied
   → $access = true or 'own'

7. Fire ajax.before hook
   → 403 if halt: true

8. Call handler($request, $access)
   → AjaxException caught → error response with exception code
   → Other exceptions caught → 500 error (message shown in debug mode)

9. Fire ajax.after hook (filters response)

10. Send JSON response
```

---

## File Reference

| File | Description |
|------|-------------|
| `core/classes/Ajax/AjaxDispatcher.php` | Action registry and request dispatcher |
| `core/classes/Ajax/AjaxException.php` | Exception class for controlled errors |
| `core/classes/Application.php` | Service registration and public `/ajax` routes |
| `modules/admin/AdminModule.php` | Admin `/admin/ajax` routes with auth middleware |
| `modules/admin/assets/js/admin-ajax.js` | jQuery helper `Mantra.ajax()` |
| `modules/admin/views/partials/admin-head.php` | Admin meta tags and script include |
| `core/classes/Module/Module.php` | `ajaxAction()` helper method |
| `core/classes/Admin/AdminPanel.php` | `ajaxAction()` helper method |
| `core/classes/HookRegistry.php` | `ajax.before` and `ajax.after` hook definitions |
