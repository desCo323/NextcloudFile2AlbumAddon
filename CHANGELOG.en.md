# Changelog

## 1.0.11

- Added a safe stale-managed-album cleanup preview. Users can now check only SakuraAlbum-managed albums that are no longer part of the current folder plan, for example after folder renames or rule changes.
- Kept destructive cleanup behind the existing dry-run, fresh fingerprint, ownership/name recheck, and exact `DELETE_MANAGED_ALBUMS` confirmation.
- Simplified the Auto-Sync file-event job nudge so WebDAV create/move/delete requests avoid heavier Nextcloud background-job reset paths while still scheduling the SakuraAlbum job soon.
- Versioned cache-busting assets as `admin-settings-1011` and `personal-settings-1011`.

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
