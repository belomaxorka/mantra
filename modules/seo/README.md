# SEO Module

Comprehensive SEO optimization module for Mantra CMS.

## Features

- **Meta Tags**: Automatic meta description, keywords, and robots tags
- **Open Graph**: Full Open Graph protocol support for social sharing
- **Twitter Cards**: Twitter Card meta tags with configurable card type
- **Breadcrumbs**: Automatic breadcrumb navigation with widget support
- **Canonical URLs**: Automatic canonical link tags
- **Configurable**: All settings managed through admin panel

## Settings

The module provides a comprehensive settings page accessible from the admin panel:

### General SEO
- Default meta description
- Default keywords

### Open Graph
- Default OG image URL
- Site name override

### Twitter Card
- Card type (summary, summary_large_image, app, player)
- Site handle (@yoursite)
- Creator handle (@author)

### Advanced
- Default robots meta tag
- Enable/disable breadcrumbs
- Customize breadcrumb home text

## Usage

### Enable the Module

Add to `content/settings/config.json`:

```json
{
  "modules": {
    "enabled": ["admin", "seo"]
  }
}
```

### Configure Settings

1. Go to Admin Panel → Settings
2. Find "SEO" module in the modules list
3. Click "Settings" to configure

### Use Breadcrumbs Widget

In your theme templates:

```php
<?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
    <?php echo widget('seo:breadcrumbs', array('breadcrumbs' => $breadcrumbs)); ?>
<?php endif; ?>
```

The breadcrumbs data is automatically added to page/post/product views by the module.

## Hooks

The module hooks into:

- `theme.head` - Adds meta tags to HTML head
- `page.single.data` - Adds breadcrumbs to page data
- `post.single.data` - Adds breadcrumbs to post data
- `product.single.data` - Adds breadcrumbs to product data
- `widget.render` - Provides breadcrumbs widget

## Settings File

Settings are stored in `content/settings/seo.json` and managed through the schema defined in `settings.schema.php`.

## Customization

### Override Meta Tags

You can hook into the data before meta tags are generated:

```php
// In your custom module
$this->hook('page.single.data', function($data) {
    // Add custom meta description
    $data['meta_description'] = 'Custom description';
    return $data;
});
```

### Custom Breadcrumb Structure

The breadcrumbs array structure:

```php
array(
    array('title' => 'Home', 'url' => '/'),
    array('title' => 'Category', 'url' => '/category'),
    array('title' => 'Current Page', 'url' => null) // null = active item
)
```
