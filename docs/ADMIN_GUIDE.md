# SakuraAlbum Admin Guide

SakuraAlbum is disabled by default after installation. Users can only write Photos albums when the global admin setting and the personal user setting are both enabled.

## Recommended first rollout

1. Enable the app only in a controlled test window.
2. Open the SakuraAlbum admin settings.
3. Enable `Debug-Logging`.
4. If possible, set `Rollout: erlaubte Gruppen` to a dedicated test group first.
5. Keep `Automatik` on `Manuell` for the first manual preview/write checks.
6. Use small limits for preview, files, folders, albums, and per-user quotas.
7. Test only with a dedicated test user and isolated test folders.
8. Disable the app and restore the pre-test state after the window.

## Rollout and quotas

`Rollout: erlaubte Gruppen` is optional. When it is empty, all users may opt in after the global enable switch is active. When one or more group IDs are configured, a user must be a member of at least one listed group before SakuraAlbum can queue background work or write generated albums.

The per-user quota fields are hard server-side write limits:

- `Benutzerquote: Alben` limits the total active SakuraAlbum-managed albums for one user.
- `Benutzerquote: Medienlinks` limits the total media links SakuraAlbum tracks across that user's managed albums.
- `0` means no total quota for that field.

Quota failures block writes and background chunks before Photos albums are changed.

Direct album ZIP downloads have separate admin limits:

- `Download: Dateilimit` blocks direct ZIP streaming when an album contains too many files.
- `Download: Bytelimit` blocks direct ZIP streaming when the readable album files are too large in total.

These limits are checked before the ZIP response starts. Larger export queues are intentionally left for a later background-export feature.

## Load control

Automatic updates are intentionally delayed and bounded. File events only queue affected source roots. The background job later processes due work with these limits:

- debounce seconds before a queued change can run,
- users per background run,
- runtime seconds per run,
- queued events per user,
- folder count,
- file count,
- album count,
- total managed album/media quota per user,
- job interval minutes.

Use `Schonend` for production tests, `Normal` for regular servers, and `Schnell` only for short test windows or strong hardware. Load-profile buttons update the form only; values are not active until `Speichern`.

## Automatic updates

Set `Automatik` to `Bei Dateiaenderungen` to allow automatic updates server-wide. This is the default for new installations. Users only need to enable `SakuraAlbum verwenden` in their own SakuraAlbum settings; the automatic update state then follows the admin mode.

If a user later disables SakuraAlbum for their account, queued automatic work for that user is skipped and cleared instead of writing stale work.

Use `Auto: Wartungsfenster Start` and `Auto: Wartungsfenster Ende` to restrict automatic queue processing to low-load hours. Empty values mean always allowed. If the end time is earlier than the start time, the window spans midnight, for example `22:00` to `06:00`.

## Diagnostics

Enable debug logging during test windows. The admin log viewer and `Diagnosebericht` expose recent SakuraAlbum events, queue status, run summaries, and redacted context. Known secret keys are redacted before log context is stored, and exception traces omit function arguments.

Direct diagnostic email sending is not implemented yet. Reports are prepared locally and can be copied from the UI.

## OCC dry-run helpers

After SakuraAlbum is installed in a controlled test window, admins can inspect behavior without Photos writes:

```bash
sudo -u www-data php /var/www/nextcloud/occ sakuraalbum:preview --user albentest
sudo -u www-data php /var/www/nextcloud/occ sakuraalbum:sync --user albentest --dry-run
sudo -u www-data php /var/www/nextcloud/occ sakuraalbum:delete-generated --user albentest --dry-run --all
```

Use `--json` for machine-readable output in automated test logs.

## Cleanup

SakuraAlbum tracks generated albums in its own database. Bulk deletion and missing-album cleanup are limited to active SakuraAlbum-managed records and re-check the Photos album id, owner, and name before deletion. Missing managed album cleanup is disabled by default.

Users can also run a personal SakuraAlbum account reset. It is still guarded by the managed-album delete preview, requires `RESET_SAKURAALBUM`, clears only SakuraAlbum queue/cursor state and personal SakuraAlbum settings, and intentionally keeps diagnostic logs for support.
