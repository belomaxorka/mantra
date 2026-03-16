# Localization System

## Overview

Mantra CMS uses a namespace-based translation system that integrates seamlessly with the module architecture.

## Key Concepts

### Translation Keys

Translation keys are namespaced by domain:

```
{domain}.{key}
```

**Examples:**
- `admin.title` - Admin module translation
- `pages.create` - Pages module translation
- `theme.header.welcome` - Theme translation
- `core.error.not_found` - Core system translation

### Domains

- **Module domain**: Module ID (e.g., `admin`, `pages`, `products`)
- **Theme domain**: `theme`
- **Core domain**: `core`

## Module Translations

### Structure

```
modules/
  my-module/
    lang/
      en.php
      ru.php
      de.php
```

### Translation File Format

```php
<?php
// modules/my-module/lang/en.php
return array(
    'my-module.title' => 'My Module',
    'my-module.description' => 'Module description',
    'my-module.button.save' => 'Save',
    'my-module.message.success' => 'Saved successfully!',
    'my-module.message.error' => 'Error: {error}',
);
```

### Using Translations in Modules

```php
class MyModuleModule extends Module {
    
    public function showPage() {
        $title = t('my-module.title');
        $message = t('my-module.message.success');
        
        // With parameters
        $error = t('my-module.message.error', array(
            'error' => 'File not found'
        ));
        
        $this->view('my-module:page', array(
            'title' => $title,
            'message' => $message,
        ));
    }
}
```

### In Templates

```php
<h1><?php echo t('my-module.title'); ?></h1>
<p><?php echo t('my-module.description'); ?></p>

<button><?php echo t('my-module.button.save'); ?></button>
```

## Automatic Discovery

The system automatically discovers module translations:

```php
// Get all available translations
$translations = translator()->discoverModuleTranslations();

foreach ($translations as $moduleId => $info) {
    echo "{$info['name']}: " . implode(', ', $info['locales']) . "\n";
}
```

## Configuration

Set locale in `content/settings/config.json`:

```json
{
  "locale": {
    "default_language": "en",
    "fallback_locale": "en",
    "available_languages": ["en", "ru", "de"]
  }
}
```

## Translation Manager API

### Basic Usage

```php
$translator = translator();

// Translate
$text = $translator->translate('admin.title');

// With parameters
$text = $translator->translate('admin.welcome', array(
    'name' => 'John'
));

// Check if translation exists
if ($translator->has('admin.title')) {
    // Translation exists
}

// Get all translations for a domain
$adminTranslations = $translator->getDomainTranslations('admin');
```

### Locale Management

```php
// Get current locale
$locale = $translator->getLocale();

// Set locale
$translator->setLocale('ru');

// Get fallback locale
$fallback = $translator->getFallbackLocale();
```

## Fallback Chain

1. Try key in current locale
2. Try key in fallback locale
3. Return key itself if not found

**Example:**
```php
// Current locale: ru
// Fallback locale: en

t('admin.title');
// 1. Look for 'admin.title' in modules/admin/lang/ru.php
// 2. Look for 'admin.title' in modules/admin/lang/en.php
// 3. Return 'admin.title' if not found
```

## Best Practices

### 1. Namespace All Keys

Always prefix keys with module ID:

```php
// Good
'admin.title'
'pages.create'
'products.list'

// Bad
'title'
'create'
'list'
```

### 2. Use Hierarchical Keys

Organize keys hierarchically:

```php
'admin.sidebar.dashboard'
'admin.sidebar.pages'
'admin.settings.general'
'admin.settings.advanced'
```

### 3. Declare Translation Capability

In `module.json`:

```json
{
  "capabilities": ["translations"]
}
```

### 4. Provide English as Default

Always provide `en.php` as the base translation:

```
modules/my-module/lang/
  en.php    ← Required
  ru.php    ← Optional
  de.php    ← Optional
```

### 5. Use Parameters for Dynamic Content

```php
// Translation file
'admin.user.welcome' => 'Welcome, {name}!',
'admin.items.count' => 'Found {count} items',

// Usage
t('admin.user.welcome', array('name' => $user['name']));
t('admin.items.count', array('count' => count($items)));
```

