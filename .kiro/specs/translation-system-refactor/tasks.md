# Translation System Refactor - Tasks

## Phase 1: Core Implementation ✅ READY TO START

### 1.1 Extend Language Class
**Requirement:** Support hierarchical translation loading (Requirements 1, 2)

**Implementation:**
- [ ] 1.1.1 Add `findChildModules($parentNamespace)` method to Language class
  - Scan modules directory for pattern `{parent}-*`
  - Return array of child module names
  - Cache results for performance

- [ ] 1.1.2 Modify `loadModule($module, $locale)` method
  - Check if module has hyphen (is child module)
  - For parent namespaces, load from child modules too
  - Merge translations with parent taking precedence
  - Maintain backward compatibility

- [ ] 1.1.3 Add translation lookup priority logic
  - Primary: exact module match
  - Secondary: child modules (for parent namespace)
  - Tertiary: return key if not found

**Files to modify:**
- `core/classes/Language.php`

**Testing:**
- Verify existing keys still work
- Test new hierarchical loading
- Check performance impact

---

### 1.2 Create Module Translation Files
**Requirement:** Each module should have its own translations (Requirements 2, 3)

- [ ] 1.2.1 Create `modules/admin-dashboard/lang/en.php`
  - Add `admin-dashboard.*` keys
  - Include: title, welcome, welcome_message, quick_actions

- [ ] 1.2.2 Create `modules/admin-dashboard/lang/ru.php`
  - Translate all keys from en.php

- [ ] 1.2.3 Create `modules/admin-pages/lang/en.php`
  - Add `admin-pages.*` keys
  - Include all page management translations

- [ ] 1.2.4 Create `modules/admin-pages/lang/ru.php`
  - Translate all keys from en.php

- [ ] 1.2.5 Create `modules/admin-posts/lang/en.php`
  - Add `admin-posts.*` keys
  - Include all post management translations

- [ ] 1.2.6 Create `modules/admin-posts/lang/ru.php`
  - Translate all keys from en.php

- [ ] 1.2.7 Create `modules/admin-settings/lang/en.php`
  - Add `admin-settings.*` keys
  - Include all settings translations

- [ ] 1.2.8 Create `modules/admin-settings/lang/ru.php`
  - Translate all keys from en.php

**Files to create:**
- `modules/admin-dashboard/lang/en.php`
- `modules/admin-dashboard/lang/ru.php`
- `modules/admin-pages/lang/en.php`
- `modules/admin-pages/lang/ru.php`
- `modules/admin-posts/lang/en.php`
- `modules/admin-posts/lang/ru.php`
- `modules/admin-settings/lang/en.php`
- `modules/admin-settings/lang/ru.php`

---

### 1.3 Reorganize Admin Module Translations
**Requirement:** Keep only shared translations in admin module (Requirement 3)

- [ ] 1.3.1 Update `modules/admin/lang/en.php`
  - Keep: `admin.login.*`
  - Keep: `admin.layout.*`
  - Keep: `admin.common.*`
  - Keep: `admin.sidebar.group.*`
  - Keep: `admin.modules.*`
  - Remove: `admin.dashboard.*` (moved to admin-dashboard)
  - Remove: `admin.pages.*` (moved to admin-pages)
  - Remove: `admin.posts.*` (moved to admin-posts)
  - Remove: `admin.settings.*` (moved to admin-settings)

- [ ] 1.3.2 Update `modules/admin/lang/ru.php`
  - Same changes as en.php

**Files to modify:**
- `modules/admin/lang/en.php`
- `modules/admin/lang/ru.php`

---

## Phase 2: Template Updates (OPTIONAL - Backward Compatible)

### 2.1 Update Module Registration
**Requirement:** Use new key format in module registration (Requirement 1)

- [ ] 2.1.1 Update `modules/admin-dashboard/AdminDashboardModule.php`
  - Change `admin.dashboard.title` → `admin-dashboard.title`
  - Change `admin.dashboard.*` → `admin-dashboard.*`

- [ ] 2.1.2 Update `modules/admin-pages/AdminPagesModule.php`
  - Change `admin.pages.title` → `admin-pages.title`
  - Change `admin.pages.*` → `admin-pages.*`

- [ ] 2.1.3 Update `modules/admin-posts/AdminPostsModule.php`
  - Change `admin.posts.title` → `admin-posts.title`
  - Change `admin.posts.*` → `admin-posts.*`

- [ ] 2.1.4 Update `modules/admin-settings/AdminSettingsModule.php`
  - Change `admin.settings.title` → `admin-settings.title`
  - Change `admin.settings.*` → `admin-settings.*`

**Files to modify:**
- `modules/admin-dashboard/AdminDashboardModule.php`
- `modules/admin-pages/AdminPagesModule.php`
- `modules/admin-posts/AdminPostsModule.php`
- `modules/admin-settings/AdminSettingsModule.php`

---

### 2.2 Update View Templates
**Requirement:** Use new key format in templates (Requirement 1)

