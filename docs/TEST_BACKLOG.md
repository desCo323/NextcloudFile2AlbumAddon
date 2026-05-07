# SakuraAlbum Test and UX Backlog

This backlog defines the next functional, security, usability, and readability checks for SakuraAlbum updates. Live execution must use only the dedicated test user `albentest` and must start with a backup plus rollback prompt.

## Execution rules

- Never run live write tests without a fresh app/database backup.
- Use only `albentest` for live tests unless a human explicitly approves a different user.
- Keep test folders isolated below `/Photos/SakuraAlbum...`.
- Enable debug logging before test execution.
- Export diagnostic JSON and CSV before and after each test block.
- Reset `albentest` after every write/export/delete block.
- Check `occ status` before and after every live test.
- Record results in `docs/SESSION_STATE.md`.

## Preflight before every update

| ID | Area | Check | Expected result | Status |
| --- | --- | --- | --- | --- |
| PRE-01 | Build | `./scripts/self-check.sh` | All static, syntax, metadata, and safety checks pass. | Ready |
| PRE-02 | Packaging | `./scripts/build-artifact.sh` | Clean `sakuraalbum/` package without local artifacts or secrets. | Ready |
| PRE-03 | Production update | `./scripts/production-update.sh --preflight` | Package builds, worktree is clean, Nextcloud status is healthy. | Ready |
| PRE-04 | Secret scan | Token and test-password pattern scan | No secrets in source tree, package, git remote, or docs. | Ready |
| PRE-05 | License consistency | README, `LICENSE.md`, metadata, store checklist | Preview license is visible; store blocker is explicit. | Ready |

## Functional live tests with `albentest`

| ID | Area | Scenario | Steps | Expected result | Priority |
| --- | --- | --- | --- | --- | --- |
| FUN-01 | First generation | Single source folder as one album | Create one isolated folder with one image, enable SakuraAlbum for `albentest`, preview, write, reset. | One managed album and one link are created; reset removes only managed album data. | P0 |
| FUN-02 | Multi-source | Two independent source folders | Configure two non-overlapping sources with different names. | Both sources produce separate predictable albums; no collision. | P0 |
| FUN-03 | Overlap guard | `/Photos` plus nested `/Photos/Sub` | Save/preview overlapping sources. | Write is blocked with understandable overlap message. | P0 |
| FUN-04 | Folder rule depth | Standard depth plus custom depth rule | Configure global depth 1 and one subtree depth 2. | Preview reflects custom subtree depth only. | P1 |
| FUN-05 | Single-album rule | Collapse large subtree | Configure one subtree as `Alles in ein Album`. | All media below subtree is grouped into one album. | P1 |
| FUN-06 | Exclude rule | Exclude noisy folder | Add screenshots/export folder exclusion. | Excluded media is absent from preview and write plan. | P1 |
| FUN-07 | Auto-sync create | File event creates media | Queue source, add image, wait debounce/Cron. | Queue entry is processed and album updates without manual write. | P0 |
| FUN-08 | Auto-sync rename/delete | Rename and delete source media | Rename/delete test files in source. | No generic file-event errors; generated album reconciles later. | P0 |
| FUN-09 | Missing managed album repair | Delete managed Photos album outside SakuraAlbum | Remove Photos album row through controlled helper, run Auto-Sync. | SakuraAlbum detects missing managed album and rebuilds it. | P0 |
| FUN-10 | Large first run chunking | Many small media files | Set low per-run limits and queue first generation. | Cursor progresses over multiple runs; progress remains meaningful. | P1 |
| FUN-11 | Direct managed ZIP | Download small managed album | Prepare direct ZIP under admin limits. | ZIP plan succeeds and contains expected file count. | P1 |
| FUN-12 | Background export | Export managed and native Photos albums | Queue export jobs, poll status, download part links. | Job completes, `.nomedia`/`.noimage` exist, part rules apply. | P1 |
| FUN-13 | Reset | Full account reset | Generate test albums, run reset dry-run and reset. | Managed albums, queue, cursors, and user settings are cleared; files remain. | P0 |

## Security and abuse tests

