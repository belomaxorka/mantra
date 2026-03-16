# Core Structure

## Overview

The core directory contains all system classes organized in a logical structure.

## Directory Structure

```
core/
├── classes/              # All system classes
│   ├── Http/            # HTTP-related classes
│   │   ├── Cookie.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Session.php
│   │
│   ├── Module/          # Module system classes
│   │   ├── ModuleInterface.php      # Module interface
│   │   ├── Module.php               # Base module class
│   │   ├── BaseAdminModule.php      # Base class for admin modules
│   │   ├── ContentAdminModule.php   # Base class for content modules
│   │   ├── ModuleManager.php        # Module lifecycle manager
│   │   ├── ModuleSettings.php       # Module settings handler
│   │   ├── ModuleValidator.php      # Module validation
│   │   ├── ModuleType.php           # Module type constants
│   │   └── ModuleCapability.php     # Module capability constants
│   │
│   ├── Psr/             # PSR standards implementation
│   │   └── Log/
│   │       ├── LoggerInterface.php
│   │       └── LogLevel.php
│   │
│   ├── Application.php           # Main application class
│   ├── Auth.php                  # Authentication
│   ├── Cache.php                 # Caching system
│   ├── Config.php                # Configuration management
│   ├── ConfigSettings.php        # Config settings handler
│   ├── ContentTypeRegistry.php   # Content type registry
│   ├── Database.php              # Database abstraction
│   ├── ErrorHandler.php          # Error handling
│   ├── HookManager.php           # Hook/event system
│   ├── JsonFile.php              # JSON file operations
│   ├── Language.php              # Language/translation
│   ├── Logger.php                # Logging system
│   ├── PageController.php        # Page controller
│   ├── Router.php                # URL routing
│   ├── TranslationManager.php    # Translation management
│   ├── User.php                  # User management
│   └── View.php                  # View/template rendering
│
├── schemas/             # Data schemas
│   ├── pages.php
│   ├── posts.php
│   └── users.php
│
├── settings/            # System settings schemas
│   └── config.settings.schema.php
│
├── bootstrap.php        # System initialization
└── helpers.php          # Helper functions

```

## Autoloading

The system uses a custom autoloader registered in `bootstrap.php`:

1. **PSR-3 interfaces** - Loaded manually before autoloader (in Psr/Log subfolder)
2. **Config classes** - Loaded manually before autoloader (needed for bootstrap)
3. **All other classes** - Loaded automatically via autoloader

### Autoloader Logic

The autoloader searches for classes in the following order:

1. `core/classes/{ClassName}.php` - Direct class files
2. `core/classes/{Namespace}/{ClassName}.php` - Namespaced classes (e.g., Http\Request)
3. `core/classes/Module/{ClassName}.php` - Module system classes

## Class Organization

### Module System (`classes/Module/`)

All module-related classes are grouped together:
- **ModuleInterface** - Contract for all modules
- **Module** - Base implementation
- **BaseAdminModule** - Base for admin panel modules
- **ContentAdminModule** - Base for content management modules
- **ModuleManager** - Handles module lifecycle
- **ModuleSettings** - Module settings management
- **ModuleValidator** - Module validation
- **ModuleType** - Type constants (CORE, ADMIN, CONTENT, CUSTOM)
- **ModuleCapability** - Capability constants

### HTTP Classes (`classes/Http/`)

HTTP-related functionality:
- **Request** - HTTP request handling
- **Response** - HTTP response handling
- **Session** - Session management
- **Cookie** - Cookie management

### PSR Standards (`classes/Psr/`)

PSR-3 logging interface implementation (no Composer dependency).

### Core Classes (`classes/`)

Main system classes at the root of `classes/` directory.

## Benefits of This Structure

1. **Clear organization** - Related classes are grouped together
2. **Easy navigation** - Find classes by category
3. **Scalability** - Easy to add new class categories
4. **Clean autoloading** - Logical class resolution
5. **Separation of concerns** - Each directory has a specific purpose

## Migration Notes

If you're updating from the old structure:
- All classes moved from `core/*.php` to `core/classes/*.php`
- Module classes moved to `core/classes/Module/`
- HTTP classes moved to `core/classes/Http/`
- PSR classes moved to `core/classes/Psr/`
- `AdminModule.php` renamed to `BaseAdminModule.php` to avoid conflicts
- Autoloader updated to handle new paths
- No changes needed in module code - autoloader handles everything
