# Translation System Refactor - Design Document

## Overview

This document describes the technical implementation for refactoring the translation system to support both shared and module-specific translations with a clear, consistent naming convention.

## Design Goals

1. **Backward Compatibility:** Existing translation keys must continue to work
2. **Modularity:** Each module can have its own translations
3. **Shared Translations:** Common translations can be shared across modules
4. **Performance:** No significant performance degradation
5. **Clarity:** Clear ownership and organization of translations

## Proposed Solution: Hierarchical Translation Loading

### Key Concept

Support multiple translation file locations for a single namespace, with a clear lookup priority:

```
Key: "admin-dashboard.title"
1. Check: modules/admin-dashboard/lang/en.php
2. Fallback: Return key if not found

Key: "admin.dashboard.title" (legacy)
1. Check: modules/admin/lang/en.php
2. Check: modules/admin-dashboard/lang/en.php (new fallback)
3. Return key if not found
```

### Translation Key Conventions

#### 1. Module-Specific Keys (Recommended)
Use full module ID as namespace:
```php
// Module: admin-dashboard
// Keys: admin-dashboard.title, admin-dashboard.welcome
// File: modules/admin-dashboard/lang/en.php

return array(
    'admin-dashboard.title' => 'Dashboard',
    'admin-dashboard.welcome' => 'Welcome',
);
```

#### 2. Shared Keys (For Common Translations)
Use parent namespace for shared translations:
```php
// Module: admin (parent)
// Keys: admin.common.*, admin.sidebar.group.*
// File: modules/admin/lang/en.php

return array(
    'admin.common.save' => 'Save',
    'admin.common.cancel' => 'Cancel',
    'admin.sidebar.group.general' => 'General',
);
```

#### 3. Legacy Keys (Backward Compatible)
Old-style keys continue to work:
```php
// Key: admin.dashboard.title
// Looks in: modules/admin/lang/en.php first
// Then: modules/admin-dashboard/lang/en.php (new fallback)
```

## Technical Implementation

### 1. Extend Language Class

Modify `core/classes/Language.php` to support hierarchical loading:

```php
private function loadModule($module, $locale) {
    if (!isset($this->moduleTranslations[$module])) {
        $this->moduleTranslations[$module] = array();
    }
    if (isset($this->moduleTranslations[$module][$locale])) {
        return $this->moduleTranslations[$module][$locale];
    }

    // Primary: Load from exact module match
    $file = MANTRA_MODULES . '/' . $module . '/lang/' . $locale . '.php';
    $translations = $this->loadTranslationFile($file);
    
    // Secondary: For hyphenated modules, also check parent namespace
    // Example: "admin" key can load from "admin-dashboard" module
    if (strpos($module, '-') === false) {
        // This is a parent namespace (e.g., "admin")
        // Check all child modules (e.g., "admin-dashboard", "admin-pages")
        $childModules = $this->findChildModules($module);
        foreach ($childModules as $childModule) {
            $childFile = MANTRA_MODULES . '/' . $childModule . '/lang/' . $locale . '.php';
            $childTranslations = $this->loadTranslationFile($childFile);
            // Merge child translations (parent takes precedence)
            $translations = array_merge($childTranslations, $translations);
        }
    }
    
    $this->moduleTranslations[$module][$locale] = $translations;
    return $this->moduleTranslations[$module][$locale];
}

private function findChildModules($parentNamespace) {
    $children = array();
    $modulesDir = MANTRA_MODULES;
    
    if (!is_dir($modulesDir)) {
        return $children;
    }
    
    $pattern = $parentNamespace . '-*';
    foreach (glob($modulesDir . '/' . $pattern, GLOB_ONLYDIR) as $dir) {
        $children[] = basename($dir);
    }
    
    return $children;
}
```

### 2. Translation File Organization

#### Before (Current):
```
modules/
  admin/
    lang/
      en.php  (contains ALL admin-* translations)
      ru.php  (contains ALL admin-* translations)
  admin-dashboard/
    lang/  (empty or doesn't exist)
  admin-pages/
    lang/  (empty or doesn't exist)
```

#### After (New):
```
modules/
  admin/
    lang/
      en.php  (shared translations only)
        - admin.common.*
        - admin.sidebar.group.*
        - admin.layout.*
        - admin.login.*
      ru.php  (shared translations only)
  admin-dashboard/
    lang/
      en.php  (dashboard-specific translations)
        - admin-dashboard.title
        - admin-dashboard.welcome
        - admin-dashboard.quick_actions
      ru.php
  admin-pages/
    lang/
      en.php  (pages-specific translations)
        - admin-pages.title
        - admin-pages.new
        - admin-pages.edit
      ru.php
```

### 3. Migration Strategy

