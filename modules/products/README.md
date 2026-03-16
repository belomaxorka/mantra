# Products Module

Example module demonstrating custom content types with self-contained templates.

## Features

- Custom content type registration via `ContentTypeRegistry`
- Custom routes for product listing and single product pages
- Self-contained templates with theme override support
- Product-specific data (price, SKU, stock, images)

## Template Architecture

This module uses the **smart fallback** pattern:

1. **Theme override** (optional): `themes/{theme}/templates/product.php`
2. **Module default** (fallback): `modules/products/views/product.php`

The module passes `_module: 'products'` to `View::render()`, which automatically falls back to module templates if the theme doesn't provide them.

### Benefits

- ✅ Module works out-of-the-box with any theme
- ✅ Themes can optionally customize product templates
- ✅ Module is self-contained and portable

## Usage

Enable in `content/settings/config.json`:

```json
{
  "modules": {
    "enabled": ["admin", "products"]
  }
}
```

## Routes

- `/products` - List all products
- `/product/{slug}` - Single product page
- `/products/category/{category}` - Products by category

## Hooks

- `product.single.query` - Modify product query parameters
- `product.single.loaded` - Modify loaded product data
- `product.single.data` - Add data to product view
