const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');
const { loginNextcloud, skipUnlessAuthEnabled } = require('./helpers/nextcloud-auth');

const TEST_USER = process.env.SAKURAALBUM_TEST_USER || 'albentest';
const SOURCE = `/Photos/SakuraAlbumBrowserJourney-${new Date().toISOString().replace(/[-:.TZ]/g, '').slice(0, 14)}`;
const SCREENSHOT_DIR =
  process.env.SAKURAALBUM_SCREENSHOT_DIR ||
  path.join(process.cwd(), 'test-results', `sakuraalbum-browser-journey-${Date.now()}`);

test.use({
  trace: 'off',
  screenshot: 'off',
  video: 'off',
});

test.describe('SakuraAlbum full browser user journey @auth', () => {
  test.beforeEach(() => {
    skipUnlessAuthEnabled();
  });

  test.afterEach(async ({ page }) => {
    if (process.env.SAKURAALBUM_AUTH_TESTS !== '1' || page.isClosed()) {
      return;
    }
    try {
      await resetSakuraAlbum(page);
      await dav(page, 'DELETE', SOURCE, { ok: [204, 404] });
      await dav(page, 'DELETE', '/SakuraAlbum Exports', { ok: [204, 404] });
    } catch (error) {
      console.warn(`SakuraAlbum browser journey cleanup failed: ${error.message}`);
    }
  });

  test('handles create, modify, move, folder rename, delete, preview, export UI, and reset', async ({ page }) => {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

    await loginNextcloud(page);
    await page.goto('/settings/user/sakuraalbum', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#sakuraalbum-personal-settings')).toBeVisible({ timeout: 30000 });
    await shot(page, '01-settings-start.png');

    await resetSakuraAlbum(page);
    await dav(page, 'DELETE', SOURCE, { ok: [204, 404] });

    await createUserFiles(page);
    await saveJourneySettings(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#sakuraalbum-personal-settings')).toBeVisible({ timeout: 30000 });
    await shot(page, '02-settings-configured.png');

    await page.getByRole('button', { name: 'Quellordner hinzufuegen' }).click();
    await expect(page.locator('.sakuraalbum-folder-browser')).toBeVisible();
    await shot(page, '03-folder-picker.png');
    await page.getByRole('button', { name: 'Schliessen' }).click();

    await page.getByRole('button', { name: 'Vorschau', exact: true }).click();
    await expect(page.locator('#ska-preview-output')).toContainText('Geplante Alben', { timeout: 15000 });
    await expect(page.locator('#ska-preview-output')).toContainText('Visuelle Vorschau');
    await expect(page.locator('.sakuraalbum-preview-tree')).toBeVisible();
    await shot(page, '04-preview.png');
    await shotOutput(page, '04b-preview-output.png');

    const initialSync = await queueAndProcess(page, 'browser_initial');
    expect(initialSync.succeededUsers).toBeGreaterThanOrEqual(1);
    const created = await managedAlbums(page);
    expect(created.total).toBeGreaterThanOrEqual(3);
    expect(created.albums.some((album) => String(album.targetPath).includes('SkipMe'))).toBe(false);

    await page.getByRole('button', { name: 'Verwaltete Alben' }).click();
    await expect(page.locator('#ska-preview-output')).toContainText('Verwaltet', { timeout: 15000 });
    await shot(page, '05-managed-after-create.png');
    await shotOutput(page, '05b-managed-after-create-output.png');

    await putPng(page, `${SOURCE}/Events/Birthday/two.png`, PNG_ALT);
    const modifySync = await queueAndProcess(page, 'browser_modify_file');
    expect(modifySync.failedUsers).toBe(0);

    await dav(page, 'MKCOL', `${SOURCE}/Events/RenamedParty`, { ok: [201, 405] });
    await dav(page, 'MOVE', `${SOURCE}/Events/Birthday/one.png`, {
      destination: `${SOURCE}/Events/RenamedParty/one-moved.png`,
      ok: [201, 204],
    });
    const moveSync = await queueAndProcess(page, 'browser_move_file');
    expect(moveSync.failedUsers).toBe(0);

    await dav(page, 'MOVE', `${SOURCE}/People`, {
      destination: `${SOURCE}/PeopleRenamed`,
      ok: [201, 204],
    });
    const folderRenameSync = await queueAndProcess(page, 'browser_rename_folder');
    expect(folderRenameSync.failedUsers).toBe(0);

    await dav(page, 'DELETE', `${SOURCE}/Events/Birthday/two.png`, { ok: [204] });
    const deleteSync = await queueAndProcess(page, 'browser_delete_file');
    expect(deleteSync.failedUsers).toBe(0);

    const changed = await managedAlbums(page);
    expect(changed.albums.some((album) => String(album.targetPath).includes('RenamedParty'))).toBe(true);
    expect(changed.albums.some((album) => String(album.targetPath).includes('PeopleRenamed'))).toBe(true);

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#sakuraalbum-personal-settings')).toBeVisible({ timeout: 30000 });
    await page.getByRole('button', { name: 'Verwaltete Alben' }).click();
    await expect(page.locator('#ska-preview-output')).toContainText('RenamedParty', { timeout: 15000 });
    await shot(page, '06-managed-after-file-operations.png');
    await shotOutput(page, '06b-managed-after-file-operations-output.png');

    await page.locator('#ska-managed-stale-preview').click();
    await expect(page.locator('#ska-preview-output')).toContainText('Veraltete verwaltete Alben', { timeout: 15000 });
    await shot(page, '06c-stale-managed-preview.png');
    await shotOutput(page, '06d-stale-managed-preview-output.png');
    page.once('dialog', async (dialog) => {
      await dialog.accept('DELETE_MANAGED_ALBUMS');
    });
    await page.locator('#ska-delete-confirm').click();
    await expect(page.locator('#ska-status')).toContainText('Loeschjob', { timeout: 15000 });
    await expect
      .poll(async () => {
        const afterStaleCleanup = await managedAlbums(page);
        return afterStaleCleanup.albums.some((album) => String(album.albumName).includes('People - Friends'));
      }, { timeout: 10000 })
      .toBe(false);

    await page.getByRole('button', { name: 'Album-Downloads' }).click();
    await expect(page.locator('#ska-preview-output')).toContainText('Album exportieren', { timeout: 15000 });
    await shot(page, '07-download-center.png');
    await shotOutput(page, '07b-download-center-output.png');

    await page.getByRole('button', { name: 'Konto-Reset pruefen' }).click();
    await expect(page.locator('#ska-preview-output')).toContainText('RESET_SAKURAALBUM', { timeout: 15000 });
    await shot(page, '08-reset-preview.png');
    await shotOutput(page, '08b-reset-preview-output.png');

    await resetSakuraAlbum(page);
    await dav(page, 'DELETE', SOURCE, { ok: [204, 404] });
    await dav(page, 'DELETE', '/SakuraAlbum Exports', { ok: [204, 404] });

    const finalAlbums = await managedAlbums(page);
    expect(finalAlbums.total).toBe(0);
    await shot(page, '09-after-reset.png');
  });
});

async function createUserFiles(page) {
  await dav(page, 'MKCOL', '/Photos', { ok: [201, 405] });
  await dav(page, 'MKCOL', SOURCE, { ok: [201, 405] });
  for (const folder of [
    'Events',
    'Events/Birthday',
    'People',
    'People/Friends',
    'Collapse',
    'Collapse/A',
    'Collapse/A/B',
    'SkipMe',
  ]) {
    await dav(page, 'MKCOL', `${SOURCE}/${folder}`, { ok: [201, 405] });
  }

  await putPng(page, `${SOURCE}/Events/Birthday/one.png`, PNG_ONE);
  await putPng(page, `${SOURCE}/Events/Birthday/two.png`, PNG_TWO);
  await putPng(page, `${SOURCE}/People/Friends/friend.png`, PNG_ONE);
  await putPng(page, `${SOURCE}/Collapse/A/B/collapsed.png`, PNG_TWO);
  await putPng(page, `${SOURCE}/SkipMe/skip.png`, PNG_ALT);
}

async function saveJourneySettings(page) {
  await api(page, '/apps/sakuraalbum/api/v1/user/settings', 'PUT', {
    settings: {
      enabled: true,
      includePaths: [SOURCE],
      sourceFolders: [
        {
          path: SOURCE,
          enabled: true,
          mode: 'depth',
          albumDepth: 2,
        },
      ],
      folderRules: [
        {
          path: `${SOURCE}/Collapse`,
          enabled: true,
          mode: 'single_album',
          albumDepth: 0,
        },
        {
          path: `${SOURCE}/SkipMe`,
          enabled: true,
          mode: 'exclude',
          albumDepth: 0,
        },
      ],
      excludePatterns: [],
      namingTemplate: 'root_relative',
      separator: ' - ',
      albumDepth: 2,
      includeImages: true,
      includeVideos: false,
      autoSyncEnabled: true,
    },
  });
}

async function queueAndProcess(page, eventType) {
  await api(page, '/apps/sakuraalbum/api/v1/sync/queue-update', 'POST', {});
  return processDueOnServer(eventType);
}

function processDueOnServer(eventType) {
  const php = `<?php
if (!defined('OC_CONSOLE')) { define('OC_CONSOLE', 1); }
require_once '/var/www/nextcloud/lib/base.php';
$mapper = \\OCP\\Server::get(\\OCA\\SakuraAlbum\\Db\\DirtyPathMapper::class);
$service = \\OCP\\Server::get(\\OCA\\SakuraAlbum\\Service\\AutoSyncService::class);
$mapper->markDirty(${phpString(TEST_USER)}, ${phpString(SOURCE)}, ${phpString(eventType)}, time() - 120);
echo json_encode($service->processDueChanges(), JSON_THROW_ON_ERROR);
`;
  const output = execFileSync('sudo', ['-u', 'www-data', 'php'], {
    input: php,
    encoding: 'utf8',
    cwd: '/var/www/nextcloud',
    maxBuffer: 1024 * 1024,
  });
  return JSON.parse(output);
}

async function managedAlbums(page) {
  return api(page, '/apps/sakuraalbum/api/v1/albums/managed?limit=200', 'GET');
}

async function resetSakuraAlbum(page) {
  const dryRun = await api(page, '/apps/sakuraalbum/api/v1/account/reset/dry-run', 'POST', {});
  if (dryRun.canReset !== true) {
    return dryRun;
  }

  return api(page, '/apps/sakuraalbum/api/v1/account/reset', 'POST', {
    confirmation: 'RESET_SAKURAALBUM',
    planFingerprint: dryRun.planFingerprint,
    deletePlanFingerprint: dryRun.deletePlanFingerprint,
  });
}

async function api(page, url, method, body) {
  return page.evaluate(
    async ({ url, method, body }) => {
      const [route, query] = String(url).split('?', 2);
      const response = await fetch(`${OC.generateUrl(route)}${query ? `?${query}` : ''}`, {
        method,
        headers: {
          'Content-Type': 'application/json',
          requesttoken: OC.requestToken || '',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error((payload && (payload.message || payload.error)) || `HTTP ${response.status}`);
      }
      return payload;
    },
    { url, method, body },
  );
}

async function putPng(page, filePath, base64) {
  await page.evaluate(
    async ({ filePath, encodedPath, base64, username }) => {
      const binary = atob(base64);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
      }
      const url = `${location.origin}/remote.php/dav/files/${encodeURIComponent(username)}${encodedPath}`;
      const response = await fetch(url, {
        method: 'PUT',
        headers: {
          'Content-Type': 'image/png',
          requesttoken: OC.requestToken || '',
        },
        body: bytes,
      });
      if (![200, 201, 204].includes(response.status)) {
        throw new Error(`DAV PUT ${filePath} failed with HTTP ${response.status}`);
      }
    },
    { filePath, encodedPath: encodeDavPath(filePath), base64, username: TEST_USER },
  );
}

async function dav(page, method, filePath, options = {}) {
  const ok = options.ok || [200, 201, 204];
  return page.evaluate(
    async ({ method, filePath, encodedPath, encodedDestination, ok, username }) => {
      const headers = {
        requesttoken: OC.requestToken || '',
      };
      if (encodedDestination) {
        headers.Destination = `${location.origin}/remote.php/dav/files/${encodeURIComponent(username)}${encodedDestination}`;
      }
      const url = `${location.origin}/remote.php/dav/files/${encodeURIComponent(username)}${encodedPath}`;
      const response = await fetch(url, { method, headers });
      if (!ok.includes(response.status)) {
        throw new Error(`DAV ${method} ${filePath} failed with HTTP ${response.status}`);
      }
      return response.status;
    },
    {
      method,
      filePath,
      encodedPath: encodeDavPath(filePath),
      encodedDestination: options.destination ? encodeDavPath(options.destination) : '',
      ok,
      username: TEST_USER,
    },
  );
}

async function shot(page, name) {
  await page.screenshot({
    path: path.join(SCREENSHOT_DIR, name),
    fullPage: true,
  });
}

async function shotOutput(page, name) {
  const output = page.locator('#ska-preview-output');
  await output.scrollIntoViewIfNeeded();
  await output.screenshot({
    path: path.join(SCREENSHOT_DIR, name),
  });
}

function phpString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function encodeDavPath(filePath) {
  return `/${String(filePath)
    .replace(/^\/+/, '')
    .split('/')
    .filter(Boolean)
    .map((part) => encodeURIComponent(part))
    .join('/')}`;
}

const PNG_ONE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
const PNG_TWO = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8AABQMBgJ8H6YAAAAAASUVORK5CYII=';
const PNG_ALT = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==';
