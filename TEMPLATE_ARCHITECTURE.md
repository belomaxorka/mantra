# Template Architecture

## Overview

Mantra CMS uses a flexible template system that allows modules to be self-contained while giving themes the ability to customize presentation.

## Template Resolution Order

When `View::render('template', $data)` is called, templates are resolved in this order:

1. **Theme template** (highest priority)
   - Path: `themes/{active_theme}/templates/{template}.php`
   - Allows themes to override any template

2. **Explicit module syntax**
   - Syntax: `View::render('module:template')`
   - Path: `modules/{module}/views/{template}.php`
   - Used by admin and internal module views

3. **Smart fallback via `_module` parameter**
   - Syntax: `View::render('template', ['_module' => 'products'])`
   - Path: `modules/{module}/views/{template}.php`
   - Automatic fallback for self-contained modules

## Two Patterns

### Pattern 1: Admin-style (Explicit Module Syntax)

Used for internal module views that should NOT be overridden by themes.

```php
// In AdminModule
$view = new View();
$view->render('admin:layout', $data);
```

- Template: `modules/admin/views/layout.php`
- Theme cannot override (by design)
- Used for admin panels, internal UIs

### Pattern 2: Content-style (Smart Fallback)

Used for public-facing content that themes MAY customize.

```php
// In ProductsModule
$view = new View();
$view->render('product', array_merge($data, [
    '_module' => 'products'
]));
```

- Default: `modules/products/views/product.php`
- Theme override: `themes/default/templates/product.php` (optional)
- Module works out-of-the-box, theme can customize

## Benefits

### For Module Authors
- ✅ Modules are self-contained and portable
- ✅ No dependency on specific themes
- ✅ Works immediately after installation

### For Theme Authors
- ✅ Can override any public template
- ✅ Full control over presentation
- ✅ Fallback to module defaults if not customized

## Example: Products Module

**Module provides default templates:**
```
modules/products/
├── ProductsModule.php
└── views/
    ├── product.php      (single product)
    └── products.php     (product listing)
```

**Theme can optionally override:**
```
themes/custom-theme/
└── templates/
    ├── product.php      (custom product design)
    └── products.php     (custom listing design)
```

If theme doesn't provide these templates, module defaults are used automatically.

## Migration Guide

### Old approach (theme-dependent):
```php
// Module depends on theme having the template
$view->render('product', $data);
// Breaks if theme doesn't have themes/*/templates/product.php
```

### New approach (self-contained):
```php
// Module provides default, theme can override
$view->render('product', array_merge($data, [
    '_module' => 'products'
]));
// Works with any theme, uses modules/products/views/product.php as fallback
```

## Best Practices

1. **Public content modules** (products, events, etc.)
   - Use Pattern 2 (smart fallback)
   - Provide default templates in `modules/{name}/views/`
   - Pass `_module` parameter

2. **Admin/internal modules**
   - Use Pattern 1 (explicit syntax)
   - Use `module:template` syntax
   - Prevent theme override

3. **Core content** (pages, posts)
   - No `_module` parameter
   - Must be provided by theme
   - Part of theme's responsibility
