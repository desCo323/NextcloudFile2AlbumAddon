# Changelog

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
