# SakuraAlbum Test Plan

This plan is for a later controlled Nextcloud test window. Do not run it on production until backup and restore steps have been printed and confirmed.

## Required test setup

- Use only Nextcloud user `albentest`.
- Enable SakuraAlbum only for the test window.
- In the SakuraAlbum admin settings, enable:
  - `Global aktiv`
  - `Debug-Logging`
- After enabling the app with `occ app:enable sakuraalbum`, confirm that SakuraAlbum still reports `Global aktiv` as disabled until an admin explicitly enables it in the app settings.
- Keep folder/file limits low for the first test:
  - preview folders: 100
  - preview files: 1000
  - job folders: 100
  - job files: 1000
  - job albums: 20
- Keep automatic sync disabled for the first manual write/delete checks.
- For the dedicated automatic-sync check only, enable:
  - `Automatik`: `Bei Dateiaenderungen`
  - auto debounce: 60 seconds
  - auto users per run: 1
  - auto runtime: 30 seconds
  - auto events per user: 50

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
- Updating a managed album removes file links for media that was moved or deleted only after a clean plan and only for SakuraAlbum-managed albums.
- Optional missing-managed-album cleanup stays disabled unless explicitly enabled for a small isolated test folder.
- If missing-managed-album cleanup is enabled, it deletes only SakuraAlbum-managed Photos albums whose tracked id, owner, and name still match.
- Automatic sync file-event test:
  - create one visible image in an included folder for `albentest`;
  - confirm a dirty-path log entry is recorded and no synchronous write happens during upload;
  - run Nextcloud cron once after the debounce window;
  - confirm SakuraAlbum logs `auto_sync_user_completed` and the managed album is updated;
  - move or delete the image, run cron after debounce, and confirm stale links are removed from the managed album;
  - confirm the job stops within the configured user/runtime/event limits.
- Automatic sync recovery test:
  - if a dirty-path row is left in `processing` with an old `locked_at`, the next auto-sync run must return it to `pending`;
  - confirm `auto_sync_stale_locks_recovered` is logged for recovered queue locks;
  - confirm `auto_sync_runtime_limit_reached` is logged if the runtime limit stops a run.
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
  - `auto_sync_dirty_path_recorded` during the automatic-sync test
  - `auto_sync_stale_locks_recovered` if stale processing locks are recovered
  - `auto_sync_runtime_limit_reached` if the runtime limit stops an automatic run
  - `auto_sync_user_completed` or `auto_sync_user_failed` during the automatic-sync test
  - `stale_file_removal_skipped`, `stale_file_remove_failed`, or stale-removal counters if files changed during sync
- Confirm log context has no raw password, request token, authorization header, app password, or stack-trace arguments.

## Restore requirement

After the test, disable the app and restore the exact pre-test app/database state prepared in the test-window recovery prompt.
