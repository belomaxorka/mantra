# Example: Admin Assets Module

Demonstrates how to load custom CSS and JavaScript files in the admin panel.

## How It Works

### Loading CSS (admin.head hook)

```php
public function addAdminStyles($content) {
    $moduleUrl = base_url('/modules/your-module');

    $styles = <<<HTML
    <link rel="stylesheet" href="{$moduleUrl}/assets/css/admin.css">
    <style>
        /* Inline styles */
    </style>
HTML;

    return $content . $styles;
}
```

### Loading JS (admin.footer hook)

```php
public function addAdminScripts($content) {
    $moduleUrl = base_url('/modules/your-module');

    $scripts = <<<HTML
    <script src="{$moduleUrl}/assets/js/admin.js"></script>
    <script>
        // Inline scripts
    </script>
HTML;

    return $content . $scripts;
}
```

## File Structure

```
modules/your-module/
├── YourModule.php
├── module.json
└── assets/
    ├── css/
    │   └── admin.css
    └── js/
        └── admin.js
```

## Features Demonstrated

1. **External CSS/JS files** - Loading from module's assets directory
2. **Inline styles/scripts** - Embedding directly in the hook
3. **Custom functionality** - Tooltips, confirmations, auto-save
4. **Global utilities** - Exposing functions to other scripts

## Usage

Enable this module in Settings → Modules to see the examples in action.

The module adds:
- Hover effects on cards
- Custom button and alert styles
- Auto-save functionality for forms with `data-example-autosave` attribute
- Confirmation dialogs for buttons with `data-example-confirm` attribute
- Global `ExampleAdminUtils.showNotification()` function
