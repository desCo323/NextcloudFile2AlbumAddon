# SakuraAlbum

SakuraAlbum is a Nextcloud app for creating managed Photos albums from existing folder structures.

The current development version focuses on safe configuration, preview planning, guarded dry-run/write paths, and guarded deletion of generated albums. It must not be enabled against production Photos albums until a controlled backup test window is prepared.

## Current scope

- Admin settings for global enablement, default folders, scan limits, job limits, video policy, and automatic update load controls.
- Personal settings for opt-in, folders, exclusions, naming template, separator, depth, and media type.
- Personal automatic-update opt-in that is only active when admins allow file-event mode.
- Preview API that scans only the current user folder and returns planned albums without writing album data.
- Dry-run API that checks generated album names against real Photos albums without writing.
- Confirmed write API that can create/link albums only when admin and user settings are both enabled and the request matches a recent server-recorded dry-run fingerprint.
- Optional debounced file-event auto-sync: file create/write/delete/rename events queue dirty paths, and a non-parallel cron job processes due users within admin budgets.
- Admin Auto-Sync status view for queued, processing, failed, due-user, and next-due queue state.
- Admin load-profile buttons for conservative, balanced, and fast scan/job/event budgets.
- Recovery for stale auto-sync queue locks if a background run stops after reserving work.
- Reconciliation for SakuraAlbum-managed albums so removed or moved media can be removed from generated albums during a later sync.
- Optional cleanup for SakuraAlbum-managed albums that no longer appear in the current plan; this is disabled by default.
- Managed-album list and delete dry-run APIs for albums tracked in SakuraAlbum's own database.
- Confirmed delete API that can delete only Photos albums still matching a SakuraAlbum managed record and a recent server-recorded delete-preview fingerprint.
- App-owned tables for future tracking of generated albums and sync runs.
- App-owned log table for errors, successes, warnings, future cron/sync events, and optional debug context.
- Admin debug mode with stricter diagnostic logging and a log viewer.
- SVG branding with a sakura blossom falling onto a dog.

## Safety model

- Generated albums are intended to be tracked in `sakuraalbum_albums`.
- Bulk deletion must only operate on app-tracked generated albums.
- Deletion requires the exact confirmation text `DELETE_MANAGED_ALBUMS`.
- Deletion requires a matching plan fingerprint from a recent successful delete dry-run stored by the server.
- Deletion re-checks the Photos album id, owner, and current name immediately before deleting.
- Renamed or owner-mismatched Photos albums are blocked instead of deleted.
- Preview has strict folder/file limits from admin settings.
- Debug logs redact common secret keys before storing context.
- Debug exception traces intentionally omit function arguments.
- Write runs are blocked by default, require the exact confirmation text `CREATE_ALBUMS`, require a matching recent dry-run plan fingerprint stored by the server, and refuse unsafe plans.
- Existing Photos albums are not modified unless SakuraAlbum already tracks them as managed albums.
- Repeated updates are idempotent: an already-linked Photos file is counted as already linked, including Photos versions that report the duplicate through the database layer.
- File events never perform heavy scans or album writes directly. They only queue dirty paths for a later background job.
- File-event queue rows are collapsed to the affected include root so a large upload inside one selected folder does not create one independent sync job per file.
- File-event auto-sync requires three gates: global admin enablement, user enablement, and the user's explicit automatic-update opt-in.
- Background sync is non-parallel and bounded by admin settings for debounce, interval, users per run, runtime, event count, folder count, file count, and album count.
- Background sync summaries include seen events, reserved events, and event-limit hits so admins can tune load profiles from real diagnostics.
- Background sync recovers stale processing locks and logs runtime-limit stops so interrupted cron work can be diagnosed.
- Automatic updates mean "scheduled as soon as allowed by debounce and load limits", not synchronous writes during uploads or deletes.
- SakuraAlbum's global admin enable setting is stored under an app-owned key separate from Nextcloud's reserved app activation flag.

## Local checks

Run from the app directory:

```bash
./scripts/self-check.sh
```

The check runs PHP syntax checks, JavaScript syntax checks, XML metadata validation, pure naming smoke tests, and a basic secret-pattern scan.

Build a clean local package from the app directory:

```bash
./scripts/build-artifact.sh
```

The package is created under `../artifacts/` with a top-level `sakuraalbum/` folder and fails if development-only files such as `node_modules`, `vendor`, `.git`, caches, logs, or nested archives are included.

## Production test rule

Do not enable this app on a production Nextcloud before a backup and restore prompt have been prepared. Live tests may only use the Nextcloud user `albentest`.

During controlled tests, enable admin setting `debugMode` so preview requests, successes, warnings, and failures are stored with enough context for diagnosis.
