# Module System Refactoring

## Overview

The module system has been refactored to provide a clean, flexible, and standardized architecture for building modular applications.

## Key Improvements

### 1. Standardized Module Manifest

All modules now use a consistent `module.json` structure:

```json
{
  "id": "module-name",
  "name": "Module Display Name",
  "description": "Module description",
  "version": "1.0.0",
  "author": "Author Name",
  "type": "feature",
  "capabilities": ["routes", "settings"],
  "dependencies": []
}
```

**Required fields**: `id`, `name`, `version`

### 2. Module Interface

All modules implement `ModuleInterface` providing:
- Lifecycle methods: `init()`, `onEnable()`, `onDisable()`, `onUninstall()`
- Metadata access: `getId()`, `getName()`, `getVersion()`, etc.
- Capability checks: `hasCapability()`, `isDisableable()`, `isDeletable()`

### 3. Module Types

Modules are categorized by type:
- `core` - Essential system modules (cannot be disabled)
- `feature` - Feature modules (can be enabled/disabled)
- `admin` - Admin panel extensions
- `integration` - Third-party integrations
- `theme` - Theme-related modules
- `utility` - Helper/utility modules
- `custom` - User-created modules

### 4. Module Capabilities

Modules declare their capabilities:
- `routes` - Registers routes
- `hooks` - Provides hooks
- `content_type` - Custom content types
- `admin_ui` - Admin interface
- `settings` - Configurable settings
- `widgets` - Provides widgets
- `templates` - Provides templates
- `translations` - Provides translations
- `api` - API endpoints
- `cli` - CLI commands
- `middleware` - Middleware
- `assets` - Static assets

### 5. Enhanced Module Manager

New ModuleManager features:
- `getModulesByType()` - Filter modules by type
- `getModulesByCapability()` - Filter by capability
- `enableModule()` / `disableModule()` - Runtime enable/disable
- `discoverModules()` - Discover all available modules
- `getModuleInfo()` - Get detailed module information

### 6. Module Validation

`ModuleValidator` class provides:
- Manifest validation
- Structure validation
- Batch validation of all modules

## Breaking Changes

### Manifest Structure

**Old** (inconsistent):
```json
{
  "name": "admin",
  "version": "1.0.0"
}
```

**New** (standardized):
```json
{
  "id": "admin",
  "name": "Admin Panel",
  "version": "1.0.0",
  "type": "core"
}
```

### Module Class Constructor

**Old**:
```php
public function __construct($manifest) {
    $this->manifest = $manifest;
}
```

**New**:
```php
public function __construct($manifest, $moduleId, $modulePath) {
    $this->manifest = $manifest;
    $this->moduleId = $moduleId;
    $this->modulePath = $modulePath;
}
```

### Removed Backward Compatibility

- No support for `requires` field (use `dependencies`)
- No support for `display_name` field (use `name`)
- No localized descriptions in manifest (use translation system)
- Removed fallback logic for missing fields

## Migration Guide

### Step 1: Update module.json

Add required fields:
```json
{
  "id": "your-module",
  "name": "Your Module",
  "version": "1.0.0"
}
```

### Step 2: Add Type and Capabilities

```json
{
  "type": "feature",
  "capabilities": ["routes", "settings"]
}
```

### Step 3: Update Dependencies

Rename `requires` to `dependencies`:
```json
{
  "dependencies": ["admin", "pages"]
}
```

### Step 4: Validate Module

```php
$errors = ModuleValidator::validateStructure('your-module');
if (empty($errors)) {
    echo "Module is valid!";
}
```

## New Features

### Runtime Module Management

```php
// Enable module
app()->modules()->enableModule('my-module');

// Disable module
app()->modules()->disableModule('my-module');

// Get module info
$info = app()->modules()->getModuleInfo('my-module');
```

### Module Discovery

```php
// Discover all available modules
$modules = app()->modules()->discoverModules();

foreach ($modules as $id => $data) {
    echo "{$id}: {$data['manifest']['name']}\n";
}
```

### Filter by Type/Capability

```php
// Get all admin modules
$adminModules = app()->modules()->getModulesByType(ModuleType::ADMIN);

// Get modules with settings
$settingsModules = app()->modules()->getModulesByCapability(ModuleCapability::SETTINGS);
```

### Lifecycle Hooks

```php
class MyModule extends Module {
    public function onEnable() {
        // Setup tasks
        $this->log('info', 'Module enabled');
        return true;
    }
    
    public function onDisable() {
        // Cleanup tasks
        $this->log('info', 'Module disabled');
        return true;
    }
    
    public function onUninstall() {
        // Remove all data
        $this->settings()->delete();
        return true;
    }
}
```

## Best Practices

1. **Always declare capabilities** - Helps with module discovery
2. **Use semantic versioning** - MAJOR.MINOR.PATCH
3. **Set appropriate type** - Helps with organization
4. **Document dependencies** - Ensures proper load order
5. **Implement lifecycle methods** - Clean setup/teardown
6. **Use module logger** - `$this->log()` adds module context
7. **Validate manifests** - Use `ModuleValidator` in development

## Example Module

See `modules/example-module/` for a complete example demonstrating:
- Proper manifest structure
- Lifecycle methods
- Settings integration
- Route registration
- Hook usage
- View templates

## API Reference

### Module Class

```php
// Metadata
$module->getId();
$module->getName();
$module->getVersion();
$module->getDescription();
$module->getType();
$module->getCapabilities();

// Permissions
$module->isDisableable();
$module->isDeletable();

// Helpers
$module->settings();
$module->log($level, $message);
$module->getPath();
```

### ModuleManager

```php
$manager = app()->modules();

// Loading
$manager->loadModule('module-id');
$manager->isLoaded('module-id');

// Access
$manager->getModule('module-id');
$manager->getModules();

// Filtering
$manager->getModulesByType(ModuleType::FEATURE);
$manager->getModulesByCapability(ModuleCapability::ROUTES);

// Management
$manager->enableModule('module-id');
$manager->disableModule('module-id');
$manager->discoverModules();
```

### ModuleValidator

```php
// Validate manifest
$errors = ModuleValidator::validateManifest($manifest);

// Validate structure
$errors = ModuleValidator::validateStructure('module-id');

// Validate all
$results = ModuleValidator::validateAll();
```

## Testing

```php
// Check module validity
$errors = ModuleValidator::validateStructure('my-module');
assert(empty($errors), 'Module should be valid');

// Test lifecycle
$module = app()->modules()->getModule('my-module');
assert($module->onEnable(), 'Module should enable successfully');
assert($module->onDisable(), 'Module should disable successfully');
```

## Further Reading

- [Creating Modules](CREATING_MODULES.md)
- [Module Manifest Specification](MODULE_MANIFEST.md)
- [Module System Documentation](MODULE_SYSTEM.md)
