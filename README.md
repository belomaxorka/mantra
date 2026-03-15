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
4. Configure the CMS via `content/settings/config.json`
5. Access `/admin` to set up your first user

### Proxy / CDN (real client IP)

If you run Mantra behind a reverse proxy or CDN (Nginx, Cloudflare, Fastly, etc.), the web server will often pass the *proxy IP* as `REMOTE_ADDR`.

Mantra provides the `client_ip()` helper (see `core/helpers.php`) to obtain the real client IP. For security, Mantra only trusts proxy headers (like `X-Forwarded-For`) when the request comes from a **trusted proxy**.

Configure trusted proxies in `content/settings/config.json`:

```json
{
  "trusted_proxies": ["127.0.0.1", "::1", "10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]
}
```

- Entries can be IPs or CIDRs (IPv4/IPv6).
- If `trusted_proxies` is empty, `client_ip()` will fall back to `REMOTE_ADDR`.
- For Cloudflare/Fastly, add their published IP ranges (see your provider documentation).

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
  content/settings/config.json - Configuration
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

4. Enable the module in `content/settings/config.json` under `enabled_modules`

## Available Hooks

- `system.init` - Fired when system initializes
- `system.shutdown` - Fired before shutdown
- `routes.register` - Register custom routes
- `view.render` - Modify rendered content
- `content.save` - Before content is saved
- `content.delete` - Before content is deleted

## Logging

The system includes a centralized logging system with PSR-3 compatible log levels:

```php
// Using logger helper
logger()->info('User action', array('user_id' => 123));
logger()->error('Something went wrong', array('exception' => $e));
logger()->debug('Debug information');

// Using specific channel
logger('security')->warning('Suspicious activity detected');

// Quick helpers
log_debug('This only logs in debug mode');
log_message('error', 'Error message', array('context' => 'data'));
```

Log levels (from highest to lowest priority):
- `emergency` - System is unusable
- `alert` - Action must be taken immediately
- `critical` - Critical conditions
- `error` - Runtime errors
- `warning` - Warning messages
- `notice` - Normal but significant events
- `info` - Informational messages
- `debug` - Detailed debug information (only in debug mode)

Logs are stored in `storage/logs/` with daily rotation:
- Error logs: `error-YYYY-MM-DD.log`
- Channel logs: `{channel}-YYYY-MM-DD.log`

Clean old logs:
```php
logger()->clearOldLogs(30); // Delete logs older than 30 days
```

## License

MIT License
