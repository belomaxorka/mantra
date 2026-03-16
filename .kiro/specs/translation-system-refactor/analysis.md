# Current Translation System Analysis

## Problem Summary

The translation system has a **namespace mismatch** between module IDs and translation keys.

### The Core Issue

```php
// Language.php logic:
private function domainFromKey($key) {
    return substr($key, 0, strpos($key, '.')); // Extract first part
}

// For key "admin.dashboard.title":
// → domain = "admin"
// → loads from: modules/admin/lang/en.php

// For key "admin-dashboard.title":
// → domain = "admin-dashboard"  
// → loads from: modules/admin-dashboard/lang/en.php
```

### Current Module Structure vs Translation Keys

| Module ID | Translation Keys Used | Where Keys Are Stored | Status |
|-----------|----------------------|----------------------|---------|
| `admin` | `admin.login.*`, `admin.layout.*`, `admin.common.*` | `modules/admin/lang/` | ✅ Works |
| `admin-dashboard` | `admin.dashboard.*` | `modules/admin/lang/` | ⚠️ Confusing |
| `admin-pages` | `admin.pages.*` | `modules/admin/lang/` | ⚠️ Confusing |
| `admin-posts` | `admin.posts.*` | `modules/admin/lang/` | ⚠️ Confusing |
| `admin-settings` | `admin.settings.*` | `modules/admin/lang/` | ⚠️ Confusing |

### Why This Happens

The admin-* modules were designed as a **family of related modules** sharing a common namespace (`admin`), but the translation system treats the first part of the key as the **module directory name**.

### Current Workaround

All admin-* module translations are stored in `modules/admin/lang/` files, which works but creates issues:

1. **Ownership confusion:** Who owns `admin.dashboard.*` keys?
2. **Scalability:** The admin module's translation files become bloated
3. **Modularity:** Can't distribute admin-dashboard independently
4. **Maintenance:** Hard to find which translations belong to which module

## What Works Now

✅ Keys in `modules/admin/lang/en.php`:
```php
'admin.login.title' => 'Sign In',
'admin.layout.logout' => 'Logout',
'admin.common.save' => 'Save',
'admin.dashboard.title' => 'Dashboard',  // Works but belongs to admin-dashboard
'admin.pages.title' => 'Pages',          // Works but belongs to admin-pages
```

## What Doesn't Work

❌ Keys in `modules/admin-dashboard/lang/en.php`:
```php
'admin.dashboard.title' => 'Dashboard',  // Won't be found!
// System looks in modules/admin/lang/ not modules/admin-dashboard/lang/
```

❌ Keys in `modules/admin-dashboard/lang/en.php`:
```php
'admin-dashboard.title' => 'Dashboard',  // Would work but not used in templates
// Templates use admin.dashboard.title not admin-dashboard.title
```

## Translation Key Usage in Code

### In Module Registration (PHP):
```php
// admin-dashboard/AdminDashboardModule.php
$this->registerSidebarItem(array(
    'title' => array('key' => 'admin.dashboard.title', 'fallback' => 'Dashboard'),
    'group' => array('key' => 'admin.sidebar.group.general', 'fallback' => 'General'),
));
```

### In Templates (PHP):
```php
// admin-dashboard/views/dashboard.php
<h1><?php echo t('admin.dashboard.title'); ?></h1>
<p><?php echo t('admin.dashboard.welcome_message'); ?></p>
```

## Proposed Solution Options

### Option A: Centralized Approach (Current)
**Keep all admin-* translations in modules/admin/lang/**

Pros:
- Already working
- Single source of truth
- Easy to find all admin translations

Cons:
- Poor modularity
- Large translation files
- Unclear ownership

### Option B: Distributed Approach (Recommended)
**Each module has its own translations with proper namespacing**

Structure:
```
modules/admin/lang/en.php:
  - admin.login.*
  - admin.layout.*
  - admin.common.*
  - admin.sidebar.group.*

modules/admin-dashboard/lang/en.php:
  - admin-dashboard.title
  - admin-dashboard.welcome
  - admin-dashboard.quick_actions

modules/admin-pages/lang/en.php:
  - admin-pages.title
  - admin-pages.new
  - admin-pages.edit
```

Changes needed:
1. Update Language class to support module ID with hyphens
2. Update all translation keys in templates
3. Move translations to correct module directories

### Option C: Hybrid Approach (Most Flexible)
**Support both centralized shared translations and module-specific translations**

Language class checks multiple locations:
```php
// For key "admin.dashboard.title":
1. Check modules/admin/lang/en.php
2. Check modules/admin-dashboard/lang/en.php (if exists)
3. Return key if not found
```

This allows:
- Shared translations in `admin` module
- Module-specific translations in their own modules
- Gradual migration without breaking changes

## Recommendation

**Implement Option C (Hybrid Approach)** with clear conventions:

1. **Shared translations** → `modules/admin/lang/`
   - `admin.common.*` (save, cancel, delete, etc.)
   - `admin.sidebar.group.*` (General, Content, System)
   - `admin.layout.*` (logout, signed in as)

2. **Module-specific translations** → `modules/{module-id}/lang/`
   - `admin-dashboard.*` in `modules/admin-dashboard/lang/`
   - `admin-pages.*` in `modules/admin-pages/lang/`
   - `admin-posts.*` in `modules/admin-posts/lang/`

3. **Update Language class** to:
   - Try exact module match first
   - Fall back to parent namespace if hyphenated
   - Cache results for performance

This provides:
- ✅ Backward compatibility
- ✅ Clear ownership
- ✅ Modularity
- ✅ Shared translations
- ✅ Gradual migration path