| ID | Risk | Scenario | Expected result | Priority |
| --- | --- | --- | --- | --- |
| SEC-01 | CSRF | Scan controllers for `NoCSRFRequired` | No controller disables CSRF. | P0 |
| SEC-02 | Unauthorized access | Personal routes with another user id | Server ignores foreign user ids and operates on authenticated user only. | P0 |
| SEC-03 | Admin routes | Non-admin calls admin APIs | Request is rejected. | P0 |
| SEC-04 | Path traversal | Source paths with `../`, double slashes, encoded traversal | Path normalization rejects unsafe values. | P0 |
| SEC-05 | XSS/readability | Folder and album names containing HTML-like text | UI escapes names and remains readable. | P0 |
| SEC-06 | Destructive confirmation | Delete/reset without exact confirmation | Operation is blocked before Photos changes. | P0 |
| SEC-07 | Stale fingerprint | Reuse old dry-run/delete fingerprint | Write/delete is blocked. | P0 |
| SEC-08 | Secret redaction | Diagnostic context with `token`, `password`, bearer strings | Stored logs contain redacted values only. | P0 |
| SEC-09 | Quotas | Exceed album/media quotas | Write/chunk is blocked before Photos modification. | P1 |
| SEC-10 | Maintenance window | Queue outside allowed window | Work waits; status explains window. | P1 |
| SEC-11 | Path abuse hardening | Very long paths, long segments, control characters, too many segments | Requests are rejected during normalization and do not reach scans or logs as raw payload. | P0 |
| SEC-12 | Export abuse limits | Queue native Photos album above background export file/byte limits | Job creation is blocked with `album_export_limit_exceeded`; no background job or ZIP files are created. | P0 |
| SEC-13 | Diagnostic CSV abuse | Repeated CSV downloads plus formula-like log messages | Stored CSV copies are pruned by age/count and cells are safe for spreadsheet opening. | P1 |
| SEC-14 | Log retention | Old debug logs beyond retention | Log table pruning removes stale rows without blocking new log writes. | P1 |

## Usability and readability tests

| ID | UX topic | Test | Desired outcome | Priority |
| --- | --- | --- | --- | --- |
| UX-01 | First-run clarity | Fresh `albentest` opens personal settings | One obvious primary action, no intimidating advanced controls above the fold. | P0 |
| UX-02 | Source folder selection | Add, remove, and inspect multiple sources | User understands source vs. folder rule without reading documentation. | P0 |
| UX-03 | Folder rules | Create depth, single-album, and exclude rules | Rule effects are visible in preview and labels are unambiguous. | P0 |
| UX-04 | Preview interpretation | Run preview after rule changes | Summary explains planned albums, links, blockers, and next action. | P0 |
| UX-05 | Auto-sync status | Queue pending, due, processing, done | Status text does not look stuck; next run text is stable and understandable. | P0 |
| UX-06 | Managed albums | List, select, delete preview, reset | Delete/reset danger zone is clear but not confusing. | P1 |
| UX-07 | Downloads | Start background export and poll jobs | User sees that large downloads can take time and where files appear. | P1 |
| UX-08 | Diagnostics | Prepare JSON and CSV report | User understands that mail is not active yet and CSV is for support. | P1 |
| UX-09 | Mobile/narrow layout | Use personal settings on small viewport | Buttons wrap cleanly; table data remains readable or becomes stacked. | P1 |
| UX-10 | Language | German labels and help text | Avoid technical terms where possible; explain unavoidable terms inline. | P1 |

## UX improvement backlog

| ID | Improvement | Reason | Priority |
| --- | --- | --- | --- |
| UI-01 | Convert source folder setup into a step-by-step wizard | Current source/rule model is powerful but cognitively heavy. | P0 |
| UI-02 | Separate normal workflow from advanced test tools more strongly | Manual write/dry-run tools can confuse normal users. | P0 |
| UI-03 | Add inline examples for album depth | Users often ask what depth means. | P0 |
| UI-04 | Add visual preview tree | Tables explain results, but a tree would make folder rules easier to understand. | P1 |
| UI-05 | Improve empty states | "No albums yet" should tell the next safe action. | P1 |
| UI-06 | Make danger zone more structured | Reset/delete actions should be visually isolated with plain-language consequences. | P1 |
| UI-07 | Add status timeline | Pending, waiting for debounce, waiting for Cron, processing, complete should be shown as a sequence. | P1 |
| UI-08 | Add copyable support bundle summary | Admins should get one concise block with health, queue, and recent errors. | P2 |
| UI-09 | Add accessibility pass | Keyboard focus, contrast, button labels, and screen-reader order need a dedicated review. | P1 |
| UI-10 | Add screenshot/demo assets for GitHub | The project page will be stronger with real UI screenshots after the design stabilizes. | P2 |
| UI-11 | Fix low-contrast button text everywhere | User reported that actions such as `Alle verwalteten pruefen` and `Konto-Reset pruefen` are barely readable because the text is too pale; audit all SakuraAlbum buttons, especially danger/secondary buttons, and enforce readable contrast in normal, hover, focus, disabled, light-theme, and dark-theme states. | P0 |

