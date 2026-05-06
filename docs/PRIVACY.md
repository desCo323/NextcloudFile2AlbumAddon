# SakuraAlbum Privacy Notes

SakuraAlbum stores only app-specific configuration and synchronization metadata needed to create and maintain generated Photos albums.

## Stored data

- Admin settings such as global enablement, limits, debug mode, and automatic-update mode.
- User settings such as source folders, excluded patterns, naming template, separator, depth, media type, and automatic opt-in.
- Managed album metadata for SakuraAlbum-generated albums.
- Sync run summaries, chunk cursors, and dirty-path queue rows.
- SakuraAlbum diagnostic logs.

## Diagnostic reports

Diagnostic reports are generated locally and are not sent anywhere in this release. They include redacted settings, queue state, run summaries, managed-album counts, and recent SakuraAlbum logs.

Known secret-like keys such as password, token, authorization, cookie, app password, and GitHub token are redacted before context is stored. Exception stack traces omit function arguments.

Future mail sending must remain explicit, minimal, and opt-in.
