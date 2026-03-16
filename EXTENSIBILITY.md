# Extensibility Guide

This guide demonstrates how to extend Mantra CMS using hooks, widgets, and custom content types.

## Hooks System

Hooks allow modules to modify behavior without changing core code.

### Available Hooks

**Page Rendering:**
- `page.home.query` - Modify homepage query parameters
- `page.home.posts` - Modify posts data before rendering
- `page.home.data` - Add data to homepage view
- `page.single.query` - Modify single page query
- `page.single.loaded` - Modify page data after loading
- `page.single.data` - Add data to page view
- `post.single.query` - Modify single post query
- `post.single.loaded` - Modify post data after loading
- `post.single.data` - Add data to post view

**Theme Hooks:**
- `theme.head` - Add content to `<head>` section
- `theme.body.start` - Add content after `<body>` tag
- `theme.footer` - Add scripts before `</body>`
- `theme.body.end` - Add content before `</body>`
- `theme.navigation` - Add items to main navigation menu
- `theme.footer.links` - Add links to footer

**Admin Hooks:**
- `admin.sidebar` - Add items to admin sidebar menu
- `admin.quick_actions` - Add quick action buttons to dashboard
- `admin.head` - Add content to admin `<head>`
- `admin.footer` - Add scripts to admin footer

**View Rendering:**
- `view.render` - Modify rendered content
- `widget.render` - Provide custom widgets

### Using Hooks in Modules

```php
class MyModule extends Module {
    public function init() {
        // Register hook
        $this->hook('page.home.data', array($this, 'addCustomData'));
    }

    public function addCustomData($data) {
        // Add custom data to view
        $data['custom_field'] = 'Custom value';
        return $data;
    }
}
```

## Widget System

Widgets are reusable components that can be embedded in templates.

### Creating a Widget

**Theme widget** (`themes/default/widgets/sidebar.php`):
```php
<aside class="sidebar">
    <h3>Sidebar Widget</h3>
    <p>Widget content here</p>
</aside>
```

**Module widget** (`modules/mymodule/widgets/mywidget.php`):
```php
<div class="my-widget">
    <h4><?php echo isset($title) ? e($title) : 'Default Title'; ?></h4>
    <p><?php echo isset($content) ? e($content) : ''; ?></p>
</div>
```

### Using Widgets in Templates

```php
<!-- Theme widget -->
<?php echo widget('sidebar'); ?>

<!-- Module widget with parameters -->
<?php echo widget('mymodule:mywidget', array(
    'title' => 'Custom Title',
    'content' => 'Widget content'
)); ?>
```

### Providing Widgets via Hooks

```php
class MyModule extends Module {
    public function init() {
        $this->hook('widget.render', array($this, 'renderWidget'));
    }

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
}
```

## Template Hierarchy

Templates are resolved in order of specificity:

### Pages
1. `page-{template}.php` (if page has custom template field)
2. `page-{slug}.php` (e.g., `page-about.php`)
3. `page.php` (default)

### Posts
1. `post-{template}.php` (if post has custom template field)
2. `post-{category}.php` (e.g., `post-news.php`)
3. `post-{slug}.php` (e.g., `post-hello-world.php`)
4. `post.php` (default)

### Example

Create `themes/default/templates/page-contact.php` for a custom contact page template.

## Custom Content Types

Register new content types (products, events, etc.) using ContentTypeRegistry.

### Registering a Content Type

```php
class ProductsModule extends Module {
    public function init() {
        // Register custom content type
        content_types()->register('product', array(
            'singular' => 'Product',
            'plural' => 'Products',
            'route_pattern' => '/product/{slug}',
            'collection' => 'products',
            'supports' => array('title', 'content', 'slug', 'price', 'sku', 'stock', 'images')
        ));

        // Register route
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/product/{slug}', array($this, 'showProduct'));
        return $data;
    }

    public function showProduct($params) {
        $db = new Database();
        $products = $db->query('products', array(
            'slug' => $params['slug'],
            'status' => 'published'
        ));

        if (empty($products)) {
            http_response_code(404);
            return;
        }

        $view = new View();
        $view->render('product', array(
            'product' => $products[0]
        ));
    }
}
```

**Complete Example:** See `modules/products/ProductsModule.php` for a full implementation with:
- Product listing page
- Single product page with image carousel
- Category filtering
- Price formatting and stock status
- Custom hooks for product data

## Complete Examples

### Example Integration Module (`modules/example-integration/`)
Demonstrates integration points:
- Adding navigation items via `theme.navigation` hook
- Adding footer links via `theme.footer.links` hook
- Adding admin sidebar items via `admin.sidebar` hook
- Adding dashboard quick actions via `admin.quick_actions` hook
- Respecting module settings (show/hide in navigation)

### SEO Module (`modules/seo/`)
Demonstrates meta tags, Open Graph, breadcrumbs:
- Adding meta tags to `<head>` via `theme.head` hook
- Modifying page data via `page.single.data` and `post.single.data` hooks
- Providing breadcrumb widgets via `widget.render` hook
- Using multiple hooks together

### Analytics Module (`modules/analytics/`)
Demonstrates tracking script injection:
- Adding scripts to footer via `theme.footer` hook
- Google Analytics integration
- Yandex Metrika integration
- Module settings usage
- Conditional script loading

### Products Module (`modules/products/`)
Demonstrates custom content types:
- Registering custom content type via ContentTypeRegistry
- Custom routes for products
- Product listing and single product pages
- Custom fields (price, sku, stock, images)
- Data transformation hooks
- Custom templates with image carousel

## Enabling Modules

Add to `content/settings/config.json`:
```json
{
    "modules": {
        "enabled": ["admin", "seo", "analytics", "products", "example-integration"]
    }
}
```

## Helper Functions

- `widget($name, $params)` - Render a widget
- `fire_hook($name, $data)` - Fire a hook manually
- `module_enabled($name)` - Check if module is enabled
- `content_types()` - Access content type registry

## Best Practices

1. **Use hooks instead of modifying core** - Always prefer hooks over editing core files
2. **Return modified data** - Hooks should return the modified data
3. **Check for existence** - Always check if data exists before using it
4. **Escape output** - Use `e()` or `$this->escape()` for user data
5. **Document your hooks** - Add comments explaining what your hooks do
