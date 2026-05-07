# Store Release Checklist

This checklist tracks the publication state for SakuraAlbum.

## Metadata

- App id is lowercase ASCII and matches the archive root: `sakuraalbum`.
- App name does not use `Nextcloud`.
- Current repository license is the preliminary non-commercial development and evaluation license in `LICENSE.md`.
- Store blocker: this preliminary license is not app-store-compatible. Before public Nextcloud App Store submission, replace or relicense the project under `AGPL-3.0-or-later` or another compatible license, then align `LICENSE.md`, `composer.json`, `appinfo/info.xml`, README badges, changelogs, and release metadata.
- `appinfo/info.xml` currently keeps the schema-compatible app metadata license value required by current Nextcloud tooling, but this is not sufficient for publication while `LICENSE.md` remains non-commercial.
- `bugs`, `website`, `repository`, `discussion`, and documentation links are present.
- `CHANGELOG.md` exists for the app store.
- `CHANGELOG.en.md` exists for user update notifications.
- The background job class is declared in `appinfo/info.xml`.
- Dry-run OCC command classes are declared in `appinfo/info.xml`.
- The archive root is `sakuraalbum/`.

## Safety

- App is disabled by default after installation.
- Global admin enablement is separate from Nextcloud's reserved app activation flag.
- Personal routes operate on the authenticated user only.
- Admin routes use `AuthorizedAdminSetting`.
- Controllers keep CSRF protection enabled.
- Write and delete operations require fresh server-recorded fingerprints and exact confirmation text.
- Automatic sync requires admin mode, user enablement, and user automatic opt-in.
- Group-limited rollout, per-user quotas, and maintenance windows are enforced in server-side services, not only in the UI.
- OCC helpers for sync and generated-album deletion require explicit `--dry-run` and do not call write/delete methods.
- Queued automatic work is skipped if the user disables automatic updates before processing.
- Debug logs redact common secret keys before storage.

## Packaging

- Run `./scripts/self-check.sh`.
- Run `./scripts/production-update.sh --preflight` before any production deployment or release tag.
- Run `php -l scripts/live-smoke.php`.
- Run `bash -n scripts/live-smoke.sh`.
- Run `./scripts/build-artifact.sh`.
- Validate the package self-check after unpacking.
- Scan source and package for GitHub token patterns and the known test password pattern.
- Confirm the package contains no `.git`, `node_modules`, `vendor`, caches, logs, nested archives, or local test artifacts.

## Controlled live smoke

- Keep SakuraAlbum globally disabled before and after the window.
- Deploy only after an app/database backup and restore prompt were written to `docs/SESSION_STATE.md`.
- Run the live smoke only with `SAKURAALBUM_LIVE_SMOKE=1` and the test user `albentest` through `scripts/live-smoke.sh`.
- Confirm the smoke uses the isolated source `/Photos/SakuraAlbumV1Smoke`.
- Confirm cleanup leaves no active SakuraAlbum-managed albums, no dirty paths, no sync cursors, and no Photos albums for the smoke source.

## Store blocker to re-review

`PhotosAlbumAdapter` currently isolates the integration with the local Photos album implementation. Before public app-store submission, re-check whether a stable public server-side Photos album API exists for the targeted Nextcloud version and replace this adapter if needed.
