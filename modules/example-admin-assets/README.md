# Example: Admin Assets Module

Demonstrates how to load custom CSS and JavaScript files in the admin panel using the Module API.

## Module API Methods

### 1. Enqueue External Files (Recommended)

```php
public function init() {
    // Load CSS file from assets/css/
    $this->enqueueAdminStyle('css/admin.css');

    // Load JS file from assets/js/
    $this->enqueueAdminScript('js/admin.js');
}
```

### 2. Add Inline Styles

```php
public function init() {
    $this->addAdminInlineStyle('
        .my-custom-class {
            color: red;
        }
    ');
}
```

### 3. Add Inline Scripts

```php
public function init() {
    $this->addAdminInlineScript("
        console.log('Module loaded');
    ");
}
```

### 4. Get Asset URLs

```php
// Get asset URL
$cssUrl = $this->asset('css/style.css');
// Returns: http://example.com/modules/your-module/assets/css/style.css

// Get module URL
$moduleUrl = $this->getUrl();
// Returns: /modules/your-module

// Get module base URL
$baseUrl = $this->getBaseUrl();
// Returns: http://example.com/modules/your-module
```

### 5. Manual Hooks (Advanced)

```php
public function init() {
    // For conditional loading or complex scenarios
    $this->hook('admin.head', array($this, 'customHeadContent'));
}

public function customHeadContent($content) {
    $url = $this->asset('css/conditional.css');
    return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
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
