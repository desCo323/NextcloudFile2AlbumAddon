# Security Policy

SakuraAlbum is developed with production safety as the primary constraint.

## Reporting

Open a private report with the repository owner for security-sensitive issues. Do not include credentials, app passwords, GitHub tokens, or private Nextcloud URLs in public issues.

## Current guarantees

- Preview requests do not write albums.
- Personal API routes operate on the authenticated user only.
- Admin settings routes require an authorized admin setting.
- Stored generated-album metadata is separate from user-created Photos albums.
- Album creation requires exact confirmation text, a matching dry-run plan fingerprint, and rejects unsafe dry-run findings.
- Managed deletion requires exact confirmation text, a matching delete dry-run plan fingerprint, and only targets active app-owned records for the current user.
- Managed deletion re-checks Photos album id, owner, and current name before deleting.
- Controllers keep the framework default CSRF protection enabled.
- Debug context is sanitized for common secrets before it is stored, and debug stack traces do not include function arguments.

## Not yet implemented

- Background sync jobs.

Background execution must include non-parallel locks, explicit limits, tracking, and rollback documentation before production testing.
