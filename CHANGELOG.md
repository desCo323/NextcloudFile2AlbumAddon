# Changelog

## 1.0.10

- Installed Playwright browser testing in the development workspace and added a first Chromium smoke test for SakuraAlbum button contrast.
- Fixed the CSS specificity of dangerous SakuraAlbum buttons so the high-contrast white text and red background win over generic app button styles in a real browser.
- Versioned cache-busting assets as `admin-settings-1010` and `personal-settings-1010`.

## 1.0.9

- Improved SakuraAlbum button readability across Nextcloud themes by adding app-scoped high-contrast styles for normal, primary, disabled, and dangerous buttons.
- Fixed the reported low-contrast danger actions such as `Alle verwalteten pruefen` and `Konto-Reset pruefen` without changing their safety behavior.
- Kept the 1.0.8 security hardening unchanged and versioned cache-busting assets as `admin-settings-109` and `personal-settings-109`.

## 1.0.8

- Hardened user path normalization with length, segment, nesting, traversal, NUL, and control-character guards.
- Added separate admin limits for background album exports by file count and readable bytes, enforced both when queuing and when executing export jobs.
- Reduced export-list load by sampling large Photos albums in the UI and performing exact size checks only when an export starts.
- Added temporary-file cleanup for failed ZIP export jobs.
- Hardened diagnostic CSV exports with server-side age/count retention, cell length caps, and spreadsheet-formula injection protection.
- Added periodic SakuraAlbum log retention pruning using the configured debug retention period.
- Changed preview debug logging to store only a compact settings summary instead of raw user-provided settings.
- Documented the SakuraAlbum security model with concrete attack scenarios and added matching security backlog checks.
- Added a guarded live security smoke helper for path hardening, export limits, diagnostic CSV hardening, export success, and reset cleanup.

## 1.0.7

- Fixed `info`-level SakuraAlbum diagnostic log writes on MySQL/MariaDB by ensuring the `level` field is always persisted and by adding a database default for existing installations.
- Added this fix after the controlled 1.0.6 test window exposed `SakuraAlbum failed to write app log` entries in the Nextcloud server log.

## 1.0.6

- Added the preliminary non-commercial development and evaluation license in `LICENSE.md` and marked the current license as a blocker for public app-store release.
- Expanded the GitHub project page with more use cases, additional Mermaid diagrams, manual installation, and manual update instructions.
- Added `docs/TEST_BACKLOG.md` for functional, security, UI, usability, readability, and next-update regression testing.
- Added operational health diagnostics that evaluate stale Auto-Sync locks, overdue queue work, stuck runs, failed cursors, failed exports, recent warning/error logs, missing managed Photos albums, and stale Nextcloud Cron state.
- Diagnostic reports now log summarized health findings so recurring failures can be found from SakuraAlbum logs without manually interpreting every raw entry.
- Added CSV downloads for user and admin diagnostic logs; each CSV export is also persisted in the SakuraAlbum AppData diagnostic folder for later server-side analysis.
- Hardened Auto-Sync rename/delete event handling when Nextcloud provides a non-existing source node by deriving the user from the file path instead of logging a generic event failure.

## 1.0.5

- Reduced personal-settings status polling load: no overlapping status requests, hidden tabs pause polling, idle tabs poll less often, and active background work still updates promptly.
- Added visible performance warnings for large SakuraAlbum-managed albums so users can identify albums that will load slowly in Nextcloud Photos and adjust album depth or folder rules.

## 1.0.4

- Added folder-specific exception rules inside source folders for custom depth, one combined album, or exclusion.
- Added background album export jobs for SakuraAlbum-managed and native Photos albums, with user-visible progress and ZIP part files from 1 GiB upward.
- Added automatic detection for SakuraAlbum-managed Photos albums that were deleted outside SakuraAlbum, so Auto-Sync can queue a rebuild without waiting for a file event.
- Expanded Auto-Sync diagnostics for ignored file events and missing managed album rebuilds.

## 1.0.3

- Scheduled Auto-Sync nudges after the configured debounce window instead of before it, preventing early empty Cron runs from delaying real work by another job interval.
- Forced the SakuraAlbum background job's stored run state to the debounced run time even when Nextcloud's job-list API resets the job first.
- Stabilized Auto-Sync status timestamps so already due work is shown as waiting for Cron instead of a moving "earliest next run" time.

## 1.0.2

- Fixed SakuraAlbum's Auto-Sync Cron health check to read Nextcloud's real app config values `core.lastcron` and `core.backgroundjobs_mode`.
- The admin Auto-Status no longer reports `cron_not_recorded` when Nextcloud Cron is actually running.

## 1.0.1

- Made `Bei Dateiaenderungen` the default admin Auto-Sync mode for new installations.
- Removed the confusing second personal Auto-Sync opt-in as an effective blocker: when the admin allows file-event updates, users only need to enable SakuraAlbum for their account.
- Updated personal and admin UI copy so the automatic-update state matches the effective server behavior.

