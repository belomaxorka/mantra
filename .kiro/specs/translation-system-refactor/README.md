# Translation System Refactor

## Quick Summary

Refactor the translation system to support hierarchical loading, allowing both shared and module-specific translations with clear ownership and organization.

## Problem

Module IDs don't match translation key namespaces:
- Module: `admin-dashboard` → Keys: `admin.dashboard.*` ❌
- Module: `admin-pages` → Keys: `admin.pages.*` ❌
- Module: `admin-posts` → Keys: `admin.posts.*` ❌

This causes confusion about translation ownership and makes the system hard to maintain.

## Solution

Implement hierarchical translation loading:
1. **Shared translations** → `modules/admin/lang/` (admin.common.*, admin.sidebar.group.*)
2. **Module-specific** → `modules/admin-dashboard/lang/` (admin-dashboard.*)
3. **Backward compatible** → Old keys still work during migration

## Files

- `requirements.md` - Detailed requirements and user stories
- `design.md` - Technical implementation details
- `tasks.md` - Step-by-step implementation tasks
- `analysis.md` - Current system analysis

## Implementation Phases

1. **Phase 1:** Core implementation (Language class + new translation files)
2. **Phase 2:** Template updates (optional, backward compatible)
3. **Phase 3:** Testing & validation
4. **Phase 4:** Documentation updates
5. **Phase 5:** Cleanup & optimization

## Getting Started

1. Read `requirements.md` to understand the problem
2. Review `design.md` for technical details
3. Open `tasks.md` and start with Phase 1

## Key Benefits

✅ Clear translation ownership  
✅ Better modularity  
✅ Shared translations for common strings  
✅ Backward compatible  
✅ No breaking changes  
✅ Gradual migration path  

## Status

🟡 **Ready for Implementation** - Design approved, tasks defined
