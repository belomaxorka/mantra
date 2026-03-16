# Admin Panel Localization

## Overview

The admin panel uses a namespace-based translation system where each module has its own language files.

## Translation Key Format

Translation keys follow the pattern: `module_id.category.key`

Examples:
- `admin.login.title` - Login page title in admin module
- `admin.pages.new` - "New Page" button in admin-pages module
- `admin.common.save` - Universal "Save" button in admin module

## Module Structure

Each admin module has a `lang/` directory containing language files:

```
modules/
  admin/
    lang/
      en.php
      ru.php
  admin-pages/
    lang/
      en.php
      ru.php
  admin-posts/
    lang/
      en.php
      ru.php
```

## Universal Translations

Common translations (save, cancel, delete, edit, etc.) are stored in the main `admin` module under the `admin.common.*` namespace:

- `admin.common.save` - Save
- `admin.common.cancel` - Cancel
- `admin.common.delete` - Delete
- `admin.common.edit` - Edit
- `admin.common.view` - View
- `admin.common.create` - Create
- `admin.common.update` - Update
- `admin.common.close` - Close
- `admin.common.back` - Back
- `admin.common.actions` - Actions
- `admin.common.status` - Status

## Usage in Templates

Use the `t()` helper function to translate keys:

```php
<?php echo t('admin.login.title'); ?>
<?php echo t('admin.pages.new'); ?>
<?php echo t('admin.common.save'); ?>
```

With parameters:

```php
<?php echo t('admin.welcome.message', ['name' => $username]); ?>
```

## Available Modules

### admin
Main admin panel module with login, layout, and common translations.

**Namespaces:**
- `admin.login.*` - Login page
- `admin.layout.*` - Admin layout
- `admin.common.*` - Universal actions and labels
- `admin.modules.*` - Module management
- `admin.settings.*` - Settings page

### admin-dashboard
Dashboard module.

**Namespaces:**
- `admin.dashboard.*` - Dashboard page

### admin-pages
Pages management module.

**Namespaces:**
- `admin.pages.*` - Pages list and edit forms

### admin-posts
Posts management module.

**Namespaces:**
- `admin.posts.*` - Posts list and edit forms

### admin-settings
Settings management module.

**Namespaces:**
- `admin.settings.*` - Settings pages and forms

## Supported Languages

- `en` - English
- `ru` - Russian (Русский)

## Adding New Translations

1. Create a new language file in the module's `lang/` directory:
   ```
   modules/your-module/lang/de.php
   ```

2. Return an array of translations:
   ```php
   <?php
   return array(
       'your-module.key' => 'Translation',
       'your-module.another.key' => 'Another translation',
   );
   ```

3. Use the translation in templates:
   ```php
   <?php echo t('your-module.key'); ?>
   ```

## Translation Guidelines

1. **Use descriptive keys**: `admin.pages.delete_confirm` instead of `admin.pages.dc`
2. **Group related translations**: Use dot notation to organize keys
3. **Reuse common translations**: Use `admin.common.*` for universal actions
4. **Provide context in help text**: Add `.help` suffix for field descriptions
5. **Keep translations consistent**: Use the same terminology across modules

## Examples

### Login Form
```php
<h1><?php echo t('admin.login.title'); ?></h1>
<label><?php echo t('admin.login.username'); ?></label>
<input type="text" name="username">
<button><?php echo t('admin.login.sign_in'); ?></button>
```

### Page List
```php
<h1><?php echo t('admin.pages.title'); ?></h1>
<a href="/admin/pages/new">
    <?php echo t('admin.pages.new'); ?>
</a>
<button onclick="deletePage()">
    <?php echo t('admin.common.delete'); ?>
</button>
```

### Settings Form
```php
<button type="submit">
    <?php echo t('admin.settings.save'); ?>
</button>
<a href="/admin">
    <?php echo t('admin.common.cancel'); ?>
</a>
```
