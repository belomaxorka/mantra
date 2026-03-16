# Integration Points Guide

This guide explains how modules can integrate into different parts of the CMS using hooks.

## Overview

Mantra CMS provides **integration points** (hooks) where modules can inject content, add menu items, widgets, and more without modifying core or theme files.

## Public Theme Integration Points

### Navigation Menu (`theme.navigation`)

Add items to the main navigation menu.

**Hook**: `theme.navigation`

**Usage**:
```php
class MyModule extends Module {
    public function init() {
        $this->hook('theme.navigation', array($this, 'addNavItem'));
    }

    public function addNavItem($items) {
        if (!is_array($items)) {
            $items = array();
        }

        $items[] = array(
            'id' => 'my-page',
            'title' => 'My Page',
            'url' => base_url('/my-page'),
            'order' => 20,
            'active' => false, // Set to true if current page
        );

        return $items;
    }
}
```

**Item Structure**:
- `id` (string, required): Unique identifier
- `title` (string, required): Display text
- `url` (string, required): Link URL
- `order` (int, optional): Sort order (lower = first, default: 100)
- `active` (bool, optional): Highlight as active page
- `icon` (string, optional): Icon class (if theme supports)

### Sidebar Widgets (`theme.sidebar`)

Add widgets to the theme sidebar (if theme has one).

**Hook**: `theme.sidebar`

**Usage**:
```php
$this->hook('theme.sidebar', array($this, 'addSidebarWidget'));

public function addSidebarWidget($widgets) {
    if (!is_array($widgets)) {
        $widgets = array();
    }

    $widgets[] = array(
        'id' => 'my-widget',
        'title' => 'My Widget',
        'content' => '<div>Widget HTML</div>',
        'order' => 10,
    );

    return $widgets;
}
```

Or use the widget system:
```php
$widgets[] = array(
    'id' => 'my-widget',
    'widget' => 'mymodule:widget-name',
    'params' => array('key' => 'value'),
    'order' => 10,
);
```

### Footer Links (`theme.footer.links`)

Add links to the footer.

**Hook**: `theme.footer.links`

**Usage**:
```php
$this->hook('theme.footer.links', array($this, 'addFooterLink'));

public function addFooterLink($links) {
    if (!is_array($links)) {
        $links = array();
    }

    $links[] = array(
        'id' => 'privacy',
        'title' => 'Privacy Policy',
        'url' => base_url('/privacy'),
        'order' => 10,
    );

    return $links;
}
```

### Content Injection Hooks

**Available hooks**:
- `theme.head` - Add content to `<head>` (meta tags, styles)
- `theme.body.start` - Add content after `<body>` (tracking pixels)
- `theme.footer` - Add scripts before `</body>` (analytics)
- `theme.body.end` - Add content before `</body>` (modals, overlays)

**Usage**:
```php
$this->hook('theme.head', array($this, 'addMetaTags'));

public function addMetaTags($content) {
    return $content . '<meta name="description" content="...">';
}
```

## Admin Panel Integration Points

### Admin Sidebar (`admin.sidebar`)

Add items to the admin sidebar menu.

**Hook**: `admin.sidebar`

**Usage**:
```php
app()->hooks()->register('admin.sidebar', function ($items) {
    if (!is_array($items)) {
        $items = array();
    }

    $items[] = array(
        'id' => 'my-admin-page',
        'title' => 'My Admin Page',
        'icon' => 'bi-gear',
        'group' => 'Content',
        'order' => 20,
        'url' => base_url('/admin/my-page'),
    );

    return $items;
});
```

**Item Structure**:
- `id` (string, required): Unique identifier
- `title` (string|array, required): Display text or i18n spec
- `icon` (string, optional): Bootstrap Icons class (e.g., `bi-gear`)
- `group` (string|array, optional): Group name or i18n spec
- `order` (int, optional): Sort order (default: 100)
- `url` (string, required): Link URL
- `children` (array, optional): Nested menu items
- `active` (bool, optional): Auto-detected, but can be set manually
- `expanded` (bool, optional): Expand children by default

**Nested Menu Example**:
```php
$items[] = array(
    'id' => 'content',
    'title' => 'Content',
    'icon' => 'bi-file-text',
    'group' => 'General',
    'order' => 10,
    'children' => array(
        array(
            'id' => 'pages',
            'title' => 'Pages',
            'url' => base_url('/admin/pages'),
        ),
        array(
            'id' => 'posts',
            'title' => 'Posts',
            'url' => base_url('/admin/posts'),
        ),
    ),
);
```

### Quick Actions (`admin.quick_actions`)

Add quick action buttons to the dashboard.

**Hook**: `admin.quick_actions`

**Usage**:
```php
app()->hooks()->register('admin.quick_actions', function ($actions) {
    if (!is_array($actions)) {
        $actions = array();
    }

    $actions[] = array(
        'id' => 'create-page',
        'title' => 'Create Page',
        'icon' => 'bi-plus-circle',
        'url' => base_url('/admin/pages/create'),
        'order' => 10,
    );

    return $actions;
});
```