#### Phase 1: Add Hierarchical Support (Non-Breaking)
1. Update `Language::loadModule()` to check child modules
2. Keep all existing translations in place
3. Test that everything still works

#### Phase 2: Reorganize Translations (Gradual)
1. Create new translation files in child modules
2. Move module-specific translations from parent to child
3. Keep shared translations in parent
4. Update templates to use new key format (optional)

#### Phase 3: Update Templates (Optional)
1. Change `admin.dashboard.title` → `admin-dashboard.title`
2. Change `admin.pages.title` → `admin-pages.title`
3. This step is optional - old keys will still work

## Translation Key Mapping

### Shared Translations (Stay in admin module)

| Key | Module | Description |
|-----|--------|-------------|
| `admin.common.*` | admin | Universal actions (save, cancel, delete, etc.) |
| `admin.sidebar.group.*` | admin | Sidebar group labels |
| `admin.layout.*` | admin | Layout elements (logout, signed in as) |
| `admin.login.*` | admin | Login page |
| `admin.modules.*` | admin | Module management |

### Module-Specific Translations (Move to respective modules)

| Old Key | New Key | Module | File |
|---------|---------|--------|------|
| `admin.dashboard.*` | `admin-dashboard.*` | admin-dashboard | `modules/admin-dashboard/lang/` |
| `admin.pages.*` | `admin-pages.*` | admin-pages | `modules/admin-pages/lang/` |
| `admin.posts.*` | `admin-posts.*` | admin-posts | `modules/admin-posts/lang/` |
| `admin.settings.*` | `admin-settings.*` | admin-settings | `modules/admin-settings/lang/` |

## Performance Considerations

### Caching Strategy
```php
// Cache loaded translations in memory
private $moduleTranslations = array(); // [module => [locale => array]]

// Lazy loading - only load when needed
// Don't scan for child modules on every request
```

### Optimization
1. **Lazy Loading:** Only load translation files when keys are requested
2. **Memory Cache:** Cache loaded translations for the request lifetime
3. **Glob Cache:** Cache the list of child modules (could be stored in config)
4. **Minimal Overhead:** Only check child modules for parent namespaces

## Backward Compatibility

### Supported Key Formats

All of these will work:

```php
// Old format (still works)
t('admin.dashboard.title')  // Looks in admin/lang/, then admin-dashboard/lang/

// New format (recommended)
t('admin-dashboard.title')  // Looks in admin-dashboard/lang/

// Shared format
t('admin.common.save')      // Looks in admin/lang/
```

### No Breaking Changes

- Existing keys continue to work
- Existing translation files continue to work
- Templates don't need immediate updates
- Gradual migration is possible

## Testing Strategy

### Unit Tests
```php
// Test hierarchical loading
testLoadModuleWithHyphen()
testLoadParentNamespace()
testLoadChildModuleFallback()
testSharedTranslations()

// Test backward compatibility
testLegacyKeysStillWork()
testExistingTranslationsWork()
```

### Integration Tests
```php
// Test in actual admin pages
testDashboardTranslations()
testPagesTranslations()
testPostsTranslations()
testSharedTranslations()
```

### Manual Testing
1. Test all admin pages with Russian locale
2. Test all admin pages with English locale
3. Test fallback behavior
4. Test with missing translations

## Documentation Updates

### Files to Update
1. `docs/LOCALIZATION.md` - Add new conventions
2. `docs/LOCALIZATION_ADMIN.md` - Update admin-specific docs
3. `docs/TRANSLATION_KEYS_REFERENCE.md` - Update key reference
4. `README.md` - Add migration guide

### New Documentation
1. `docs/TRANSLATION_MIGRATION_GUIDE.md` - How to migrate existing translations
2. `docs/TRANSLATION_BEST_PRACTICES.md` - Best practices for new modules

## Implementation Checklist

- [ ] Update `Language::loadModule()` method
- [ ] Add `Language::findChildModules()` method
- [ ] Create translation files in child modules
- [ ] Move module-specific translations
- [ ] Update templates (optional)
- [ ] Update documentation
- [ ] Add tests
- [ ] Performance testing

## Rollback Plan

If issues arise:
1. Revert `Language.php` changes
2. Keep all translations in parent modules
3. No template changes needed if using old keys

## Future Enhancements

1. **Translation Registry:** Cache module-to-namespace mappings
2. **Validation Tool:** Check for missing translations
3. **Auto-discovery:** Automatically find all translation keys in templates
4. **IDE Support:** Generate translation key autocomplete

## Success Metrics

1. ✅ All existing translations work without changes
2. ✅ New modules can have their own translation files
3. ✅ Shared translations are clearly identified
4. ✅ No performance degradation (< 5ms overhead)
5. ✅ Clear documentation for developers
