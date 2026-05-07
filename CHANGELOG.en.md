# Changelog

## 1.0.6

- Added operational health diagnostics for stale Auto-Sync locks, overdue queue work, stuck runs, failed cursors, failed exports, recent warning/error logs, missing managed Photos albums, and stale Nextcloud Cron state.
- Diagnostic reports now log summarized health findings so recurring failures can be found from SakuraAlbum logs without manually interpreting every raw entry.
- Added CSV downloads for user and admin diagnostic logs; every CSV export is also persisted in the SakuraAlbum AppData diagnostic folder for later server-side analysis.
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
- Added guarded direct ZIP downloads for individual SakuraAlbum-managed albums.
- Added a guarded personal account reset with preview and exact confirmation.
- Improved source-folder rule explanations and reset documentation.
- Added a controlled live smoke-test helper for small `albentest` test windows.
- Documented the production rollback and safety status after the server reboot during testing.

## 0.2.6

- Added safe OCC commands for controlled test windows.
- Added `sakuraalbum:preview`, `sakuraalbum:sync --dry-run`, and `sakuraalbum:delete-generated --dry-run`.
- Commands do not write or delete Photos albums.
- Added cache-busting `admin-settings-026` and `personal-settings-026` assets.

## 0.2.5

- Added admin rollout controls with optional allowed Nextcloud groups.
- Added optional Auto-Sync maintenance windows for low-load background processing.
- Added per-user managed album and managed media-link quotas.
- Expanded admin and personal UI explanations for rollout, quota, and window state.
- Added cache-busting `admin-settings-025` and `personal-settings-025` assets.

## 0.2.4

- Added user, admin, developer, privacy, and store-release documentation.
- Added app metadata links for documentation and discussion.
- Declared the SakuraAlbum background job in `appinfo/info.xml`.
- Hardened automatic sync so queued work is skipped if a user disables automatic updates before processing.
- Expanded release self-checks for metadata, UI routes/labels, and the automatic opt-in skip guard.

## 0.2.3

- Added redacted diagnostic report preparation for users and admins.
- Added UI actions to prepare and copy diagnostic reports.
- Reports include settings, queue state, recent runs, managed-album summary, and recent SakuraAlbum logs.
