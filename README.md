# SakuraAlbum

SakuraAlbum is a Nextcloud app for creating managed Photos albums from existing folder structures.

The current development version focuses on safe configuration and preview planning. It does not need to write to production Photos albums until a controlled test window is prepared.

## Current scope

- Admin settings for global enablement, default folders, scan limits, job limits, and video policy.
- Personal settings for opt-in, folders, exclusions, naming template, separator, depth, and media type.
- Preview API that scans only the current user folder and returns planned albums without writing album data.
- App-owned tables for future tracking of generated albums and sync runs.
- App-owned log table for errors, successes, warnings, future cron/sync events, and optional debug context.
- Admin debug mode with stricter diagnostic logging and a log viewer.
- SVG branding with a sakura blossom falling onto a dog.

## Safety model

- Generated albums are intended to be tracked in `sakuraalbum_albums`.
- Bulk deletion must only operate on app-tracked generated albums.
- Preview has strict folder/file limits from admin settings.
- Debug logs redact common secret keys before storing context.
- Background writing jobs are intentionally not part of the first safe source drop.

## Local checks

Run from the app directory:

```bash
./scripts/self-check.sh
```

The check runs PHP syntax checks, JavaScript syntax checks, XML metadata validation, pure naming smoke tests, and a basic secret-pattern scan.

## Snapshot

The first safe source snapshot is stored as `releases/sakuraalbum-0.1.0.tar.gz.base64` with checksum in `releases/sakuraalbum-0.1.0.sha256`.

## Production test rule

Do not enable this app on a production Nextcloud before a backup and restore prompt have been prepared. Live tests may only use the Nextcloud user `albentest`.

During controlled tests, enable admin setting `debugMode` so preview requests, successes, warnings, and failures are stored with enough context for diagnosis.
