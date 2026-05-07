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
