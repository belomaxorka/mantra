# Analytics Module

Flexible analytics and tracking module for Mantra CMS.

## Features

- **Google Analytics**: Full Google Analytics 4 (GA4) and Universal Analytics support
- **Yandex Metrika**: Russian analytics service integration
- **Custom Code**: Add any custom tracking scripts
- **Automatic Loading**: Scripts loaded automatically in footer
- **Configurable**: All settings managed through admin panel

## Settings

The module provides a settings page accessible from the admin panel:

### Analytics Services
- **Google Analytics ID**: Your GA tracking ID (G-XXXXXXXXXX or UA-XXXXXXXXX-X)
- **Yandex Metrika ID**: Your Yandex counter ID (numeric)

### Custom Code
- **Custom Tracking Code**: Add any custom analytics or tracking scripts

## Usage

### Enable the Module

Add to `content/settings/config.json`:

```json
{
  "modules": {
    "enabled": ["admin", "analytics"]
  }
}
```

### Configure Settings

1. Go to Admin Panel → Settings
2. Find "Analytics" module in the modules list
3. Click "Settings" to configure
4. Enter your tracking IDs
5. Save settings

### Supported Services

#### Google Analytics

Supports both:
- **GA4** (Google Analytics 4): `G-XXXXXXXXXX`
- **Universal Analytics**: `UA-XXXXXXXXX-X`

The module automatically loads the gtag.js script and initializes tracking.

#### Yandex Metrika

Enter your numeric counter ID (e.g., `12345678`). The module loads the full Metrika script with:
- Click map tracking
- Link tracking
- Accurate bounce tracking

#### Custom Code

Add any custom tracking scripts in the "Custom Code" field. Common uses:
- Facebook Pixel
- Hotjar
- Custom event tracking
- A/B testing tools

## How It Works

The module hooks into `theme.footer` and injects tracking scripts before the closing `</body>` tag.

Scripts are only loaded if their corresponding IDs are configured, so there's no performance impact from unused services.

## Settings File

Settings are stored in `content/settings/analytics.json` and managed through the schema defined in `settings.schema.php`.

## Example Configuration

```json
{
  "google_analytics_id": "G-ABC123DEF4",
  "yandex_metrika_id": "12345678",
  "custom_code": "<script>console.log('Custom tracking loaded');</script>"
}
```

## Privacy Considerations

When using analytics services:
- Ensure compliance with GDPR, CCPA, and other privacy regulations
- Add cookie consent banners if required in your jurisdiction
- Update your privacy policy to disclose tracking
- Consider IP anonymization features

## Extending

You can add support for additional analytics services by:

1. Adding new fields to `settings.schema.php`
2. Creating getter methods in `AnalyticsModule.php`
3. Hooking into `theme.footer` or `theme.head` as needed

Example:

```php
// In AnalyticsModule.php
private function getCustomServiceScript($id) {
    return '<script>/* your service code */</script>';
}
```
