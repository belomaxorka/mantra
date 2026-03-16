# Translation System Refactor - Requirements

## Problem Statement

The current translation system has inconsistencies between module IDs and translation key namespaces:

**Current Issues:**
1. Module ID: `admin-dashboard` but translation keys use `admin.dashboard.*`
2. Module ID: `admin-pages` but translation keys use `admin.pages.*`
3. Module ID: `admin-posts` but translation keys use `admin.posts.*`
4. Module ID: `admin-settings` but translation keys use `admin.settings.*`

The `Language` class extracts the domain from the first part of the key (before the first dot), so:
- `admin.dashboard.title` → looks for module `admin` (✅ works because admin module has this key)
- `admin-dashboard.title` → looks for module `admin-dashboard` (❌ would work but keys don't use this format)

This creates confusion and makes the system harder to maintain and extend.

## User Stories

### 1. As a developer, I want a consistent translation key naming convention
**Acceptance Criteria:**
- Translation keys should clearly map to their source module
- The naming convention should be documented and easy to understand
- Keys should be predictable based on module structure

### 2. As a developer, I want to easily add translations to new modules
**Acceptance Criteria:**
- Clear guidelines on where to place translation files
- Obvious naming pattern for translation keys
- No confusion about which module owns which translations

### 3. As a developer, I want to share common translations across modules
**Acceptance Criteria:**
- Common translations (save, cancel, delete, etc.) should be reusable
- Shared translations should have a clear namespace
- No duplication of common strings

### 4. As a system architect, I want the translation system to support both simple and complex module structures
**Acceptance Criteria:**
- Support for single modules (e.g., `pages`)
- Support for module families (e.g., `admin-*` modules)
- Support for nested/hierarchical translations

## Current System Analysis

### How It Works Now:
```php
// Language.php extracts domain from key
private function domainFromKey($key) {
    $pos = strpos($key, '.');
    if ($pos === false) {
        return null;
    }
    return substr($key, 0, $pos); // Returns first part before dot
}

// Then loads from: modules/{domain}/lang/{locale}.php
```

### Current Module Structure:
```
modules/
  admin/                    → domain: "admin"
  admin-dashboard/          → domain: "admin-dashboard" (but keys use "admin")
  admin-pages/              → domain: "admin-pages" (but keys use "admin")
  admin-posts/              → domain: "admin-posts" (but keys use "admin")
  admin-settings/           → domain: "admin-settings" (but keys use "admin")
  pages/                    → domain: "pages"
  products/                 → domain: "products"
```

## Proposed Solutions

### Option 1: Align Keys with Module IDs (Breaking Change)
**Change translation keys to match module IDs:**
- `admin-dashboard.title` instead of `admin.dashboard.title`
- `admin-pages.title` instead of `admin.pages.title`
- `admin-posts.title` instead of `admin.posts.title`

**Pros:**
- Direct 1:1 mapping between module ID and translation domain
- No ambiguity
- Simpler to understand

**Cons:**
- Breaking change - all existing keys need updating
- Longer key names with hyphens
- All templates need updating

### Option 2: Support Module Aliases (Non-Breaking)
**Extend Language class to support module aliases:**
```php
// In module manifest or config
'translation_alias' => 'admin'

// admin-dashboard uses alias "admin" for translations
// Keys: admin.dashboard.* → loads from admin-dashboard/lang/
```

**Pros:**
- No breaking changes
- Maintains current key structure
- Flexible for different naming strategies

**Cons:**
- More complex system
- Potential for conflicts if multiple modules use same alias
- Less obvious which module owns which translations

### Option 3: Hierarchical Translation Loading (Recommended)
**Support both module-specific and shared translations:**

1. **Module-specific translations:**
   - Module: `admin-dashboard`
   - Keys: `admin-dashboard.title`, `admin-dashboard.welcome`
   - File: `modules/admin-dashboard/lang/en.php`

2. **Shared namespace translations:**
   - Shared namespace: `admin`
   - Keys: `admin.common.save`, `admin.sidebar.group.general`
   - File: `modules/admin/lang/en.php`
   - Used by all `admin-*` modules

3. **Lookup order:**
   ```
   Key: "admin.dashboard.title"
   1. Check modules/admin/lang/en.php for "admin.dashboard.title"
   2. Check modules/admin-dashboard/lang/en.php for "admin.dashboard.title"
   3. Return key if not found
   ```

**Pros:**
- Supports both shared and module-specific translations
- No breaking changes needed
- Clear separation of concerns
- Extensible for future modules

**Cons:**
- Slightly more complex lookup logic
- Need clear documentation on when to use which approach

### Option 4: Namespace Mapping Configuration
**Add explicit namespace-to-module mapping:**
```php
// In config or module manifest
'translation_namespaces' => array(
    'admin.dashboard' => 'admin-dashboard',
    'admin.pages' => 'admin-pages',
    'admin.posts' => 'admin-posts',
    'admin.settings' => 'admin-settings',
)
```

**Pros:**
- Explicit and clear
- Flexible
- No breaking changes

**Cons:**
- Requires configuration maintenance
- Another layer of indirection
- Could become complex with many modules

## Recommended Approach

**Hybrid: Option 3 (Hierarchical) + Clear Conventions**

### Convention Rules:

1. **Module-specific translations:**
   - Use full module ID as namespace
   - Example: `admin-dashboard.title` for admin-dashboard module
   - File: `modules/admin-dashboard/lang/en.php`

2. **Shared translations within a family:**
   - Use parent namespace for shared translations
   - Example: `admin.common.save` shared by all admin-* modules
   - File: `modules/admin/lang/en.php`

3. **Lookup strategy:**
   - For key `admin.dashboard.title`:
     1. Try `modules/admin/lang/en.php` (parent namespace)
     2. Try `modules/admin-dashboard/lang/en.php` (if exists)
   - For key `admin-dashboard.title`:
     1. Try `modules/admin-dashboard/lang/en.php` (exact match)

### Migration Path:

**Phase 1: Add support for hierarchical loading (non-breaking)**
- Extend Language class to check multiple locations
- Keep existing keys working

**Phase 2: Migrate to new convention (gradual)**
- Move module-specific translations to use full module IDs
- Keep shared translations in parent namespace
- Update templates gradually

**Phase 3: Deprecate old patterns**
- Add warnings for old-style keys
- Provide migration guide

## Success Criteria

1. ✅ All admin panel translations work correctly
2. ✅ Clear documentation on translation key conventions
3. ✅ Easy to add new modules with translations
4. ✅ No duplication of common translations
5. ✅ Backward compatible with existing keys
6. ✅ Performance: no significant overhead in translation lookup

## Out of Scope

- Translation management UI
- Automatic translation extraction
- Translation file validation
- Pluralization support
- Date/time localization

## Technical Constraints

- Must work with existing PHP 7.4+ codebase
- Must not break existing translations
- Must maintain performance (no database queries)
- Must support lazy loading of translation files

## Dependencies

- Core Language class
- Module system
- Configuration system

## Risks

1. **Breaking changes:** Changing key format could break existing code
   - Mitigation: Support both old and new formats during transition

2. **Performance:** Multiple file lookups could slow down translation
   - Mitigation: Cache loaded translations, lazy load only when needed

3. **Confusion:** Multiple ways to structure keys could confuse developers
   - Mitigation: Clear documentation and examples

## Questions to Resolve

1. Should we support both `admin.dashboard.title` and `admin-dashboard.title`?
2. How do we handle conflicts if both exist?
3. Should we add a translation registry/cache?
4. Do we need a migration tool for existing translations?
5. Should module manifests declare their translation namespaces?

## Next Steps

1. Review and approve requirements
2. Create design document with technical implementation details
3. Create migration plan for existing translations
4. Implement changes in Language class
5. Update all admin module translations
6. Update documentation
7. Test thoroughly