## Operational improvement backlog

| ID | Improvement | Reason | Priority |
| --- | --- | --- | --- |
| OPS-01 | Split health status into active blockers vs. recent history | The diagnostic report can be `critical` because of older resolved warnings/errors even when the current queue is clean. | P1 |
| OPS-02 | Remove deprecated container aliases from request paths | Nextcloud logs level-0 deprecation notices for `OCP\\IServerContainer`/`OCP\\AppFramework\\IAppContainer` during SakuraAlbum UI polling. | P1 |
| OPS-03 | Reduce lazy AppConfig/UserConfig debug noise | Debug logs can contain level-0 lazy-loading notices during status polling; this makes operational review noisier. | P2 |
| OPS-04 | Track external Nextcloud storage/versioning warnings separately | The 1.0.7 event test exposed a non-SakuraAlbum `files_versions`/trashbin warning that should not be confused with SakuraAlbum health. | P2 |

## Next controlled test window proposal

1. Back up live SakuraAlbum app directory and relevant tables.
2. Deploy the next test build only if the production preflight passes.
3. Enable debug logging and conservative limits.
4. Run `FUN-01`, `FUN-03`, `FUN-07`, `FUN-08`, `SEC-08`, `UX-01`, `UX-02`, and `UX-05`.
5. Export diagnostic JSON and CSV.
6. Reset `albentest`.
7. Restore production state if any regression appears.
8. Convert findings into issues or update tasks.

## Latest executed baseline

Date: 2026-05-07 22:34 CEST

Environment:

- SakuraAlbum 1.0.8 deployed through `scripts/production-update.sh --deploy`.
- Nextcloud stayed healthy before and after testing: `maintenance=false`, `needsDbUpgrade=false`.
- Manual DB/app backup before deploy/test: `/home/cloud/sakuraalbum-backups/sakuraalbum-108-security-test-20260507-222556`.
- Production-update app backup: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-update-1.0.8-20260507-222621`.
- Test user: `albentest`.

Executed:

- Local source self-check and live deployed self-check both passed.
- `scripts/live-smoke.php` covered dry-run, write, direct managed ZIP preparation, account reset, and cleanup.
- `scripts/live-regression-104.php` covered folder rules, exclude rule, Auto-Sync processing, missing managed album repair, background album export, downloadable ZIP part, and reset cleanup.
- `scripts/live-security-smoke.php` covered path hardening, background export file-limit blocking, background export success under limits, diagnostic CSV formula protection, and reset cleanup.
- Live `tests/Smoke/SecuritySmokeTest.php` passed against the deployed app.

Post-check:

- `albentest` active managed albums: 0.
- `albentest` dirty paths: 0.
- `albentest` sync cursors: 0.
- `albentest` download jobs: 0.
- `albentest` Photos albums: 0.
- `albentest` Photos album links: 0.
- Test folders and `SakuraAlbum Exports`: absent.
- Nextcloud log tail contained no SakuraAlbum/PHP fatal/error entries.
- SakuraAlbum recent errors in the last 30 minutes: 0.
- One warning remained by design from the missing-managed-album repair test: `auto_sync_missing_managed_album_refresh_queued`.

Covered backlog items:

- `FUN-01`, `FUN-04`, `FUN-05`, `FUN-06`, `FUN-07`, `FUN-09`, `FUN-11`, `FUN-12`, `FUN-13`.
- `SEC-01`, `SEC-04`, `SEC-08`, `SEC-11`, `SEC-12`, `SEC-13`.

## Previous executed baseline

Date: 2026-05-07 19:32 CEST

Environment:

- Live Nextcloud was healthy before and after the smoke (`maintenance=false`, `needsDbUpgrade=false`).
- Live app version was the already installed SakuraAlbum build, not the unreleased documentation/license worktree.
- Backup path: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-license-doc-smoke-20260507-193126`.
- Test user: `albentest`.