## Module Integration

### Declaring Translations

In `module.json`:

```json
{
  "id": "my-module",
  "name": "My Module",
  "capabilities": ["translations"]
}
```

### Checking Translation Support

```php
$module = module('my-module');

if ($module->hasTranslations()) {
    // Module has translations
    $langPath = $module->getPath() . '/lang';
}
```

### Translation Discovery

```php
// Discover all module translations
$moduleTranslations = translator()->discoverModuleTranslations();

// Result:
array(
    'admin' => array(
        'name' => 'Admin Panel',
        'locales' => array('en', 'ru'),
        'path' => '/path/to/modules/admin/lang',
    ),
    'pages' => array(
        'name' => 'Pages',
        'locales' => array('en'),
        'path' => '/path/to/modules/pages/lang',
    ),
)
```

## Admin Integration

### Settings UI

Modules with translations can be managed in admin:

```php
// In admin settings
$modules = translator()->discoverModuleTranslations();

foreach ($modules as $moduleId => $info) {
    echo "<h3>{$info['name']}</h3>";
    echo "Available locales: " . implode(', ', $info['locales']);
}
```

### Translation Editor

Create a translation editor in admin:

```php
public function editTranslations() {
    $moduleId = request()->get('module');
    $locale = request()->get('locale', 'en');
    
    $translations = translator()->getDomainTranslations($moduleId, $locale);
    
    // Show editor UI
    $this->view('admin:translations-editor', array(
        'module' => $moduleId,
        'locale' => $locale,
        'translations' => $translations,
    ));
}
```

## Examples

### Simple Module Translation

```php
// modules/hello/lang/en.php
return array(
    'hello.greeting' => 'Hello, World!',
    'hello.farewell' => 'Goodbye!',
);

// modules/hello/HelloModule.php
class HelloModule extends Module {
    public function greet() {
        echo t('hello.greeting');
    }
}
```

### Parameterized Translation

```php
// modules/users/lang/en.php
return array(
    'users.welcome' => 'Welcome, {name}!',
    'users.last_login' => 'Last login: {date} at {time}',
);

// Usage
echo t('users.welcome', array('name' => $user['name']));
echo t('users.last_login', array(
    'date' => date('Y-m-d'),
    'time' => date('H:i:s'),
));
```

### Multi-locale Module

```php
// modules/shop/lang/en.php
return array(
    'shop.cart.add' => 'Add to Cart',
    'shop.cart.total' => 'Total: ${amount}',
);

// modules/shop/lang/ru.php
return array(
    'shop.cart.add' => 'Добавить в корзину',
    'shop.cart.total' => 'Итого: ${amount}',
);

// Usage (automatically uses current locale)
echo t('shop.cart.add');
echo t('shop.cart.total', array('amount' => '99.99'));
```

## Migration from Old System

### Old System (Language.php)

```php
$lang = new Language();
$text = $lang->translate('admin.title');
```

### New System (TranslationManager)

```php
$text = t('admin.title');
// or
$text = translator()->translate('admin.title');
```

## Performance

### Lazy Loading

Translations are loaded only when needed:

```php
// No translations loaded yet
$translator = translator();

// Loads admin translations for current locale
t('admin.title');

// Loads pages translations for current locale
t('pages.create');
```

### Caching

Translation files are cached per domain and locale:

```php
// First call: loads from file
t('admin.title');

// Subsequent calls: uses cached data
t('admin.description');
t('admin.settings');
```

## Troubleshooting

### Translation Not Found

1. Check key namespace matches module ID
2. Verify translation file exists
3. Check file returns array
4. Ensure module has `translations` capability

### Wrong Locale

1. Check `config.json` locale settings
2. Verify translation file exists for locale
3. Check fallback locale is set

### Module Translations Not Loading

1. Ensure module is loaded
2. Check module has `lang/` directory
3. Verify file naming: `{locale}.php`
4. Check module declares `translations` capability

## Further Reading

- [Creating Modules](CREATING_MODULES.md)
- [Module Capabilities](MODULE_MANIFEST.md#capabilities)
- [Configuration](../content/settings/config.json)
