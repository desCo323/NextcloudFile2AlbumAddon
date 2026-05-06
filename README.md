# SakuraAlbum

SakuraAlbum is a Nextcloud app for creating managed Photos albums from existing folder structures.

The current development version focuses on safe configuration, preview planning, guarded dry-run/write paths, guarded deletion of generated albums, automatic background updates, and store-ready release documentation. It must not be enabled against production Photos albums until a controlled backup test window is prepared.

## Current scope

- Admin settings for global enablement, group-limited rollout, default folders, scan limits, job limits, user quotas, video policy, and automatic update load controls.
- Personal settings for opt-in, selectable source folders, folder-specific rules, exclusions, naming template, separator, depth, and media type.
- Source folders can use global defaults, a folder-specific depth, or `Alles in ein Album` so a large subtree can be represented as one Photos album.
- Personal automatic-update opt-in that is only active when admins allow file-event mode.
- Personal status view with queue state, recent run details, resumable cursor details, expandable diagnostics, and a 0-100% progress bar based on running sync or chunk cursor state.
- Personal one-step automation action that enables SakuraAlbum plus automatic background generation when the administrator has allowed file-event mode.
- Resumable background generation for large first runs: automatic sync writes bounded chunks and continues from a stored cursor over later cron runs.
- Preview API that scans only the current user folder and returns planned albums without writing album data.
- Dry-run API that checks generated album names against real Photos albums without writing.
- Confirmed write API that can create/link albums only when admin and user settings are both enabled and the request matches a recent server-recorded dry-run fingerprint.
- Optional debounced file-event auto-sync: file create/write/delete/rename events queue dirty paths, and a non-parallel cron job processes due users within admin budgets.
- Admin Auto-Sync status view for queued, processing, failed, due-user, and next-due queue state.
- Admin-controlled "process due Auto-Sync now" action that respects the same saved user/runtime/event limits as the scheduled background job.
- Admin load-profile buttons for conservative, balanced, and fast scan/job/event budgets.
- Optional Auto-Sync maintenance windows so queued work only runs during configured low-load hours.
- Optional per-user quotas for SakuraAlbum-managed album count and managed media-link count.
- Recovery for stale auto-sync queue locks if a background run stops after reserving work.
- Reconciliation for SakuraAlbum-managed albums so removed or moved media can be removed from generated albums during a later sync.
- Optional cleanup for SakuraAlbum-managed albums that no longer appear in the current plan; this is disabled by default.
- Managed-album list and delete dry-run APIs for albums tracked in SakuraAlbum's own database.
- Confirmed delete API that can delete only Photos albums still matching a SakuraAlbum managed record and a recent server-recorded delete-preview fingerprint.
- App-owned tables for future tracking of generated albums and sync runs.
- App-owned log table for errors, successes, warnings, future cron/sync events, and optional debug context.
- Admin debug mode with stricter diagnostic logging and a log viewer.
- Redacted diagnostic report preparation for users and admins; direct email sending is intentionally left for a later release.
- User, admin, developer, privacy, and store-release documentation linked from `appinfo/info.xml`.
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
- Diagnostic reports reuse redacted app logs and explicitly mark mail sending as disabled until a future mail sender is added.
- Write runs are blocked by default, require the exact confirmation text `CREATE_ALBUMS`, require a matching recent dry-run plan fingerprint stored by the server, and refuse unsafe plans.
- Existing Photos albums are not modified unless SakuraAlbum already tracks them as managed albums.
- Repeated updates are idempotent: an already-linked Photos file is counted as already linked, including Photos versions that report the duplicate through the database layer.
- File events never perform heavy scans or album writes directly. They only queue dirty paths for a later background job.
- File-event queue rows are collapsed to the affected include root so a large upload inside one selected folder does not create one independent sync job per file.
- File-event auto-sync requires three gates: global admin enablement, user enablement, and the user's explicit automatic-update opt-in.
- Admins can further restrict SakuraAlbum to selected Nextcloud groups; users outside those groups cannot queue or write generated albums.
- Per-user managed album and managed media-link quotas are enforced on the server before dry-run-approved writes or background chunks can write.
- Optional Auto-Sync maintenance windows prevent background queue processing outside configured low-load hours.
- Queued automatic work is skipped if the user disables SakuraAlbum or automatic updates before the background job processes the queue.
- Settings changes can queue all enabled source folders for the next background run when automatic sync is active.
- Active source folders must not overlap. A nested source selection such as `/Photos` plus `/Photos/Trip` is blocked before writing so media is not planned twice.
- Background sync is non-parallel and bounded by admin settings for debounce, interval, users per run, runtime, event count, folder count, file count, and album count.
- Background sync summaries include seen events, reserved events, and event-limit hits so admins can tune load profiles from real diagnostics.
- Write runs update the current run summary while processing so the UI can report stage, processed albums, processed links, and error counters.
- Background chunk writes store a per-user/config cursor and requeue themselves while more media remains.
- Chunk cursors expose an estimated progress percent for user-visible status; it is intentionally an estimate while the total remaining scan size is still unknown.
- During chunked background writes, SakuraAlbum never removes stale file links or missing managed albums, because a partial plan must not be treated as the full desired album state.
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

The package is created under `../artifacts/` with a top-level `sakuraalbum/` folder and fails if development-only or repository-only files such as `node_modules`, `vendor`, `.git`, `.github`, `releases`, `SOURCE_MANIFEST.txt`, caches, logs, or nested archives are included.

## Production test rule

Do not enable this app on a production Nextcloud before a backup and restore prompt have been prepared. Live tests may only use the Nextcloud user `albentest`.

During controlled tests, enable admin setting `debugMode` so preview requests, successes, warnings, and failures are stored with enough context for diagnosis.

## Publication notes

SakuraAlbum includes app-store metadata, changelogs, background-job declaration, documentation links, and a release checklist. Before a public app-store submission, re-review `PhotosAlbumAdapter` against the then-current Nextcloud and Photos APIs because it is the intentionally isolated Photos integration point.
