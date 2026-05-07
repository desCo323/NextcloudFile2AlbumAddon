# SakuraAlbum Update Policy

SakuraAlbum is treated as a production Nextcloud app. Every change must be safe for existing users, settings, queued work, generated albums, exports, and diagnostics.

## Production Assumptions

- The app may already be enabled and used by real users.
- Existing SakuraAlbum-managed Photos albums must not be deleted, renamed, or rewritten unexpectedly.
- Existing user settings must keep their meaning after an update.
- Background jobs, dirty queues, cursors, and export jobs may be active during an update.
- Debug logs may contain operational context and must stay redacted before storage.
- GitHub tokens, test passwords, backups, logs, exports, and production data must never be committed.

## Release Licensing

SakuraAlbum currently uses the preliminary non-commercial development and evaluation license in `LICENSE.md`. This license is intentionally not compatible with public Nextcloud App Store publication.

Before any public store release:

- replace or relicense the project under `AGPL-3.0-or-later` or another Nextcloud-compatible license,
- align `LICENSE.md`, `composer.json`, `appinfo/info.xml`, README badges, changelogs, release notes, and store metadata,
- re-run the full release and legal checklist,
- document the license transition in `docs/SESSION_STATE.md`.

Until that happens, release artifacts are development/evaluation previews and must not be advertised as app-store-ready.

## Versioning

Use semantic versioning:

- Patch releases (`1.0.x`) are for bug fixes, diagnostics, performance fixes, safe UI improvements, and backwards-compatible guardrails.
- Minor releases (`1.x.0`) are for backwards-compatible features, new settings, new APIs, or new optional background behavior.
- Major releases (`x.0.0`) are for intentional breaking behavior and require a migration plan, admin-facing release notes, and a rollback rehearsal.

Every release version must be consistent in:

- `appinfo/info.xml`
- `package.json`
- `CHANGELOG.md`
- `CHANGELOG.en.md`
- cache-busting settings assets referenced from `lib/Settings/Admin.php` and `lib/Settings/Personal.php`

## Data Compatibility

- Database schema changes must use Nextcloud migrations under `lib/Migration`.
- Migrations must be idempotent and safe when rerun or partially applied.
- Settings changes must have server-side defaults in `SettingsService`.
- New settings must tolerate missing old values.
- Naming syntax changes must be versioned so old generated albums are not silently captured by a new naming rule.
- Destructive cleanup must be opt-in, previewable, and restricted to clearly SakuraAlbum-managed records.

## Forbidden Update Patterns

- Do not manually edit live production files as the normal update mechanism.
- Do not bump the live app version without a controlled Nextcloud upgrade step.
- Do not run broad `occ app:update --all` during SakuraAlbum deployment.
- Do not run live tests against any user except `albentest`, unless the human explicitly requests a specific production operation.
- Do not delete Photos albums directly unless the operation has its own backup, scope check, and restore prompt.
- Do not store credentials in repo files, docs, remotes, scripts, or shell history snippets.

Emergency hotfixes without a version bump are allowed only to recover production health. They must be documented in `docs/SESSION_STATE.md`, backed up before deployment, and folded into the next normal versioned release.

## Standard Release Flow

1. Update code in the work directory, not in `/var/www/nextcloud/apps/sakuraalbum`.
2. Update changelogs and versioned JS asset references.
3. Run `./scripts/self-check.sh`.
4. Run `./scripts/production-update.sh --preflight`.
5. Commit and push to GitHub using a transient credential only.
6. Prepare a controlled test window with `albentest`.
7. Create a live app backup and database backup.
8. Deploy the new app version.
9. Run the Nextcloud app upgrade step if `occ status` reports `needsDbUpgrade: true`.
10. Run the controlled smoke/regression test.
11. Confirm `occ status` reports `maintenance: false` and `needsDbUpgrade: false`.
12. Document outcome, backup path, restore prompt, commit, and any residual risk in `docs/SESSION_STATE.md`.

## Live Deployment Guardrail

Use:

```bash
./scripts/production-update.sh --preflight
```

Only after the preflight is clean and a human intentionally starts the window:

```bash
SAKURAALBUM_PRODUCTION_UPDATE=1 ./scripts/production-update.sh --deploy
```

The deploy mode creates an app backup first, copies the prepared app to the live app directory, fixes ownership, runs the SakuraAlbum-specific upgrade path if Nextcloud reports a pending upgrade, and prints a restore prompt.

## Rollback

Rollback must restore the complete live app directory from the backup created immediately before deployment:

```bash
sudo rm -rf /var/www/nextcloud/apps/sakuraalbum
sudo cp -a <backup>/app /var/www/nextcloud/apps/sakuraalbum
sudo chown -R www-data:www-data /var/www/nextcloud/apps/sakuraalbum
sudo -u www-data php /var/www/nextcloud/occ status
```

If the failed update included a database migration, rollback requires the matching database backup and must not be done by restoring files alone.

## Test Policy

- Automated tests may inspect production state, but must not modify production data unless a controlled test window is active.
- Controlled live tests use only `albentest`.
- Live tests must reset `albentest` SakuraAlbum state before and after the test.
- Debug mode should be enabled during controlled tests and disabled or reduced after diagnosis when no longer needed.

## Documentation Requirements

Every production-facing update must add a `docs/SESSION_STATE.md` note containing:

- version or hotfix label
- Git commit
- files changed at a high level
- backup path
- restore prompt
- checks run
- live deployment result
- test result
- next safe step
