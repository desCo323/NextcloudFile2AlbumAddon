# SakuraAlbum Security Model

SakuraAlbum is built for productive Nextcloud servers. The app therefore treats every user input, every Photos album id, and every long-running background operation as potentially hostile until the server has checked ownership, scope, size, and freshness.

## Core principles

- **Authenticated user scope:** personal routes never accept a foreign user id as authority. They operate on Nextcloud's authenticated user.
- **Admin isolation:** central settings, diagnostics, and Auto-Sync controls require Nextcloud's authorized admin settings permission.
- **CSRF protection:** controllers keep the framework default CSRF protection enabled.
- **Preview before write:** album creation, deletion, and account reset use server-recorded dry-run fingerprints and exact confirmation text before destructive or persistent changes.
- **Managed ownership:** SakuraAlbum deletes or repairs only albums that are tracked as SakuraAlbum-managed records and whose Photos album id, owner, and current name still match.
- **Resource limits:** scans, writes, direct ZIP downloads, background exports, Auto-Sync, diagnostic context, and stored CSV files are bounded by server-side limits.
- **Secret minimization:** diagnostic context is redacted before storage; raw preview settings are no longer logged.

## Attack scenarios and mitigations

| Scenario | Risk | Mitigation |
| --- | --- | --- |
| A user sends paths like `../../config` or very long nested paths. | Path traversal, memory pressure, confusing log/UI output. | `PathHelper` normalizes paths, blocks traversal, NUL/control characters, overly long paths, overly long segments, and excessive segment count. |
| A user tries to create or delete albums without first previewing the exact plan. | Unauthorized or stale writes after settings changed. | Write/delete/reset require exact confirmation plus a recent server-stored fingerprint for the same user and plan. |
| A user passes another user's Photos album id to export/download endpoints. | Cross-user data access. | Album adapters re-load the Photos album and verify the current owner before file ids or files are read. |
| A huge native Photos album is queued repeatedly for export. | Disk, CPU, and background-job denial of service. | Background export creation and execution enforce admin limits for total album file count and readable byte size; active jobs per user remain capped. |
| A ZIP export fails mid-run. | Temporary files can accumulate and consume disk. | Export jobs clean temporary copied files and temporary ZIP files in `finally` blocks. |
| A user repeatedly downloads diagnostic CSV files. | AppData growth and spreadsheet formula injection. | CSV copies are pruned by age/count, cells are length-capped, NUL bytes are stripped, and formula-like cells are prefixed safely. |
| Logs accidentally contain secrets in context. | Token/password exposure in diagnostics. | Common secret keys and bearer/basic/GitHub token patterns are redacted before storage; exception traces omit arguments. |
| Debug logging is left enabled. | Large log table and noisy diagnostics. | Log retention is enforced periodically according to the admin retention setting. |
| Generated export ZIP files are scanned back into albums. | Recursive media/export growth. | Export folders contain `.nomedia` and `.noimage` marker files. |

## Admin-controlled security limits

Admins can tune the following limits without changing user workflows:

- preview folders/files,
- write-job folders/files/albums,
- managed albums/media links per user,
- direct managed ZIP file/byte limits,
- background export file/byte limits,
- Auto-Sync debounce, user count, event count, runtime, and maintenance window,
- debug retention and maximum stored context size.

The defaults are intentionally conservative for HTTP ZIP streaming and permissive but bounded for background exports. Large exports still use 1 GiB ZIP parts, but they must fit inside the administrator's export limits.

## Update rule

Every new feature that writes, deletes, exports, queues, logs, or scans data must declare:

- which authenticated user scope it operates in,
- which server-side limit bounds it,
- whether it needs preview/fingerprint/confirmation,
- what is logged and what is deliberately not logged,
- which functional and security backlog items validate it.