## 1.0.0

- Marked SakuraAlbum as the first version-1 release candidate.
- Added guarded direct ZIP download for individual SakuraAlbum-managed albums with admin-controlled file and byte limits.
- Added a personal account reset flow guarded by a reset preview and exact `RESET_SAKURAALBUM` confirmation.
- Reset now deletes only clearly SakuraAlbum-managed Photos albums, clears SakuraAlbum queue/cursor state, and removes the user's SakuraAlbum settings.
- Improved source-folder rule UX with clearer explanations for global defaults, custom depth, and `Alles in ein Album`.
- Added a controlled live smoke-test helper for tiny `albentest` test windows.
- Documented post-crash recovery status and the production safety rollback process.

## 0.2.6

- Added safe OCC commands for controlled test windows:
  - `sakuraalbum:preview --user <uid>`
  - `sakuraalbum:sync --user <uid> --dry-run`
  - `sakuraalbum:delete-generated --user <uid> --dry-run --all`
- Commands are dry-run/preview oriented and do not write or delete Photos albums.
- Added command metadata to `appinfo/info.xml`.
- Added cache-busting `admin-settings-026` and `personal-settings-026` assets.

## 0.2.5

- Added admin rollout controls with optional allowed Nextcloud groups and a group picker.
- Added optional Auto-Sync maintenance windows so background processing can be limited to low-load hours.
- Added per-user quotas for SakuraAlbum-managed album count and managed media links, enforced before write or chunk jobs.
- Expanded admin and personal UI explanations for rollout state, quotas, maintenance windows, and quota-blocked plans.
- Added admin group listing API at `/api/v1/admin/groups`.
- Added cache-busting `admin-settings-025` and `personal-settings-025` assets.

## 0.2.4

- Added user, admin, developer, privacy, and store-release documentation.
- Added documentation and discussion links to `appinfo/info.xml`.
- Declared the SakuraAlbum background job in app metadata.
- Hardened automatic sync so queued work is skipped and cleared if a user disables SakuraAlbum or automatic updates before processing.
- Added release metadata and UI smoke self-checks.
- Added cache-busting `admin-settings-024` and `personal-settings-024` assets.

## 0.2.3

- Added redacted diagnostic report preparation for users at `/api/v1/diagnostics/report`.
- Added admin diagnostic report preparation at `/api/v1/admin/diagnostics/report`.
- Reports include settings, queue state, cursor state, recent runs, managed-album summary, and recent SakuraAlbum logs for the relevant scope.
- Reports explicitly mark mail sending as future work with `sendMailReady=false`.
- Added personal `Fehlerbericht vorbereiten` and admin `Diagnosebericht` UI actions with JSON copy support.
- Added cache-busting `admin-settings-023` and `personal-settings-023` assets.

## 0.2.2

- Added an admin-only "due Auto-Sync now" action that processes queued automatic work through the existing load limits instead of bypassing cron safety.
- Added a clearer admin automation card, an explicit "Automatik vorbereiten" action, and translated Auto-Sync queue event/status labels.
- Added a personal automation card and "Automatik einschalten" action so users can enable SakuraAlbum plus automatic background generation in one step when admins allow it.
- Moved manual write controls into advanced test tools; normal use now emphasizes saving settings and background generation.
- The personal status endpoint now returns current user, effective, and admin settings so the UI can refresh status without losing server-side state.
- Chunk cursors now expose an estimated progress percent so partial background chunks no longer appear as a finished 100% run.
- Added cache-busting `admin-settings-022` and `personal-settings-022` assets.

## 0.2.1

- Added a resumable background sync cursor table for large first-generation runs.
- Added deterministic chunk execution plans that continue after the last processed file path.
- Automatic file-event sync now writes one bounded chunk per run and requeues the user when more media remains.
- Chunk writes keep the existing safety gates but do not require a human dry-run fingerprint because they are server-owned background work.
- Stale-file removal and missing-managed-album cleanup are disabled during chunk writes so partial scans cannot remove media or albums that were simply not reached yet.
- Personal status now exposes the current background cursor, chunk count, processed link count, and last cursor path.
- Added cache-busting `admin-settings-021` and `personal-settings-021` assets.

## 0.2.0

- Added structured multi-source folder settings with stable source ids and legacy `includePaths` fallback.
- Added per-folder rule modes: use global defaults, use a custom album depth, or treat the selected folder and all subfolders as one album.
- Added source-folder overlap detection so nested active sources are blocked before writes can duplicate work or create confusing albums.
- Added an authenticated folder picker API for selecting folders from the current user's file area instead of typing raw paths.
- Added personal background update controls: saving settings can queue a refresh, and users can explicitly queue an update for the next Auto-Sync run.
- Added personal sync status API and UI with a progress bar, queue state, recent run details, and expandable activity diagnostics.
- Write runs now publish running progress summaries with processed album/link counters and progress stage.
- Added cache-busting `admin-settings-020` and `personal-settings-020` assets.

