# Security Policy

SakuraAlbum is developed with production safety as the primary constraint.

## Reporting

Open a private report with the repository owner for security-sensitive issues. Do not include credentials, app passwords, GitHub tokens, or private Nextcloud URLs in public issues.

## Current guarantees

- Preview requests do not write albums.
- Personal API routes operate on the authenticated user only.
- Admin settings routes require an authorized admin setting.
- Stored generated-album metadata is separate from user-created Photos albums.
- Album creation requires exact confirmation text, a matching recent dry-run plan fingerprint stored by the server for the same user, and rejects unsafe dry-run findings.
- Managed deletion requires exact confirmation text, a matching recent delete dry-run plan fingerprint stored by the server for the same user, and only targets active app-owned records for the current user.
- Managed deletion re-checks Photos album id, owner, and current name before deleting.
- Controllers keep the framework default CSRF protection enabled.
- SakuraAlbum admin settings do not read or write Nextcloud's reserved app activation key `enabled`.
- Debug context is sanitized for common secrets before it is stored, and debug stack traces do not include function arguments.
- Duplicate Photos album links are treated as idempotent only after SakuraAlbum confirms the exact album/file/owner link already exists.
- File-event handling queues dirty paths only; scans and Photos writes happen later in a non-parallel background job.
- Automatic sync is bounded by admin-controlled debounce, cron interval, user count, runtime, queued event count, folder count, file count, and album count.
- Queued automatic sync work is skipped and cleared if the user disables SakuraAlbum or automatic updates before the queue is processed.
- Automatic sync recovers stale processing locks from interrupted runs instead of leaving queued work permanently reserved.
- Stale file removal is limited to Photos albums that SakuraAlbum already tracks as managed.
- Optional missing-managed-album cleanup is disabled by default and re-checks Photos album id, owner, and name before deleting.

## Not yet implemented

- External error-report email sending from the UI.
- Store signing and formal Nextcloud app-store submission.
- Public API re-review for the isolated Photos album integration adapter.
