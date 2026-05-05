# Security Policy

SakuraAlbum is developed with production safety as the primary constraint.

## Reporting

Open a private report with the repository owner for security-sensitive issues. Do not include credentials, app passwords, GitHub tokens, or private Nextcloud URLs in public issues.

## Current guarantees

- Preview requests do not write albums.
- Personal API routes operate on the authenticated user only.
- Admin settings routes require an authorized admin setting.
- Stored generated-album metadata is separate from user-created Photos albums.
- Debug context is sanitized for common secrets before it is stored.

## Not yet implemented

- Live album creation.
- Bulk deletion execution.
- Background sync jobs.

These features must include explicit confirmation, tracking, and rollback documentation before production testing.
