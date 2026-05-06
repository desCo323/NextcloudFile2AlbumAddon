# Store Release Checklist

This checklist tracks the publication state for SakuraAlbum.

## Metadata

- App id is lowercase ASCII and matches the archive root: `sakuraalbum`.
- App name does not use `Nextcloud`.
- License is `AGPL-3.0-or-later`.
- `bugs`, `website`, `repository`, `discussion`, and documentation links are present.
- `CHANGELOG.md` exists for the app store.
- `CHANGELOG.en.md` exists for user update notifications.
- The background job class is declared in `appinfo/info.xml`.
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
- Queued automatic work is skipped if the user disables automatic updates before processing.
- Debug logs redact common secret keys before storage.

## Packaging

- Run `./scripts/self-check.sh`.
- Run `./scripts/build-artifact.sh`.
- Validate the package self-check after unpacking.
- Scan source and package for GitHub token patterns and the known test password pattern.
- Confirm the package contains no `.git`, `node_modules`, `vendor`, caches, logs, nested archives, or local test artifacts.

## Store blocker to re-review

`PhotosAlbumAdapter` currently isolates the integration with the local Photos album implementation. Before public app-store submission, re-check whether a stable public server-side Photos album API exists for the targeted Nextcloud version and replace this adapter if needed.
