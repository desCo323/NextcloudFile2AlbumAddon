# Changelog

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