### Admin Head/Footer

**Available hooks**:
- `admin.head` - Add content to admin `<head>`
- `admin.footer` - Add scripts to admin footer

## Widget System

### Creating Widgets

**Theme widget** (`themes/{theme}/widgets/{name}.php`):
```php
<aside class="widget">
    <h3><?php echo isset($title) ? e($title) : 'Widget'; ?></h3>
    <div><?php echo isset($content) ? $content : ''; ?></div>
</aside>
```

**Module widget** (`modules/{module}/widgets/{name}.php`):
```php
<div class="my-widget">
    <h4><?php echo isset($title) ? e($title) : 'Default'; ?></h4>
    <p><?php echo isset($content) ? e($content) : ''; ?></p>
</div>
```

### Using Widgets in Templates

```php
<!-- Theme widget -->
<?php echo widget('sidebar'); ?>

<!-- Module widget with parameters -->
<?php echo widget('mymodule:widget', array(
    'title' => 'Custom Title',
    'content' => 'Widget content'
)); ?>
```

### Dynamic Widgets via Hook

```php
$this->hook('widget.render', array($this, 'renderWidget'));

public function renderWidget($widgetData) {
    if ($widgetData['name'] === 'mymodule:dynamic') {
        ob_start();
        ?>
        <div class="dynamic-widget">
            <p>Dynamically generated content</p>
        </div>
        <?php
        $widgetData['output'] = ob_get_clean();
    }
    return $widgetData;
}
```

## Content Hooks

### Page/Post Rendering

**Available hooks**:
- `page.home.query` - Modify homepage query
- `page.home.posts` - Modify posts data
- `page.home.data` - Add data to homepage view
- `page.single.query` - Modify page query
- `page.single.loaded` - Modify page data
- `page.single.data` - Add data to page view
- `post.single.query` - Modify post query
- `post.single.loaded` - Modify post data
- `post.single.data` - Add data to post view

**Example**:
```php
$this->hook('post.single.data', array($this, 'addRelatedPosts'));

public function addRelatedPosts($data) {
    $data['related_posts'] = $this->getRelatedPosts($data['post']);
    return $data;
}
```

## Best Practices

1. **Always check array type**: Hooks may receive non-array data
2. **Return modified data**: Always return the data, even if unchanged
3. **Use unique IDs**: Prevent conflicts with other modules
4. **Set order values**: Control where your items appear
5. **Escape output**: Use `e()` or `htmlspecialchars()` for user data
6. **Check module settings**: Respect user preferences (e.g., "show in navigation")

## Example: Complete Module Integration

```php
class MyModule extends Module {
    public function init() {
        // Add navigation item
        $this->hook('theme.navigation', array($this, 'addNavItem'));

        // Add admin sidebar item
        app()->hooks()->register('admin.sidebar', function ($items) {
            if (!is_array($items)) {
                $items = array();
            }

            $items[] = array(
                'id' => 'my-admin',
                'title' => 'My Module',
                'icon' => 'bi-puzzle',
                'group' => 'Content',
                'url' => base_url('/admin/my-module'),
                'order' => 30,
            );

            return $items;
        });

        // Add quick action
        app()->hooks()->register('admin.quick_actions', function ($actions) {
            if (!is_array($actions)) {
                $actions = array();
            }

            $actions[] = array(
                'id' => 'my-action',
                'title' => 'My Action',
                'icon' => 'bi-lightning',
                'url' => base_url('/admin/my-action'),
                'order' => 20,
            );

            return $actions;
        });

        // Add footer link
        $this->hook('theme.footer.links', array($this, 'addFooterLink'));

        // Register routes
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    public function addNavItem($items) {
        if (!is_array($items)) {
            $items = array();
        }

        // Only show if enabled in settings
        if (module_settings('mymodule')->get('show_in_nav', true)) {
            $items[] = array(
                'id' => 'my-page',
                'title' => 'My Page',
                'url' => base_url('/my-page'),
                'order' => 20,
            );
        }

        return $items;
    }

    public function addFooterLink($links) {
        if (!is_array($links)) {
            $links = array();
        }

        $links[] = array(
            'id' => 'my-link',
            'title' => 'My Link',
            'url' => base_url('/my-page'),
            'order' => 10,
        );

        return $links;
    }

    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/my-page', array($this, 'showPage'));
        return $data;
    }

    public function showPage() {
        $view = new View();
        $view->render('page', array(
            'title' => 'My Page',
            'content' => '<h1>My Module Page</h1>',
        ));
    }
}
```

## See Also

- `EXTENSIBILITY.md` - Complete extensibility guide
- `QUICK_ACTIONS.md` - Quick actions documentation
- `modules/seo/` - Example SEO module
- `modules/analytics/` - Example analytics module
