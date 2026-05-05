# SakuraAlbum Test Plan

This plan is for a later controlled Nextcloud test window. Do not run it on production until backup and restore steps have been printed and confirmed.

## Required test setup

- Use only Nextcloud user `albentest`.
- Enable SakuraAlbum only for the test window.
- In the SakuraAlbum admin settings, enable:
  - `Global aktiv`
  - `Debug-Logging`
- Keep folder/file limits low for the first test:
  - preview folders: 100
  - preview files: 1000
  - job folders: 100
  - job files: 1000
  - job albums: 20

## Pre-human self-checks

Run from the app directory:

```bash
./scripts/self-check.sh
```

Expected result: `Self-check passed`.

## Manual checks after activation

- Admin settings page opens and saves.
- Debug setting remains enabled after reload.
- Personal settings page opens for `albentest`.
- Preview can run against a small test folder.
- Dry-run can run against a small test folder and reports:
  - planned albums
  - planned links
  - whether writing is blocked
  - existing unmanaged album conflicts
- Write endpoint rejects without exact confirmation text `CREATE_ALBUMS`.
- Write endpoint rejects without `planFingerprint` from a fresh successful dry-run.
- Write endpoint rejects if the supplied `planFingerprint` was not stored by a recent successful dry-run for the same user.
- Write endpoint rejects if settings or folder contents changed after the dry-run fingerprint was created.
- Write endpoint rejects while global admin setting or personal user setting is disabled.
- In the controlled write test, only use files owned by `albentest`.
- Managed album list shows only SakuraAlbum-tracked albums for `albentest`.
- Delete dry-run works for selected managed albums and reports:
  - Photos albums that would be deleted
  - stale tracking records that would be cleaned
  - blocked albums such as renamed Photos albums
- Delete endpoint rejects without exact confirmation text `DELETE_MANAGED_ALBUMS`.
- Delete endpoint rejects without `planFingerprint` from a fresh delete dry-run.
- Delete endpoint rejects if the supplied `planFingerprint` was not stored by a recent successful delete dry-run for the same user.
- Delete endpoint rejects if the managed album list, Photos album name, or Photos owner changed after the delete dry-run fingerprint was created.
- Delete endpoint rejects truncated delete-all plans and any owner/name mismatch.
- A controlled delete test may delete only albums created by SakuraAlbum during the same test window.
- Admin log viewer shows:
  - `admin_settings_updated`
  - `user_settings_updated`
  - `preview_requested` when debug is enabled
  - `preview_completed` or `preview_failed`
  - `dry_run_started`
  - `dry_run_completed` or `dry_run_failed`
  - `write_started`
  - `write_completed`, `write_completed_with_errors`, or `write_failed`
  - `managed_delete_dry_run_started`
  - `managed_delete_dry_run_completed` or `managed_delete_dry_run_failed`
  - `managed_delete_started`
  - `managed_delete_completed` or `managed_delete_failed`
- Confirm log context has no raw password, request token, authorization header, app password, or stack-trace arguments.

## Restore requirement

After the test, disable the app and restore the exact pre-test app/database state prepared in the test-window recovery prompt.
