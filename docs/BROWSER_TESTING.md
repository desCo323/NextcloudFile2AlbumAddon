# SakuraAlbum Browser Tests

This document describes the Playwright browser tests used for SakuraAlbum.

## Local non-authenticated smoke

Run:

```bash
npm run test:browser
```

This starts Chromium and verifies UI styling that does not need a live Nextcloud login. Authenticated tests are present in the suite but are skipped unless explicitly enabled.

## Live authenticated smoke

Live authenticated tests must only use the dedicated test account `albentest`.

Required environment variables:

```bash
export SAKURAALBUM_BASE_URL="https://chaosnet.me"
export SAKURAALBUM_TEST_USER="albentest"
export SAKURAALBUM_TEST_PASSWORD="..."
npm run test:browser:auth
```

Rules:

- Do not commit or store the password.
- Do not write the password into `.env`, shell scripts, documentation, traces, screenshots, or Playwright storage-state files.
- Keep authenticated UI tests read-only unless the test begins with a documented backup/reset window.
- Use `trace: off`, `screenshot: off`, and `video: off` for authenticated tests to avoid local artifacts containing session data.
- After live auth tests, check SakuraAlbum logs and verify that `albentest` has no managed albums, dirty queue entries, cursors, or download jobs unless the test intentionally created them and then reset them.

## Current authenticated coverage

`tests/Browser/authenticated-settings.auth.spec.js` currently verifies:

- `albentest` can log in through the real Nextcloud login page.
- The personal SakuraAlbum settings section loads from `/settings/user/sakuraalbum`.
- Main SakuraAlbum action buttons are visible.
- Danger buttons keep high-contrast colors in the real Nextcloud theme.
- The live sync-status API can be read from the browser session.
- The folder picker opens and lists available folders without saving settings.

Future browser tests should add authenticated flows for preview-only planning, read-only download-center rendering, mobile/narrow layout, and eventually write/reset flows inside a controlled backup test window.
