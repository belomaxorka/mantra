# Module System - Quick Start

## Create Your First Module in 5 Minutes

### 1. Create Module Directory

```bash
mkdir -p modules/hello-world
```

### 2. Create module.json

```json
{
  "id": "hello-world",
  "name": "Hello World",
  "description": "My first module",
  "version": "1.0.0",
  "type": "feature",
  "capabilities": ["routes"]
}
```

### 3. Create Module Class

File: `modules/hello-world/HelloWorldModule.php`

```php
<?php

class HelloWorldModule extends Module {
    
    public function init() {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    public function registerRoutes($data) {
        $router = $data['router'];
        $router->get('/hello', array($this, 'hello'));
        return $data;
    }
    
    public function hello() {
        echo '<h1>Hello World!</h1>';
    }
}
```

### 4. Enable Module

Add to `content/settings/config.json`:

```json
{
  "modules": {
    "enabled": ["admin", "pages", "hello-world"]
  }
}
```

### 5. Test

Visit: `http://yoursite.com/hello`

## Common Patterns

### Add Settings

Create `settings.schema.php`:

```php
<?php
return array(
    'version' => 1,
    'tabs' => array(
        array(
            'id' => 'general',
            'title' => 'Settings',
            'fields' => array(
                array(
                    'path' => 'message',
                    'type' => 'text',
                    'title' => 'Message',
                    'default' => 'Hello!',
                ),
            ),
        ),
    ),
);
```

Use in module:

```php
public function hello() {
    $message = $this->settings()->get('message', 'Hello!');
    echo "<h1>{$message}</h1>";
}
```

### Add Admin Page

```php
public function init() {
    app()->hooks()->register('admin.sidebar', function ($items) {
        $items[] = array(
            'id' => 'hello-world',
            'title' => 'Hello World',
            'url' => base_url('/admin/hello'),
            'order' => 50,
        );
        return $items;
    });
    
    app()->hooks()->register('routes.register', function ($data) {
        $admin = app()->modules()->getModule('admin');
        if ($admin) {
            $admin->adminRoute('GET', 'hello', array($this, 'adminPage'));
        }
        return $data;
    });
}

public function adminPage() {
    $admin = app()->modules()->getModule('admin');
    return $admin->render('Hello World', '<h1>Admin Page</h1>');
}
```

### Add Custom Content Type

```php
public function init() {
    content_types()->register('event', array(
        'singular' => 'Event',
        'plural' => 'Events',
        'route_pattern' => '/event/{slug}',
        'collection' => 'events',
        'supports' => array('title', 'content', 'date', 'location'),
    ));
}
```

### Use Hooks

```php
public function init() {
    // Listen to hook
    $this->hook('content.save', array($this, 'onContentSave'));
}

public function onContentSave($data) {
    $this->log('info', 'Content saved', array('id' => $data['id']));
    return $data;
}
```

## Module Types

Choose the right type:

- `core` - System modules (cannot disable)
- `feature` - Add functionality
- `admin` - Admin extensions
- `integration` - External services
- `utility` - Helper modules

## Capabilities

Declare what your module does:

- `routes` - Adds routes
- `settings` - Has settings
- `admin_ui` - Admin interface
- `content_type` - Custom content
- `hooks` - Provides hooks
- `widgets` - Provides widgets
- `templates` - Provides templates
- `api` - API endpoints

## Validation

Check your module:

```php
$errors = ModuleValidator::validateStructure('hello-world');
if (empty($errors)) {
    echo "✓ Module is valid";
} else {
    print_r($errors);
}
```

## Next Steps

- [Full Module Guide](CREATING_MODULES.md)
- [Manifest Specification](MODULE_MANIFEST.md)
- [Example Module](../modules/example-module/)
