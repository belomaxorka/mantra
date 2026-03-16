# Module Manifest Specification (module.json)

This document defines the standard structure for `module.json` files in Mantra CMS.

## Standard Structure

```json
{
  "id": "module-name",
  "name": "Module Display Name",
  "description": "Module description",
  "version": "1.0.0",
  "author": "Author Name",
  "license": "MIT",
  "homepage": "https://example.com",
  "type": "feature",
  "capabilities": ["routes", "settings", "admin_ui"],
  "dependencies": ["other-module"],
  "tags": ["content", "seo"],
  "admin": {
    "disableable": true,
    "deletable": true
  }
}
```

## Field Definitions

### Required Fields

#### `id` (string, required)
- **Format**: kebab-case (lowercase with hyphens)
- **Pattern**: `^[a-z0-9-]+$`
- **Example**: `"admin-pages"`, `"seo"`, `"example-integration"`
- **Purpose**: Unique identifier for the module
- **Note**: Used for file paths, class names, and internal references

#### `version` (string, required)
- **Format**: Semantic versioning (MAJOR.MINOR.PATCH)
- **Pattern**: `^\d+\.\d+\.\d+$`
- **Example**: `"1.0.0"`, `"2.3.1"`
- **Purpose**: Track module version for updates and compatibility

### Recommended Fields

#### `name` (string, recommended)
- **Format**: Human-readable display name
- **Example**: `"Admin Pages"`, `"SEO Module"`
- **Purpose**: Displayed in admin UI
- **Default**: Capitalized version of `id` if not provided

#### `description` (string|object, recommended)
- **Format**: Plain text or localized object
- **Examples**:
  - Simple: `"Manages site pages"`
  - Localized: `{"en": "Manages pages", "ru": "Управление страницами"}`
- **Purpose**: Brief description of module functionality

#### `author` (string, recommended)
- **Example**: `"Mantra CMS Team"`, `"John Doe"`
- **Purpose**: Module author/maintainer information

### Optional Fields

#### `type` (string, optional)
- **Values**: `"core"`, `"feature"`, `"admin"`, `"integration"`, `"theme"`, `"utility"`, `"custom"`
- **Default**: `"custom"`
- **Purpose**: Categorize modules for organization
- **Note**: Core modules cannot be disabled/deleted

#### `capabilities` (array, optional)
- **Values**: Array of capability strings
- **Available capabilities**:
  - `"routes"` - Registers routes
  - `"hooks"` - Provides hooks for other modules
  - `"content_type"` - Registers custom content types
  - `"admin_ui"` - Provides admin interface
  - `"settings"` - Has configurable settings
  - `"widgets"` - Provides widgets
  - `"templates"` - Provides templates
  - `"translations"` - Provides translations
  - `"api"` - Provides API endpoints
  - `"cli"` - Provides CLI commands
  - `"middleware"` - Provides middleware
  - `"assets"` - Provides static assets
- **Example**: `["routes", "settings", "admin_ui"]`
- **Purpose**: Declare module features for discovery

#### `dependencies` (array, optional)
- **Format**: Array of module IDs
- **Example**: `["admin", "pages"]`
- **Purpose**: Declare required modules
- **Note**: Dependencies are loaded before the module

#### `tags` (array, optional)
- **Format**: Array of keyword strings
- **Example**: `["content", "seo", "optimization"]`
- **Purpose**: Searchable keywords for module discovery

#### `license` (string, optional)
- **Example**: `"MIT"`, `"GPL-3.0"`, `"Proprietary"`
- **Purpose**: Module license information

#### `homepage` (string, optional)
- **Format**: Valid URL
- **Example**: `"https://github.com/user/module"`
- **Purpose**: Link to module documentation/repository

#### `admin` (object, optional)
- **Fields**:
  - `disableable` (boolean): Can module be disabled? Default: `true` (except core modules)
  - `deletable` (boolean): Can module be deleted? Default: `true` (except core modules)
- **Example**: `{"disableable": false, "deletable": false}`
- **Purpose**: Control module management in admin UI

## Module Class Naming Convention

The module class name is derived from the `id` field:

1. Split `id` by hyphens: `"admin-pages"` → `["admin", "pages"]`
2. Capitalize each part: `["Admin", "Pages"]`
3. Join and append "Module": `"AdminPagesModule"`

**Examples**:
- `"admin"` → `AdminModule`
- `"admin-pages"` → `AdminPagesModule`
- `"example-integration"` → `ExampleIntegrationModule`
- `"seo"` → `SeoModule`

## File Structure

```
modules/
  module-name/
    module.json              # Manifest (required)
    ModuleNameModule.php     # Main class (required)
    settings.schema.php      # Settings schema (optional)
    views/                   # Templates (optional)
    lang/                    # Translations (optional)
      en.php
      ru.php
    assets/                  # Static files (optional)
      css/
      js/
```

## Validation Rules

1. **ID Format**: Must match `^[a-z0-9-]+$`
2. **Version Format**: Must match semantic versioning (MAJOR.MINOR.PATCH)
3. **Type**: Must be one of the defined types
4. **Capabilities**: Must be valid capability names
5. **Dependencies**: Must reference existing module IDs
6. **Class File**: Must exist at `modules/{id}/{ClassName}Module.php`

## Best Practices

1. **Always include `id`**: Use kebab-case for consistency
2. **Use semantic versioning**: Follow MAJOR.MINOR.PATCH format
3. **Declare capabilities**: Help other modules discover your features
4. **Document dependencies**: Ensure proper load order
5. **Set appropriate type**: Helps with module organization
6. **Add meaningful tags**: Improve module discoverability

## Examples

### Minimal Module
```json
{
  "id": "hello-world",
  "version": "1.0.0"
}
```

### Feature Module
```json
{
  "id": "products",
  "name": "Products",
  "description": "Product catalog and management",
  "version": "1.0.0",
  "author": "Mantra CMS",
  "type": "feature",
  "capabilities": ["routes", "content_type", "admin_ui", "settings"],
  "tags": ["ecommerce", "products", "catalog"]
}
```

### Integration Module
```json
{
  "id": "google-analytics",
  "name": "Google Analytics",
  "description": "Google Analytics integration",
  "version": "1.0.0",
  "author": "Mantra CMS",
  "license": "MIT",
  "homepage": "https://github.com/mantra-cms/google-analytics",
  "type": "integration",
  "capabilities": ["settings", "hooks"],
  "tags": ["analytics", "tracking", "google"]
}
```

### Core Module
```json
{
  "id": "admin",
  "name": "Admin Panel",
  "description": "Core admin panel functionality",
  "version": "1.0.0",
  "author": "Mantra CMS",
  "type": "core",
  "capabilities": ["routes", "admin_ui", "middleware"],
  "admin": {
    "disableable": false,
    "deletable": false
  }
}
```
