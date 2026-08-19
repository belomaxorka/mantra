# Storage integrity

Mantra keeps its production runtime free of third-party PHP packages. Persistence is implemented by the core classes and follows one rule: each kind of data has one write authority.

| Data | Authority | Location |
|---|---|---|
| Global configuration | `ConfigRepository` | `content/settings/config.json` |
| Module configuration | `Module\ModuleSettings` | `content/settings/<module>.json` |
| Content documents | `Database` plus a storage driver | `content/<collection>/` |
| Document history | `Storage\RevisionStore` | `storage/revisions/` |
| Multi-file recovery | `Storage\FileTransaction` | `storage/transactions/` |
| Recoverable removals | `Storage\TrashManager` | `storage/trash/` |

## Settings repositories

`ConfigRepository` and `ModuleSettings` share `SettingsRepository`. Mutations are explicit and batched in memory:

```php
$settings = config();
$settings->set('site.name', 'Example');
$settings->set('content.posts_per_page', 20);
$settings->save();
```

The repository stores an SHA-256 fingerprint of the source document. `save()` compares it with the current file while holding the file lock. A stale repository throws `ConcurrentSettingsModificationException` instead of overwriting another request. Reload the repository and repeat the logical change after resolving the conflict.

Unreadable JSON and settings written by a newer schema remain read-only. Defaults may still be used for the current request, but automatic saving is refused so the damaged or newer source is preserved.

## Schemas and migrations

`SchemaMigrator` is used for both settings and content documents. Migrations are keyed by the version they produce:

```php
return [
    'version' => 3,
    'migrations' => [
        2 => fn(array $data, int $from, int $to): array => $data,
        3 => fn(array $data, int $from, int $to): array => $data,
    ],
];
```

Callbacks run sequentially. Each callback must return the complete array. Invalid results abort the operation, and a document whose version is newer than the runtime is never downgraded. The legacy `migrate` callback remains available for third-party modules, but new schemas should use `migrations`.

Collection schemas can declare uniqueness:

```php
'unique' => [
    'slug',
    'email' => ['case_insensitive' => true],
],
```

`Database` verifies these constraints under a collection-level lock. Controller pre-checks can improve form feedback, but the database check is the authoritative race-safe invariant.

## Atomicity and recovery

`FileIO` is the low-level boundary for persisted application files. It provides locked reads, atomic replacement, compare/update callbacks, path containment checks, and locked deletion. Atomic replacement never removes the last known-good target before the replacement is ready.

`FileTransaction` handles small operations that must update or delete several files together. It writes backups and a prepared journal before the first mutation. A normal failure rolls back immediately; journals left by a terminated PHP process are recovered at the beginning of `Application::run()`.

When a content document has related files, use `Database::deleteWithRelatedFiles()`. This keeps schema validation, revision capture, cache invalidation, and collection locking inside the database boundary while the underlying files are committed through one journal.

Use `FileTransaction` only for project files. It deliberately rejects unresolved paths and paths outside `MANTRA_ROOT`.

## Revisions and trash

Before an existing content document is updated, deleted, or rewritten by a lazy migration, `Database` captures its previous value. The number of snapshots per document is controlled by `content.revision_limit`; `0` disables revisions.

```php
$history = app()->db()->revisions('posts', $postId);
app()->db()->restoreRevision('posts', $postId, $history[0]['revision_id']);
```

Restoration uses the normal database write path, so validation, uniqueness, timestamps, and revision capture still apply.

Destructive module removal uses `TrashManager`, which moves module files and settings into `storage/trash/`. This is intentionally separate from transaction journals: trash is retained for operator recovery, while completed transaction journals are removed.

Backups should include `content/`, `uploads/`, and `storage/revisions/`. Include `storage/trash/` when pending manual recovery matters. `storage/transactions/` is transient and should be allowed to recover before a snapshot is taken.
