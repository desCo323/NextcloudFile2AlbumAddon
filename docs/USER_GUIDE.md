# SakuraAlbum User Guide

SakuraAlbum creates Nextcloud Photos albums from folders you already have in Files. You choose the source folders once, then SakuraAlbum keeps the generated albums updated in the background when your administrator allows automatic updates.

## First setup

1. Open your personal settings and choose `SakuraAlbum`.
2. Turn on `SakuraAlbum verwenden`.
3. Add one or more source folders with `Ordner hinzufuegen`.
4. Choose how album names should be built.
5. Turn on `Automatisch aktuell halten` if it is available.
6. Save with `Speichern und Hintergrundlauf vormerken`.

If automatic updates are enabled, the first generation runs in background chunks. The progress bar shows pending work, recent run details, and the current continuation cursor for large libraries.

If the settings page says `Gruppe gesperrt` or `Nicht freigegeben`, SakuraAlbum is limited to administrator-selected groups. In that state the app can show settings, but it will not queue or write albums for your account.

## Folder rules

Each source folder can use the global rule, a custom depth, or `Alles in ein Album`.

- `Standard`: uses the default depth, name template, and separator.
- `Eigene Tiefe`: uses a custom depth for this source folder only.
- `Alles in ein Album`: puts the selected folder and all subfolders into one generated album.

Nested active source folders are blocked. For example, do not select `/Photos` and `/Photos/Trip` at the same time. This prevents duplicate media and confusing album names.

## Updates and deletion

`Update vormerken` queues all active source folders for the next background run. File changes also queue updates when automatic mode is active. SakuraAlbum does not write during uploads, moves, or deletes; it waits for the configured background job limits.

Administrators may also set low-load time windows and per-user quotas. If a quota is reached, SakuraAlbum blocks the write and shows the quota reason before changing Photos albums.

`Verwaltete Alben` lists only albums that SakuraAlbum created or updated and still tracks. Deletion requires a preview and the exact confirmation text, and renamed or foreign albums are blocked.

Each listed managed album has a `ZIP` action. SakuraAlbum first checks that the album is still clearly managed by your account and that it fits the administrator's direct-download limits. If the check passes, the browser receives a ZIP stream of that one album. If the album is too large, the download is blocked before the server starts streaming.

`Konto-Reset pruefen` is the safest way to start from zero. The preview shows which SakuraAlbum-managed albums would be deleted and which tracking records would be cleaned. The final reset needs the exact text `RESET_SAKURAALBUM`; it resets your SakuraAlbum settings, clears SakuraAlbum's background queue/cursor state for your account, and leaves diagnostic logs available for troubleshooting.

## Error reports

`Fehlerbericht vorbereiten` creates a redacted diagnostic report with your SakuraAlbum settings, recent runs, queue state, managed-album summary, and recent app logs. Direct mail sending is planned for a later release and is currently disabled.
