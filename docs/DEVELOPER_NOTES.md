# SakuraAlbum Developer Notes

SakuraAlbum is a Nextcloud app with app id `sakuraalbum` and PHP namespace `OCA\SakuraAlbum`.

## Architecture

- `SettingsService` owns admin/user settings normalization and compatibility defaults.
- `AlbumPlanService` scans user folders and builds deterministic preview or execution plans.
- `AlbumSyncService` applies guarded dry-run/write/chunk execution and stores run state.
- `AutoSyncService` records file changes, debounces work, and processes due background chunks.
- `ManagedAlbumDeletionService` handles guarded deletion for app-managed albums only.
- `DiagnosticReportService` prepares redacted reports for future support mail workflows.
- `PhotosAlbumAdapter` is the only integration point that touches the local Photos album implementation.

Generated albums are tracked in SakuraAlbum tables so future naming-syntax changes can be handled through stored schema versions and config hashes instead of relying on album names alone.

## Photos integration

Nextcloud exposes WebDAV operations for clients, but SakuraAlbum currently runs server-side and isolates Photos-specific behavior in `PhotosAlbumAdapter`. This keeps the non-OCP integration surface small and replaceable if a stable public server-side Photos album API becomes available.

Before a public app-store submission, re-review this adapter against the then-current Nextcloud and Photos APIs.

## Background sync

File events must never scan folders or write Photos albums directly. They only queue dirty source roots. `AutoSyncJob` is non-parallel and delegates to `AutoSyncService::processDueChanges()`, which respects admin limits and user opt-in before calling chunked writes.

Large first-generation runs use `sakuraalbum_sync_cursors`. Partial chunks do not run stale-file removal or missing-managed-album cleanup because a partial plan is not the full desired state.

## Local commands

```bash
./scripts/self-check.sh
./scripts/build-artifact.sh
```

The self-check is intentionally conservative: it checks syntax, metadata, UI labels/routes, safety guard strings, cache-busting asset names, and accidental secret patterns.