Executed:

- Isolated smoke source `/Photos/SakuraAlbumV1Smoke`.
- Dry-run planned 1 album and 1 media link.
- Write created 1 managed album and linked 1 file.
- Direct managed ZIP preparation found 1 file and 68 bytes.
- Account reset completed and deleted only SakuraAlbum-managed Photos albums.
- Cleanup removed the smoke folder.

Post-check:

- `oc_photos_albums` for `albentest`: 0.
- Photos album links for `albentest`: 0.
- SakuraAlbum dirty paths for `albentest`: 0.
- SakuraAlbum cursors for `albentest`: 0.
- SakuraAlbum download jobs for `albentest`: 0.
- SakuraAlbum active managed albums for `albentest`: 0; historical rows remain with status `deleted`.

Covered backlog items:

- `FUN-01` baseline pass.
- `FUN-11` baseline pass for direct ZIP preparation.
- `FUN-13` baseline pass for reset cleanup.

Not covered:

- Browser UI usability, mobile layout, folder-rule UX, CSV endpoint tests, and 1.0.7-specific Health CSV UI remain open for a browser test window. Server-side auto-sync event tests passed in the controlled 1.0.6/1.0.7 test window.

## Latest controlled update test

Date: 2026-05-07 19:40-19:54 CEST

Builds:

- Started from live SakuraAlbum `1.0.4`.
- Deployed `1.0.6` for the requested controlled test window.
- Found and fixed a diagnostic logging defect; deployed hotfix `1.0.7`.

Backups:

- Pre-1.0.6 backup: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-106-test-20260507-193858`.
- Pre-1.0.7 backup: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-107-logfix-20260507-195033`.

Executed:

- Production preflight before deployment.
- `occ upgrade` from 1.0.4 to 1.0.6 and then 1.0.7.
- Live regression with `albentest`: dry-run, auto-sync processing, missing managed album repair, background export, reset.
- Real file-event auto-sync test: create, write, rename, delete, due queue, forced Nextcloud background-job execution, verification, cleanup.
- Diagnostic JSON and CSV export.
- Direct `info`-level log write self-test after 1.0.7.
- Nextcloud server-log review for SakuraAlbum failures after the hotfix.

Passed:

- Auto-sync queue processed from `pending=1` to `pending=0` and `failed=0`.
- Background job created 1 managed album with 1 media file in the event test.
- Reset removed generated Photos album, queue row, cursor, and test folders.
- `info`-level diagnostic logs now persist with `level=info`.
- No `SakuraAlbum failed to write app log` entries after 1.0.7.
- Final Nextcloud status: `maintenance=false`, `needsDbUpgrade=false`, live SakuraAlbum `1.0.7`.

Findings:

- 1.0.6 exposed a real logging bug for `info`-level app logs on MySQL/MariaDB; fixed in 1.0.7.
- The first 1.0.7 migration attempt failed because Doctrine `changeColumn()` received a string `type`; corrected migration and reran `occ upgrade` successfully.
- Nextcloud server log still contains non-SakuraAlbum level-0 deprecation/lazy-loading notices during UI polling.
- One external `files_versions`/trashbin warning appeared during the 1.0.7 event test; this is not a SakuraAlbum app-log failure but should be tracked separately.

Covered backlog items:

- `FUN-07` pass.
- `FUN-08` pass.
- `FUN-09` pass.
- `FUN-12` server-side pass for small export job.
- `SEC-08` pass for stored app-log behavior already verified earlier; 1.0.7 additionally fixed `info`-level persistence.
- `UX-05` partially pass server-side: status fields are stable after processing; browser wording still needs manual review.

## Result template

```text
Date:
Build:
Nextcloud version:
Live backup:
Test user:
Executed tests:
Passed:
Failed:
Warnings:
Diagnostic CSV:
Cleanup result:
Next actions:
```