## 0.1.12

- Load versioned admin and personal JavaScript asset names to avoid stale browser-cached settings scripts during controlled test windows.
- Admin save feedback now reports the saved automatic mode explicitly.
- Kept the fixed query-string handling for admin Auto-Status and log calls.

## 0.1.11

- Fixed admin Auto-Status and log loading URLs by keeping query parameters outside `OC.generateUrl()`, preventing encoded-query 404 responses.
- Updated the shared personal API helper to preserve query parameters safely after generating the Nextcloud app URL.
- Clarified the admin automatic-update explanation: admins allow file-event mode, users still opt in per account.

## 0.1.10

- Added a personal `Automatisch aktuell halten` opt-in toggle.
- Automatic file-event sync now requires admin file-event mode, the user account being enabled, and the user's automatic-update opt-in.
- Personal settings now explain whether automatic updates are centrally unavailable, available but off, or active for the current user.
- Kept admin load controls as the server-side limit authority while making the user-facing automatic-update state explicit.

## 0.1.9

- Added an admin Auto-Sync status API and UI view showing pending, processing, failed, due-user, and next-due queue state.
- Added admin load-profile buttons for conservative, balanced, and fast Auto-Sync/job limits.
- Automatic file events now collapse dirty queue rows to the affected include root, reducing queue churn when many files change inside one selected folder.
- Automatic sync run summaries now report `pendingEventsSeen`, `lockedEvents`, and `eventLimitHits`.
- Reworked the personal settings UI for clearer effective-state, preview, dry-run, write, run-history, managed-album, and delete-result displays.
- Expanded UI explanations for admin limits, automatic update timing, block reasons, and safe deletion.

## 0.1.8

- Fixed Photos duplicate-link handling for Nextcloud/Photos versions where an already-linked album file can surface as an `OCP\DB\Exception` unique-constraint violation instead of `AlreadyInAlbumException`.
- Re-checks the Photos album/file/owner row before treating a database unique-constraint violation as `already_linked`, so unrelated DB failures still fail loudly.

## 0.1.7

- Added automatic recovery for stale auto-sync queue locks if a cron/background run stops after marking events as processing.
- Added explicit runtime-limit logging for automatic sync so admins can see when configured limits stop a run.
- Extended the self-check script to guard stale-lock recovery and runtime-limit diagnostics.

## 0.1.6

- Added debounced file-event based auto-sync infrastructure with admin controls for mode, debounce, cron interval, users per run, runtime, and queued events per run.
- Added a dirty-path table and non-parallel background job so file create/write/delete/rename events only queue work instead of doing heavy scans inside the file request.
- Added managed-album reconciliation for stale file links, guarded by an admin setting and limited to SakuraAlbum-managed albums.
- Added optional cleanup for managed albums that are no longer present in the current plan, disabled by default and checked against Photos album id, owner, and name before deletion.
- Improved admin UX for server load controls and personal UX for managed-album selection, delete previews, blocked write explanations, and post-write summaries.

## 0.1.5

- Fixed admin global enable storage so SakuraAlbum no longer reads or writes Nextcloud's reserved app activation key `enabled`.
- Added a self-check guard that blocks future use of the reserved app-config key for SakuraAlbum admin settings.
- Documented that version 0.1.4 must not be used for live testing.

## 0.1.4

- Added server-side freshness checks for write and managed-delete plan fingerprints.
- Stored successful dry-run fingerprints in sync-run summaries so write/delete jobs must follow a recent matching dry-run by the same user.
- Cleaned the guarded write/delete service formatting for easier security review.

## 0.1.3

- Added a reproducible artifact build script that packages the app under a `sakuraalbum/` root directory.
- Added artifact hygiene checks to block development-only files such as `node_modules`, caches, `.git`, `vendor`, logs, and nested archives.

## 0.1.2

- Added plan fingerprints for write and managed-delete jobs so a fresh dry-run/delete preview must match the write request.
- Hardened debug exception logging by removing stack-trace arguments and redacting common token strings in messages/context.
- Kept destructive delete confirmation mandatory in admin settings.
- Improved settings UI tooltips, focus states, mobile spacing, and escaped numeric values.

## 0.1.1

- Added guarded managed-album deletion APIs.
- Added personal UI for managed album listing, delete dry-run, and confirmed delete.
- Added Photos album id, owner, and name re-checks before deletion.
- Added CSRF posture check to the local self-check script.
- Updated security, test, and recovery documentation.

## 0.1.0

- Initial SakuraAlbum app metadata.
- Admin and personal settings pages.
- Preview planning API.
- Managed album and sync-run database migrations.
- App log table, log service, admin debug settings, and admin log viewer.
- Local self-check script.
