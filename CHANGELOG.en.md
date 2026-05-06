# Changelog

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
