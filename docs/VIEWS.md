# Views & Templates — Developer Guide

Reference for the Mantra CMS template engine (`core/classes/View.php`).

## Architecture

```
View::render($template, $data, $options)
  |
  +-- Template Resolution (theme -> module fallback)
  |
  +-- renderTemplate() -- extract($data) + include
  |
  +-- Layout Wrapping (theme layout.php, $content injected)
  |
  +-- Hook: view.render -- post-processing filter
```

The View class is a thin PHP template engine. Templates are plain `.php` files with `extract()`-ed variables. No special syntax, no compilation.

## Rendering Methods

### `render($template, $data, $options)`

Renders a template and **echoes** the result. This is the primary method for controllers.

```php
// Theme template — wrapped in layout automatically
app()->view()->render('home', array('posts' => $posts));

// Module template — NO layout (for admin panels)
app()->view()->render('categories:admin-list', array('categories' => $cats));

// Module template WITH site layout (for public module pages)
app()->view()->render('categories:category', $data, array('layout' => true));
```

### `fetch($template, $data, $options)`

Same as `render()` but **returns** the HTML string instead of echoing.

```php
$html = app()->view()->fetch('categories:admin-list', $data);
```

### `fetchPath($templatePath, $data)`

Renders a template from an **absolute filesystem path**. No layout wrapping, no `view.render` hook. Used internally by admin panels.

```php
$html = app()->view()->fetchPath('/path/to/template.php', $data);
```

### `partial($name, $params)`

Renders a reusable fragment. No layout wrapping. Errors are caught and logged (won't break the page).

```php
echo partial('pagination', array('paginator' => $paginator));
echo partial('seo:breadcrumbs', array('breadcrumbs' => $crumbs));
```

Global helper `partial()` is a shortcut for `app()->view()->partial()`.

## Template Resolution

### Full templates (`render` / `fetch`)

Resolution order:

| Priority | Path | When |
|----------|------|------|
| 1 | `themes/{theme}/templates/{template}.php` | Always checked first |
| 2 | `modules/{module}/views/{template}.php` | If `module:template` syntax used |
| 3 | `modules/{_module}/views/{template}.php` | If `_module` key in `$data` |

Theme always wins — this lets themes override any module template.

```php
// "home" resolves to themes/default/templates/home.php
app()->view()->render('home', $data);

// "categories:category" resolves to:
//   1. themes/default/templates/category.php (if exists)
//   2. modules/categories/views/category.php (fallback)
app()->view()->render('categories:category', $data, array('layout' => true));

// Nested paths work: "categories:partials/widget"
// -> modules/categories/views/partials/widget.php
```

### Partials

Resolution order:

| Priority | Path | When |
|----------|------|------|
| 1 | `themes/{theme}/templates/partials/{name}.php` | Theme partial |
| 2 | `themes/{theme}/templates/partials/{module}/{partial}.php` | Theme override of module partial |
| 3 | `modules/{module}/views/partials/{partial}.php` | Module partial |

```php
// "sidebar" -> themes/default/templates/partials/sidebar.php
partial('sidebar');

// "seo:breadcrumbs" resolution:
//   1. themes/default/templates/partials/seo/breadcrumbs.php (theme override)
//   2. modules/seo/views/partials/breadcrumbs.php (module default)
partial('seo:breadcrumbs', array('breadcrumbs' => $data));
```

## Layout Wrapping

The theme's `layout.php` is the site shell (header, nav, footer). Templates are rendered **inside** it via the `$content` variable.

**Rules:**
- Theme templates (`home`, `post`, `page`, etc.) — layout applied automatically
- Module templates (`module:template`) — **no layout** by default (designed for admin panels that have their own layout)
- Module templates with `array('layout' => true)` — layout applied (for public-facing module pages)

```
layout.php:
+------------------------------------------+
| <header>...</header>                      |
| <main><?php echo $content; ?></main>      |  <-- template output injected here
| <footer>...</footer>                      |
+------------------------------------------+
```

**When to use `layout => true`:**

If your module registers a public route (e.g., `/category/{slug}`) and renders a module template, pass `array('layout' => true)` so the page gets the site header/footer:

```php
app()->view()->render('mymodule:public-page', $data, array('layout' => true));
```

If the theme provides its own `public-page.php`, the theme version is used automatically (with layout) and the option is not needed.

## Available in Templates

### Variables

All keys from `$data` are available as local variables:

```php
// Controller:
app()->view()->render('post', array('post' => $post, 'title' => 'My Post'));

// Template (post.php):
<h1><?php echo $this->escape($post['title']); ?></h1>
<title><?php echo $this->escape($title); ?></title>
```

In layout templates, `$content` contains the rendered inner template.

### `$this` Methods

Templates have access to the View instance as `$this`:

| Method | Description |
|--------|-------------|
| `$this->escape($value)` | HTML-escape a string (ENT_QUOTES, UTF-8). Arrays escaped recursively. |
| `$this->e($value)` | Alias for `escape()` |
| `$this->asset('css/style.css')` | Theme asset URL: `/themes/default/assets/css/style.css` |
| `$this->moduleAsset('css/style.css')` | Module asset URL (uses `_module` from context) |
| `$this->moduleAsset('admin', 'css/style.css')` | Explicit module asset URL with version cache-busting |

### Global Helpers

Available in all templates:

| Helper | Description |
|--------|-------------|
| `e($value)` | Same as `$this->escape()` |
| `base_url('/path')` | Full URL: `https://site.com/path` |
| `partial('name', $params)` | Render a partial |
| `config('key', $default)` | Read config value |
| `t('translation.key')` | Translate a string |
| `clock()->formatDate($ts)` | Format a timestamp |
| `app()` | Application instance |

## Template Hierarchy

Templates are resolved in order of specificity:

**Pages:** `page-{template}.php` > `page-{slug}.php` > `page.php`

**Posts:** `post-{template}.php` > `post-{category}.php` > `post-{slug}.php` > `post.php`

This is handled by `PageController`, not the View class. The first existing template is used.

## Hooks

| Hook | Data | Description |
|------|------|-------------|
| `view.render` | `string` (HTML) | Post-process all rendered output. Return modified HTML. |

Fired after layout wrapping, before output. Useful for injecting scripts, modifying HTML, etc.

## File Organization

### Theme templates

```
themes/default/
  templates/
    layout.php              # Site shell (required)
    home.php                # Home page
    blog.php                # Blog listing
    post.php                # Single post
    page.php                # Single page
    404.php                 # Not found
    category.php            # Category listing (overrides module)
    partials/
      pagination.php        # Reusable pagination
      sidebar.php           # Sidebar widget
      seo/
        breadcrumbs.php     # Override of seo module partial
  assets/
    css/style.css
```

### Module templates

```
modules/mymodule/
  views/
    admin-list.php          # Admin list view
    admin-edit.php          # Admin edit form
    public-page.php         # Public page (use layout option)
    partials/
      widget.php            # Reusable fragment
  assets/
    css/style.css
```

## Quick Reference

| Want to... | Use |
|------------|-----|
| Render a page with site layout | `render('template', $data)` |
| Render admin content (no layout) | `fetch('module:template', $data)` then `renderAdmin()` |
| Render public module page with layout | `render('module:template', $data, array('layout' => true))` |
| Include a reusable fragment | `partial('name', $params)` or `partial('module:name', $params)` |
| Let themes override module template | Just use `render('module:template')` — theme checked first |
| Let themes override module partial | Theme puts file at `partials/{module}/{name}.php` |
| Get theme asset URL | `$this->asset('css/style.css')` |
| Get module asset URL | `$this->moduleAsset('css/style.css')` |
