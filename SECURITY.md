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

## Not yet implemented

- Background sync jobs.

Background execution must include non-parallel locks, explicit limits, tracking, and rollback documentation before production testing.
