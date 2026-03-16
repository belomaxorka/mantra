# Creating Modules in Mantra CMS

This guide explains how to create modules using the new standardized module system.

## Quick Start

### 1. Create Module Structure

```bash
modules/
  my-module/
    module.json              # Module manifest
    MyModuleModule.php       # Main module class
```

### 2. Create module.json

```json
{
  "id": "my-module",
  "name": "My Module",
  "description": "Description of what my module does",
  "version": "1.0.0",
  "author": "Your Name",
  "type": "feature",
  "capabilities": ["routes", "settings"],
  "dependencies": []
}
```

### 3. Create Module Class

```php
<?php
/**
 * MyModuleModule - Brief description
 */

class MyModuleModule extends Module {
    
    public function init() {
        // Register hooks
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/my-route', array($this, 'handleRoute'));
        return $data;
    }
    
    public function handleRoute() {
        echo 'Hello from My Module!';
    }
}
```

### 4. Enable Module

Add to `content/settings/config.json`:

```json
{
  "modules": {
    "enabled": ["admin", "pages", "my-module"]
  }
}
```

## Module Types

Choose the appropriate type for your module:

### Core Module
```json
{
  "type": "core",
  "admin": {
    "disableable": false,
    "deletable": false
  }
}
```
- Essential system modules
- Cannot be disabled or deleted
- Examples: admin, auth, database

### Feature Module
```json
{
  "type": "feature"
}
```
- Adds new functionality
- Can be disabled/enabled
- Examples: pages, posts, products

### Admin Module
```json
{
  "type": "admin",
  "capabilities": ["admin_ui", "routes"]
}
```
- Extends admin panel
- Provides admin interface
- Examples: admin-pages, admin-settings

### Integration Module
```json
{
  "type": "integration",
  "capabilities": ["settings", "hooks"]
}
```
- Third-party service integration
- Usually configuration-driven
- Examples: google-analytics, mailchimp

## Module Capabilities

Declare what your module provides:

```json
{
  "capabilities": [
    "routes",        // Registers routes
    "hooks",         // Provides hooks for other modules
    "content_type",  // Registers custom content types
    "admin_ui",      // Provides admin interface
    "settings",      // Has configurable settings
    "widgets",       // Provides widgets
    "templates",     // Provides templates
    "translations",  // Provides translations
    "api",           // Provides API endpoints
    "middleware",    // Provides middleware
    "assets"         // Provides CSS/JS assets
  ]
}
```

## Module Class API

### Lifecycle Methods

```php
class MyModuleModule extends Module {
    
    // Called when module is loaded
    public function init() {
        // Register hooks, routes, etc.
    }
    
    // Called when module is enabled
    public function onEnable() {
        // Setup tasks (create tables, etc.)
        return true; // Return false to prevent enabling
    }
    
    // Called when module is disabled
    public function onDisable() {
        // Cleanup tasks
        return true;
    }
    
    // Called when module is uninstalled
    public function onUninstall() {
        // Remove all module data
        return true;
    }
}
```

### Helper Methods

```php
// Register hooks
$this->hook('hook.name', array($this, 'callback'), $priority);

// Fire hooks
$data = $this->fireHook('hook.name', $data);

// Register routes
$this->route('GET', '/path', array($this, 'handler'));

// Get config
$value = $this->config('key.path', 'default');

// Render view
$this->view('module:template', array('data' => $value));

// Get module settings
$settings = $this->settings();
$value = $settings->get('key', 'default');

// Log with module context
$this->log('info', 'Message', array('context' => 'data'));

// Get module path
$path = $this->getPath();

// Load module file
$this->loadFile('includes/helper.php');
```

### Module Information

```php
// Get module metadata
$id = $this->getId();                    // "my-module"
$name = $this->getName();                // "My Module"
$version = $this->getVersion();          // "1.0.0"
$description = $this->getDescription();  // "Description..."
$author = $this->getAuthor();            // "Your Name"
$type = $this->getType();                // "feature"

// Check capabilities
$capabilities = $this->getCapabilities();
$hasSettings = $this->hasCapability('settings');

// Check permissions
$canDisable = $this->isDisableable();
$canDelete = $this->isDeletable();

// Get dependencies
$deps = $this->getDependencies();
```

## Registering Routes

```php
public function init() {
    $this->hook('routes.register', array($this, 'registerRoutes'));
}

public function registerRoutes($data) {
    $router = $data['router'];
    
    // Simple routes
    $router->get('/path', array($this, 'handler'));
    $router->post('/path', array($this, 'handler'));
    
    // Routes with parameters
    $router->get('/post/{slug}', array($this, 'showPost'));
    
    // Routes with middleware
    $router->get('/admin/path', array($this, 'handler'))
           ->middleware(array($this, 'requireAuth'));
    
    return $data;
}

public function showPost($params) {
    $slug = $params['slug'];
    // Handle request
}
```

