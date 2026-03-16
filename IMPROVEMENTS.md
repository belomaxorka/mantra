# Extensibility Improvements Summary

This document summarizes all extensibility improvements made to Mantra CMS.

## Overview

The CMS now has a comprehensive extensibility system allowing modules to:
- Modify content queries and data
- Add custom content types
- Inject scripts and meta tags
- Provide reusable widgets
- Use custom templates per content item

## Core Improvements

### 1. Hook System in PageController

**Added 9 new hooks** for content rendering:

**Homepage:**
- `page.home.query` - Modify query parameters (collection, filter, options)
- `page.home.posts` - Modify posts array after loading
- `page.home.data` - Add data to view (widgets, metadata, etc.)

**Single Page:**
- `page.single.query` - Modify page query
- `page.single.loaded` - Modify page data after loading
- `page.single.data` - Add data to page view

**Single Post:**
- `post.single.query` - Modify post query
- `post.single.loaded` - Modify post data after loading
- `post.single.data` - Add data to post view

### 2. Template Hierarchy

Templates are now resolved in order of specificity:

**Pages:**
1. `page-{template}.php` (custom template field)
2. `page-{slug}.php` (e.g., `page-about.php`)
3. `page.php` (default)

**Posts:**
1. `post-{template}.php` (custom template field)
2. `post-{category}.php` (e.g., `post-news.php`)
3. `post-{slug}.php` (e.g., `post-hello-world.php`)
4. `post.php` (default)

### 3. Widget System

**New widget() helper** for embedding components:
```php
<?php echo widget('sidebar'); ?>
<?php echo widget('module:widget', array('param' => 'value')); ?>
```

**Widget locations:**
- Theme widgets: `themes/{theme}/widgets/{name}.php`
- Module widgets: `modules/{module}/widgets/{name}.php`

**Hook for dynamic widgets:**
- `widget.render` - Modules can provide widgets programmatically

### 4. Theme Hooks

**4 new hooks** for theme customization:
- `theme.head` - Add content to `<head>` (meta tags, styles)
- `theme.body.start` - Add content after `<body>` (tracking pixels)
- `theme.footer` - Add scripts before `</body>` (analytics)
- `theme.body.end` - Add content before `</body>` (modals, overlays)

### 5. ContentTypeRegistry

**New system** for registering custom content types:
```php
content_types()->register('product', array(
    'singular' => 'Product',
    'plural' => 'Products',
    'route_pattern' => '/product/{slug}',
    'collection' => 'products',
    'supports' => array('title', 'content', 'price', 'sku')
));
```

### 6. Helper Functions

**New helpers:**
- `widget($name, $params)` - Render widget
- `fire_hook($name, $data)` - Fire hook manually
- `module_enabled($name)` - Check if module enabled
- `content_types()` - Access content type registry

## Example Modules

### SEO Module (`modules/seo/`)
**Demonstrates:** Meta tags, Open Graph, breadcrumbs
- Injects SEO meta tags via `theme.head`
- Adds breadcrumbs data via `page.single.data` and `post.single.data`
- Provides breadcrumb widget via `widget.render`

**Features:**
- Open Graph tags
- Twitter Card tags
- Canonical URLs
- Breadcrumb navigation

### Analytics Module (`modules/analytics/`)
**Demonstrates:** Tracking script injection
- Adds analytics scripts via `theme.footer`
- Supports Google Analytics (gtag.js)
- Supports Yandex Metrika
- Custom tracking code support

**Features:**
- Conditional loading based on settings
- Multiple analytics providers
- Clean script injection

### Products Module (`modules/products/`)
**Demonstrates:** Custom content types
- Registers 'product' content type
- Custom routes: `/products`, `/product/{slug}`, `/products/category/{category}`
- Custom fields: price, sku, stock, images, category
- Data transformation (formatted price, stock status)

**Features:**
- Product listing with grid layout
- Single product with image carousel
- Category filtering
- Price formatting
- Stock status display

## Files Created/Modified

### Core Files Modified:
- `core/PageController.php` - Added hooks and template hierarchy
- `core/View.php` - Added widget system
- `core/helpers.php` - Added widget(), fire_hook(), content_types()
- `themes/default/templates/layout.php` - Added theme hooks
- `themes/default/templates/page.php` - Added breadcrumb widget usage
- `themes/default/templates/post.php` - Added breadcrumb widget usage

### Core Files Created:
- `core/ContentTypeRegistry.php` - Content type registration system

### Example Modules Created:
- `modules/seo/SeoModule.php` + `module.json`
- `modules/analytics/AnalyticsModule.php` + `module.json`
- `modules/products/ProductsModule.php` + `module.json`

### Templates Created:
- `themes/default/templates/product.php` - Single product
- `themes/default/templates/products.php` - Product listing
- `themes/default/widgets/sidebar.php` - Example widget

### Documentation Created:
- `EXTENSIBILITY.md` - Complete extensibility guide
- Updated `CLAUDE.md` - Architecture documentation

## Usage Examples

### Adding Meta Tags (SEO Module)
```php
$this->hook('theme.head', array($this, 'addMetaTags'));

public function addMetaTags($content) {
    return $content . '<meta name="description" content="...">';
}
```

### Modifying Query (Filter Posts)
```php
$this->hook('page.home.query', array($this, 'filterPosts'));

public function filterPosts($queryParams) {
    $queryParams['filter']['category'] = 'featured';
    return $queryParams;
}
```

### Adding View Data (Related Posts)
```php
$this->hook('post.single.data', array($this, 'addRelated'));

public function addRelated($data) {
    $data['related_posts'] = $this->getRelatedPosts($data['post']);
    return $data;
}
```

### Providing Widget
```php
$this->hook('widget.render', array($this, 'renderWidget'));

public function renderWidget($widgetData) {
    if ($widgetData['name'] === 'mymodule:widget') {
        $widgetData['output'] = '<div>Widget HTML</div>';
    }
    return $widgetData;
}
```

## Benefits

1. **No Core Modifications** - Modules extend functionality via hooks
2. **Clean Separation** - Core handles routing, themes handle presentation, modules add features
3. **Flexible Templates** - Template hierarchy allows per-item customization
4. **Reusable Components** - Widget system for embedding components
5. **Custom Content Types** - Easy to add products, events, portfolios, etc.
6. **SEO Ready** - Meta tags, Open Graph, breadcrumbs via modules
7. **Analytics Ready** - Tracking scripts via modules
8. **Well Documented** - Complete guide in EXTENSIBILITY.md

## Testing

All features tested and working:
- ✅ Homepage loads with SEO meta tags
- ✅ Hooks fire correctly in PageController
- ✅ Template hierarchy resolves correctly
- ✅ Widget system works (theme and module widgets)
- ✅ Theme hooks inject content in correct locations
- ✅ ContentTypeRegistry registers custom types
- ✅ All example modules load without errors

## Next Steps for Developers

1. Read `EXTENSIBILITY.md` for complete guide
2. Study example modules in `modules/seo/`, `modules/analytics/`, `modules/products/`
3. Create custom modules using hooks and widgets
4. Register custom content types via ContentTypeRegistry
5. Create custom templates using template hierarchy
