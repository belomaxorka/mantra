# Mantra CMS

Flat-File Content Management System with modular architecture.

## Features

- **Flat-File Storage**: No database required, all content stored in JSON files
- **Modular System**: Extensible architecture with plugin support
- **PHP 5.6+ Compatible**: Works with PHP 5.6 through PHP 8.x
- **Theme Support**: Easy theme customization
- **Multi-language**: Built-in internationalization
- **User Management**: Role-based access control
- **Caching**: File-based caching for performance
- **Hook System**: Event-driven architecture for extensibility

## Requirements

- PHP 5.6 or higher
- Apache with mod_rewrite (or Nginx with proper configuration)
- Write permissions for content, storage, and uploads directories

## Installation

1. Clone or download this repository
2. Set write permissions:
   ```bash
   chmod -R 755 content storage uploads
   ```
3. Configure your web server to point to the project root
4. Copy `config.php` and adjust settings
5. Access `/admin` to set up your first user

## Directory Structure

```
/mantra
  /core           - Core system files
  /modules        - Modules (plugins)
  /themes         - Themes
  /content        - Content storage (pages, posts, users)
  /storage        - Cache and logs
  /uploads        - User uploaded files
  index.php       - Entry point
  config.php      - Configuration
```

## Creating a Module

1. Create a folder in `/modules/your-module`
2. Create `module.json`:
```json
{
    "name": "your-module",
    "version": "1.0.0",
    "description": "Your module description",
    "dependencies": []
}
```

3. Create `YourModuleModule.php`:
```php
<?php
class YourModuleModule extends Module {
    public function init() {
        // Register hooks
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/your-route', array($this, 'yourMethod'));
        return $data;
    }
    
    public function yourMethod() {
        echo 'Hello from your module!';
    }
}
```

4. Enable module in `config.php`

## Available Hooks

- `system.init` - Fired when system initializes
- `system.shutdown` - Fired before shutdown
- `routes.register` - Register custom routes
- `view.render` - Modify rendered content
- `content.save` - Before content is saved
- `content.delete` - Before content is deleted

## License

MIT License