## Using Hooks

### Registering Hooks

```php
public function init() {
    // Register hook listener
    $this->hook('content.save', array($this, 'onContentSave'), 10);
}

public function onContentSave($data) {
    // Modify data
    $data['modified_by_module'] = true;
    
    // Return modified data
    return $data;
}
```

### Providing Hooks

```php
public function saveContent($content) {
    // Allow other modules to modify content before save
    $content = $this->fireHook('my-module.before_save', $content);
    
    // Save content
    // ...
    
    // Notify other modules after save
    $this->fireHook('my-module.after_save', $content);
}
```

## Module Settings

### 1. Create settings.schema.php

```php
<?php
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'General Settings',
            'fields' => array(
                array(
                    'path' => 'enabled',
                    'type' => 'toggle',
                    'title' => 'Enable Module',
                    'default' => true,
                ),
                array(
                    'path' => 'api_key',
                    'type' => 'text',
                    'title' => 'API Key',
                    'default' => '',
                ),
            ),
        ),
    ),
);
```

### 2. Use Settings in Module

```php
public function init() {
    $settings = $this->settings();
    
    if ($settings->get('enabled', true)) {
        // Module is enabled
        $apiKey = $settings->get('api_key', '');
    }
}
```

## Registering Content Types

```php
public function init() {
    $this->registerProductType();
}

private function registerProductType() {
    content_types()->register('product', array(
        'singular' => 'Product',
        'plural' => 'Products',
        'route_pattern' => '/product/{slug}',
        'collection' => 'products',
        'supports' => array(
            'title',
            'content',
            'slug',
            'status',
            'price',
            'sku',
        ),
    ));
}
```

## Adding Admin UI

```php
public function init() {
    // Add sidebar item
    app()->hooks()->register('admin.sidebar', function ($items) {
        $items[] = array(
            'id' => 'my-module',
            'title' => 'My Module',
            'icon' => 'bi-star',
            'group' => 'Content',
            'order' => 20,
            'url' => base_url('/admin/my-module'),
        );
        return $items;
    });
    
    // Register admin routes
    app()->hooks()->register('routes.register', function ($data) {
        $admin = app()->modules()->getModule('admin');
        if ($admin && method_exists($admin, 'adminRoute')) {
            $admin->adminRoute('GET', 'my-module', array($this, 'adminPage'));
        }
        return $data;
    });
}

public function adminPage() {
    $admin = app()->modules()->getModule('admin');
    $content = '<h1>My Module Admin Page</h1>';
    return $admin->render('My Module', $content);
}
```

## Module Dependencies

```json
{
  "id": "my-module",
  "dependencies": ["admin", "pages"]
}
```

Dependencies are loaded before your module. If a dependency is missing, your module won't load.

## Translations

### 1. Create lang/en.php

```php
<?php
return array(
    'my-module.title' => 'My Module',
    'my-module.description' => 'Module description',
    'my-module.settings.enabled' => 'Enable Module',
);
```

### 2. Use Translations

```php
$title = t('my-module.title');

// With parameters
$message = t('my-module.welcome', array('name' => $userName));
```

## Best Practices

1. **Use kebab-case for module IDs**: `my-awesome-module`
2. **Follow naming convention**: `MyAwesomeModuleModule.php`
3. **Declare all capabilities**: Helps with module discovery
4. **Document dependencies**: Ensure proper load order
5. **Use semantic versioning**: `1.0.0`, `1.1.0`, `2.0.0`
6. **Provide settings schema**: Make module configurable
7. **Add translations**: Support multiple languages
8. **Log important events**: Use `$this->log()`
9. **Handle errors gracefully**: Return false from lifecycle methods on failure
10. **Clean up on uninstall**: Remove all module data in `onUninstall()`

## Testing Your Module

```php
// Check if module is loaded
if (app()->modules()->isLoaded('my-module')) {
    // Module is active
}

// Get module instance
$module = app()->modules()->getModule('my-module');

// Get module info
$info = app()->modules()->getModuleInfo('my-module');
```

## Example: Complete Module

See `modules/example-integration/` for a complete working example.

## Troubleshooting

### Module not loading
- Check module ID matches directory name
- Verify class name follows convention
- Check dependencies are available
- Look for errors in logs

### Settings not working
- Ensure `settings.schema.php` exists
- Verify schema structure
- Check file permissions on `content/settings/`

### Routes not working
- Register routes in `routes.register` hook
- Return modified `$data` from hook
- Check route priority if conflicts occur

## Further Reading

- [Module System Documentation](MODULE_SYSTEM.md)
- [Module Manifest Specification](MODULE_MANIFEST.md)
- [Integration Points](INTEGRATION_POINTS.md)
