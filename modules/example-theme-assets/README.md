# Example: Theme Assets Module

Demonstrates how to load custom CSS and JavaScript files in the public theme (frontend).

## Module API Methods for Public Theme

### 1. Enqueue External Files

```php
public function init() {
    // Load CSS file in <head>
    $this->enqueueStyle('css/style.css');

    // Load JS file before </body>
    $this->enqueueScript('js/script.js');
}
```

### 2. Add Inline Styles

```php
public function init() {
    $this->addInlineStyle('
        .my-custom-class {
            color: blue;
        }
    ');
}
```

### 3. Add Inline Scripts

```php
public function init() {
    $this->addInlineScript("
        console.log('Module loaded');
    ");
}
```

### 4. Conditional Loading

```php
public function init() {
    $this->hook('theme.head', array($this, 'conditionalAssets'));
}

public function conditionalAssets($content) {
    $path = request()->path();

    if (strpos($path, '/blog') === 0) {
        $url = $this->asset('css/blog.css');
        return $content . "\n    <link rel=\"stylesheet\" href=\"" . e($url) . "\">";
    }

    return $content;
}
```

## Complete Module API Reference

### Asset URLs
- `$this->asset('css/style.css')` - Get full URL to asset file
- `$this->getUrl()` - Get module URL path
- `$this->getBaseUrl()` - Get full module URL with domain

### Admin Panel Assets
- `$this->enqueueAdminStyle($path)` - Load CSS in admin
- `$this->enqueueAdminScript($path)` - Load JS in admin
- `$this->addAdminInlineStyle($css)` - Add inline CSS in admin
- `$this->addAdminInlineScript($js)` - Add inline JS in admin

### Public Theme Assets
- `$this->enqueueStyle($path)` - Load CSS in theme
- `$this->enqueueScript($path)` - Load JS in theme
- `$this->addInlineStyle($css)` - Add inline CSS in theme
- `$this->addInlineScript($js)` - Add inline JS in theme

## Features Demonstrated

This module adds to the public theme:
- Smooth scrolling for anchor links
- Lazy loading for images with `data-lazy-src` attribute
- "Read more" functionality for elements with `data-read-more` attribute
- Back to top button
- Custom button and alert styles
- Global `ThemeUtils` object with utility functions

## File Structure

```
modules/example-theme-assets/
├── ExampleThemeAssetsModule.php
├── module.json
├── README.md
└── assets/
    ├── css/
    │   └── theme-custom.css
    └── js/
        └── theme-custom.js
```

## Usage

Enable this module in Settings → Modules to see the examples in action on your public site.