- [ ] 2.2.1 Update `modules/admin-dashboard/views/dashboard.php`
  - Change all `admin.dashboard.*` → `admin-dashboard.*`

- [ ] 2.2.2 Update `modules/admin-pages/views/list.php`
  - Change all `admin.pages.*` → `admin-pages.*`

- [ ] 2.2.3 Update `modules/admin-pages/views/edit.php`
  - Change all `admin.pages.*` → `admin-pages.*`

- [ ] 2.2.4 Update `modules/admin-posts/views/list.php`
  - Change all `admin.posts.*` → `admin-posts.*`

- [ ] 2.2.5 Update `modules/admin-posts/views/edit.php`
  - Change all `admin.posts.*` → `admin-posts.*`

- [ ] 2.2.6 Update `modules/admin-settings/views/*.php`
  - Change all `admin.settings.*` → `admin-settings.*`

**Files to modify:**
- `modules/admin-dashboard/views/dashboard.php`
- `modules/admin-pages/views/list.php`
- `modules/admin-pages/views/edit.php`
- `modules/admin-posts/views/list.php`
- `modules/admin-posts/views/edit.php`
- `modules/admin-settings/views/settings.php`
- `modules/admin-settings/views/settings-general.php`
- `modules/admin-settings/views/module-settings.php`

---

## Phase 3: Testing & Validation

### 3.1 Functional Testing
- [ ] 3.1.1 Test login page (Russian)
- [ ] 3.1.2 Test login page (English)
- [ ] 3.1.3 Test dashboard page (Russian)
- [ ] 3.1.4 Test dashboard page (English)
- [ ] 3.1.5 Test pages list (Russian)
- [ ] 3.1.6 Test pages list (English)
- [ ] 3.1.7 Test pages edit (Russian)
- [ ] 3.1.8 Test pages edit (English)
- [ ] 3.1.9 Test posts list (Russian)
- [ ] 3.1.10 Test posts list (English)
- [ ] 3.1.11 Test posts edit (Russian)
- [ ] 3.1.12 Test posts edit (English)
- [ ] 3.1.13 Test settings page (Russian)
- [ ] 3.1.14 Test settings page (English)

### 3.2 Backward Compatibility Testing
- [ ] 3.2.1 Verify old keys still work (`admin.dashboard.*`)
- [ ] 3.2.2 Verify new keys work (`admin-dashboard.*`)
- [ ] 3.2.3 Verify shared keys work (`admin.common.*`)
- [ ] 3.2.4 Verify sidebar groups work
- [ ] 3.2.5 Verify fallback to English works

### 3.3 Performance Testing
- [ ] 3.3.1 Measure translation lookup time (before)
- [ ] 3.3.2 Measure translation lookup time (after)
- [ ] 3.3.3 Verify < 5ms overhead
- [ ] 3.3.4 Check memory usage

---

## Phase 4: Documentation

### 4.1 Update Existing Documentation
- [ ] 4.1.1 Update `docs/LOCALIZATION.md`
  - Add hierarchical loading explanation
  - Add new key conventions
  - Add examples

- [ ] 4.1.2 Update `docs/LOCALIZATION_ADMIN.md`
  - Update module structure
  - Update key reference
  - Add migration notes

- [ ] 4.1.3 Update `docs/TRANSLATION_KEYS_REFERENCE.md`
  - Update all key references
  - Add new module-specific keys
  - Mark old keys as legacy

- [ ] 4.1.4 Update `docs/TRANSLATION_COVERAGE.md`
  - Update file locations
  - Update key counts
  - Update module breakdown

### 4.2 Create New Documentation
- [ ] 4.2.1 Create `docs/TRANSLATION_MIGRATION_GUIDE.md`
  - How to migrate from old to new format
  - Step-by-step instructions
  - Common pitfalls

- [ ] 4.2.2 Create `docs/TRANSLATION_BEST_PRACTICES.md`
  - When to use shared vs module-specific
  - Naming conventions
  - Organization tips

---

## Phase 5: Cleanup & Optimization

### 5.1 Remove Duplicates
- [ ] 5.1.1 Check for duplicate translations across modules
- [ ] 5.1.2 Consolidate duplicates into shared namespace
- [ ] 5.1.3 Remove unused translation keys

### 5.2 Optimize Performance
- [ ] 5.2.1 Add caching for child module discovery
- [ ] 5.2.2 Optimize file loading
- [ ] 5.2.3 Profile translation lookup performance

### 5.3 Final Validation
- [ ] 5.3.1 Run full test suite
- [ ] 5.3.2 Manual testing of all admin pages
- [ ] 5.3.3 Check for console errors
- [ ] 5.3.4 Verify all translations display correctly

---

## Notes

**Priority:** Phase 1 must be completed first. Phase 2 is optional but recommended.

**Backward Compatibility:** All existing keys will continue to work. No breaking changes.

**Testing:** Test after each phase before moving to the next.

**Rollback:** If issues arise, revert Language.php changes and keep translations in parent modules.
