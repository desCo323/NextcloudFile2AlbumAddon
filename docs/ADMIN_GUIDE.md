# SakuraAlbum Admin Guide

SakuraAlbum is disabled by default after installation. Users can only write Photos albums when the global admin setting and the personal user setting are both enabled.

## Recommended first rollout

1. Enable the app only in a controlled test window.
2. Open the SakuraAlbum admin settings.
3. Enable `Debug-Logging`.
4. Keep `Automatik` on `Manuell` for the first manual preview/write checks.
5. Use small limits for preview, files, folders, and albums.
6. Test only with a dedicated test user and isolated test folders.
7. Disable the app and restore the pre-test state after the window.

## Load control

Automatic updates are intentionally delayed and bounded. File events only queue affected source roots. The background job later processes due work with these limits:

- debounce seconds before a queued change can run,
- users per background run,
- runtime seconds per run,
- queued events per user,
- folder count,
- file count,
- album count,
- job interval minutes.

Use `Schonend` for production tests, `Normal` for regular servers, and `Schnell` only for short test windows or strong hardware. Load-profile buttons update the form only; values are not active until `Speichern`.

## Automatic updates

Set `Automatik` to `Bei Dateiaenderungen` to allow automatic updates server-wide. Users still need to enable `Automatisch aktuell halten` in their own SakuraAlbum settings.

If a user later disables automatic updates, queued automatic work for that user is skipped and cleared instead of writing stale work.

## Diagnostics

Enable debug logging during test windows. The admin log viewer and `Diagnosebericht` expose recent SakuraAlbum events, queue status, run summaries, and redacted context. Known secret keys are redacted before log context is stored, and exception traces omit function arguments.

Direct diagnostic email sending is not implemented yet. Reports are prepared locally and can be copied from the UI.

## Cleanup

SakuraAlbum tracks generated albums in its own database. Bulk deletion and missing-album cleanup are limited to active SakuraAlbum-managed records and re-check the Photos album id, owner, and name before deletion. Missing managed album cleanup is disabled by default.
