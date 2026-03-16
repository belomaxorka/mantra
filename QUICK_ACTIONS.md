# Quick Actions Hook

Admin modules can register quick actions that appear on the dashboard using the `admin.quick_actions` hook.

## Usage

Register a quick action in your admin submodule's `init()` method:

```php
public function init($admin)
{
    app()->hooks()->register('admin.quick_actions', function ($actions) {
        if (!is_array($actions)) {
            $actions = array();
        }

        $actions[] = array(
            'id' => 'my-action',           // Unique identifier
            'title' => 'My Action',        // Display text
            'icon' => 'bi-icon-name',      // Bootstrap icon class (optional)
            'url' => base_url('/admin/my-page'),  // Target URL
            'order' => 20,                 // Sort order (lower = first, default: 100)
        );

        return $actions;
    });
}
```

## Fields

- **id** (string, required): Unique identifier for the action
- **title** (string, required): Display text shown on the button
- **icon** (string, optional): Bootstrap Icons class (e.g., `bi-gear`, `bi-plus-circle`)
- **url** (string, required): Target URL when the action is clicked
- **order** (int, optional): Sort order (default: 100, lower numbers appear first)

## Example

See `modules/admin-modules/settings/SettingsAdminModule.php` for a working example.
