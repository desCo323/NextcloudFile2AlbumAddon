# SakuraAlbum User Guide

SakuraAlbum creates Nextcloud Photos albums from folders you already have in Files. You choose the source folders once, then SakuraAlbum keeps the generated albums updated in the background when your administrator allows automatic updates.

## First setup

1. Open your personal settings and choose `SakuraAlbum`.
2. Turn on `SakuraAlbum verwenden`.
3. Add one or more source folders with `Quellordner hinzufuegen`.
4. Choose how album names should be built.
5. Save with `Speichern und Hintergrundlauf vormerken`.

If your administrator set `Automatik` to `Bei Dateiaenderungen`, automatic updates start as soon as SakuraAlbum is enabled for your account. The first generation runs in background chunks. The progress bar shows pending work, recent run details, and the current continuation cursor for large libraries.

If the settings page says `Gruppe gesperrt` or `Nicht freigegeben`, SakuraAlbum is limited to administrator-selected groups. In that state the app can show settings, but it will not queue or write albums for your account.

## Folder rules

Each source folder can use the global rule, a custom depth, or `Alles in ein Album`.

- `Standard`: uses the default depth, name template, and separator.
- `Eigene Tiefe`: uses a custom depth for this source folder only.
- `Alles in ein Album`: puts the selected folder and all subfolders into one generated album.

Nested active source folders are blocked. For example, do not select `/Photos` and `/Photos/Trip` at the same time. This prevents duplicate media and confusing album names.

`Ordner-Regeln` are exceptions inside active source folders. Use them when most of `/Photos` should follow the default, but one subtree needs a different depth, should be collected as one album, or should be skipped. A later settings change is handled by the next background update: SakuraAlbum adds the newly planned managed albums and can remove old managed album containers when the administrator has enabled safe missing-album cleanup. Media files themselves are not deleted.

## Updates and deletion

`Update vormerken` queues all active source folders for the next background run. File changes also queue updates when automatic mode is active for your administrator and SakuraAlbum is enabled for your account. SakuraAlbum does not write during uploads, moves, or deletes; it waits for the configured background job limits.

Administrators may also set low-load time windows and per-user quotas. If a quota is reached, SakuraAlbum blocks the write and shows the quota reason before changing Photos albums.

`Verwaltete Alben` lists only albums that SakuraAlbum created or updated and still tracks. Deletion requires a preview and the exact confirmation text, and renamed or foreign albums are blocked.

Each listed managed album still has a direct `ZIP` action for small, clearly managed albums. SakuraAlbum first checks that the album still belongs to your account and fits the administrator's direct-download limits. If the album is too large, use `Album-Downloads` instead.

`Album-Downloads` prepares SakuraAlbum-managed albums and normal Photos albums as background exports. The ZIP files are written into your Files area under `SakuraAlbum Exports`. Very large exports are split into part ZIP files from about 1 GiB upward, and the status table shows pending, running, failed, and ready jobs with a progress bar.

`Konto-Reset pruefen` is the safest way to start from zero. The preview shows which SakuraAlbum-managed albums would be deleted and which tracking records would be cleaned. The final reset needs the exact text `RESET_SAKURAALBUM`; it resets your SakuraAlbum settings, clears SakuraAlbum's background queue/cursor state for your account, and leaves diagnostic logs available for troubleshooting.

## Error reports

`Fehlerbericht vorbereiten` creates a redacted diagnostic report with your SakuraAlbum settings, recent runs, queue state, managed-album summary, and recent app logs. Direct mail sending is planned for a later release and is currently disabled.
